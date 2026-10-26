<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Aperçu d'un lien publié (titre, description, image, site), comme sur Facebook.
 *
 * Sécurité : seules les adresses http(s) publiques sont lues (jamais une adresse
 * interne au serveur ou au réseau local, y compris après redirection), avec un
 * délai court et une taille maximale. L'image est téléchargée puis ré-encodée
 * en WebP sur notre serveur : les visiteurs ne chargent rien depuis le site tiers.
 * En cas d'échec, la publication est enregistrée sans aperçu (le lien reste cliquable).
 */
class LinkPreviewService
{
    private const MAX_HTML = 700_000;
    private const MAX_IMAGE = 5_000_000;

    public function __construct(private MediaService $media) {}

    /** @return array{link_url:string,link_title:?string,link_description:?string,link_image_path:?string,link_site:?string}|null */
    public function fetch(string $url): ?array
    {
        if (! config('mxconnect.social.link_preview', true)) {
            return null;
        }
        try {
            [$html, $final] = $this->get($url, 'text/html', self::MAX_HTML);
            if ($html === null) {
                return null;
            }
            $meta = $this->parse($html, $final);
            if (! $meta['title'] && ! $meta['description']) {
                return null;
            }
            $image = null;
            if ($meta['image']) {
                $image = $this->storeImage($meta['image']);
            }

            return [
                'link_url'         => Str::limit($url, 490, ''),
                'link_title'       => $meta['title'] ? Str::limit($meta['title'], 250) : null,
                'link_description' => $meta['description'] ? Str::limit($meta['description'], 480) : null,
                'link_image_path'  => $image,
                'link_site'        => Str::limit($meta['site'] ?: (string) parse_url($final, PHP_URL_HOST), 115),
            ];
        } catch (\Throwable $e) {
            Log::info('Aperçu de lien impossible : ' . $e->getMessage(), ['url' => $url]);

            return null;
        }
    }

    /** @return array{0:?string,1:string} contenu + adresse finale */
    private function get(string $url, string $expect, int $max): array
    {
        for ($hop = 0; $hop < 4; $hop++) {
            $ip = $this->assertPublic($url);
            $ch = curl_init($url);
            if ($ip) {
                // L'adresse IP vérifiée est imposée à curl (pas de seconde résolution DNS différente).
                $p = parse_url($url);
                $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
                curl_setopt($ch, CURLOPT_RESOLVE, [strtolower($p['host']) . ':' . $port . ':' . $ip]);
            }
            $body = '';
            $headers = [];
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; MX-CONNECT link preview; +https://m.araild.com)',
                CURLOPT_HTTPHEADER     => ['Accept: ' . ($expect === 'text/html' ? 'text/html,application/xhtml+xml' : 'image/*'), 'Accept-Language: fr,en;q=0.8'],
                CURLOPT_ENCODING       => '',
                CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$headers) {
                    if (str_contains($h, ':')) {
                        [$k, $v] = explode(':', $h, 2);
                        $headers[strtolower(trim($k))] = trim($v);
                    }

                    return strlen($h);
                },
                CURLOPT_WRITEFUNCTION  => function ($c, $chunk) use (&$body, $max) {
                    $body .= $chunk;

                    return strlen($body) > $max ? 0 : strlen($chunk);   // coupe au-delà de la taille maximale
                },
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($code >= 300 && $code < 400 && ! empty($headers['location'])) {
                $url = $this->absolute($headers['location'], $url);
                continue;
            }
            $type = strtolower($headers['content-type'] ?? '');
            if ($code !== 200 || ! str_contains($type, $expect === 'text/html' ? 'html' : 'image/')) {
                return [null, $url];
            }

            return [$body, $url];
        }

        return [null, $url];
    }

    /** Refuse toute adresse qui n'est pas http(s) vers une IP publique. */
    private function assertPublic(string $url): ?string
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($p['user'])) {
            throw new \RuntimeException('Adresse non autorisée.');
        }
        if (in_array($host, (array) config('mxconnect.social.link_preview_test_hosts', []), true)) {
            return null;   // uniquement pour les tests automatisés (vide en production)
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            throw new \RuntimeException('Nom de domaine introuvable.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('Adresse interne refusée.');
            }
        }

        return filter_var($host, FILTER_VALIDATE_IP) ? null : $ips[0];
    }

    /** @return array{title:?string,description:?string,image:?string,site:?string} */
    private function parse(string $html, string $base): array
    {
        $html = mb_substr($html, 0, 300_000);
        if (! preg_match('~charset=["\']?utf-8~i', substr($html, 0, 3000)) && ! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
        }
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $meta = [];
        foreach ($doc->getElementsByTagName('meta') as $m) {
            $key = strtolower($m->getAttribute('property') ?: $m->getAttribute('name'));
            if ($key !== '' && ! isset($meta[$key])) {
                $meta[$key] = trim(html_entity_decode($m->getAttribute('content'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
        $title = $meta['og:title'] ?? $meta['twitter:title'] ?? null;
        if (! $title && ($t = $doc->getElementsByTagName('title')->item(0))) {
            $title = trim(preg_replace('/\s+/u', ' ', $t->textContent));
        }
        $image = $meta['og:image'] ?? $meta['og:image:url'] ?? $meta['twitter:image'] ?? null;

        return [
            'title'       => $title ?: null,
            'description' => ($meta['og:description'] ?? $meta['description'] ?? $meta['twitter:description'] ?? null) ?: null,
            'image'       => $image ? $this->absolute($image, $base) : null,
            'site'        => ($meta['og:site_name'] ?? null) ?: null,
        ];
    }

    private function storeImage(string $url): ?string
    {
        try {
            [$data] = $this->get($url, 'image', self::MAX_IMAGE);
            if (! $data || ! ($info = @getimagesizefromstring($data)) || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
                return null;
            }
            $img = @imagecreatefromstring($data);
            if (! $img) {
                return null;
            }
            $stored = $this->media->storeGd($img, 'social/links/' . now()->format('Y/m') . '/' . Str::uuid(), 1000);
            Storage::disk('social')->delete($stored['thumb_path']);   // une seule taille suffit

            return $stored['path'];
        } catch (\Throwable) {
            return null;
        }
    }

    private function absolute(string $href, string $base): string
    {
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $root = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($b['scheme'] ?? 'https') . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $root . $href;
        }
        $dir = isset($b['path']) ? preg_replace('~/[^/]*$~', '/', $b['path']) : '/';

        return $root . $dir . $href;
    }
}

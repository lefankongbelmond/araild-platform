<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Mise en forme sûre du texte d'une publication ou d'un commentaire :
 *  - tout est échappé (aucun HTML saisi n'est interprété) ;
 *  - les adresses web deviennent des liens cliquables (nouvel onglet, nofollow) ;
 *  - les mentions enregistrées (@Prénom Nom, @Page) deviennent des liens ;
 *  - les mots-clés #… mènent à la recherche ;
 *  - les retours à la ligne sont conservés (au plus une ligne vide entre deux paragraphes).
 * Les emojis sont du texte Unicode : ils s'affichent tels quels.
 */
class SocialText
{
    private const URL = '(?:https?://|www\.)[^\s<>"\']+';

    /**
     * @param iterable<\App\Models\Social\Mention> $mentions
     */
    public static function render(?string $text, iterable $mentions = [], ?int $limit = null): string
    {
        $text = self::normalize((string) $text);
        if ($text === '') {
            return '';
        }
        if ($limit && mb_strlen($text) > $limit) {
            $text = Str::limit($text, $limit);
        }

        // Mentions : libellé exact → adresse (les plus longs d'abord pour « @Ana Belle » avant « @Ana »).
        $map = [];
        foreach ($mentions as $m) {
            $url = $m->url();
            if ($url && $m->label !== '') {
                $map['@' . $m->label] = $url;
            }
        }
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $parts = [self::URL];
        if ($map) {
            $parts[] = implode('|', array_map(fn ($k) => preg_quote($k, '~'), array_keys($map)));
        }
        $parts[] = '(?<![\p{L}\p{N}_&])#[\p{L}\p{N}_]{2,50}';
        $pattern = '~(' . implode(')|(', $parts) . ')~u';

        $out = '';
        $pos = 0;
        if (preg_match_all($pattern, $text, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($found as $f) {
                [$token, $offset] = $f[0];
                $out .= e(substr($text, $pos, $offset - $pos));
                $pos = $offset + strlen($token);

                if (isset($map[$token])) {
                    $out .= '<a class="mx-mention" href="' . e($map[$token]) . '">' . e($token) . '</a>';
                } elseif ($token[0] === '#') {
                    $out .= '<a class="mx-hashtag" href="' . e(route('social.search', ['q' => $token])) . '">' . e($token) . '</a>';
                } else {
                    // Ponctuation finale exclue du lien (« voir www.site.org. »).
                    $trail = '';
                    while ($token !== '' && preg_match('~[.,;:!?)\]}»]$~u', $token)) {
                        if (str_ends_with($token, ')') && substr_count($token, '(') >= substr_count($token, ')')) {
                            break;
                        }
                        $trail = mb_substr($token, -1) . $trail;
                        $token = mb_substr($token, 0, -1);
                    }
                    $href = str_starts_with(strtolower($token), 'www.') ? 'https://' . $token : $token;
                    if (filter_var($href, FILTER_VALIDATE_URL)) {
                        $shown = Str::limit(preg_replace('~^https?://(www\.)?~i', '', $token), 60);
                        $out .= '<a class="mx-link" href="' . e($href) . '" target="_blank" rel="noopener nofollow ugc">' . e($shown) . '</a>';
                    } else {
                        $out .= e($token);
                    }
                    $out .= e($trail);
                }
            }
        }
        $out .= e(substr($text, $pos));

        // Retours à la ligne → <br> SANS garder le caractère « \n » : le corps des publications
        // est affiché en « white-space: pre-line », qui l'aurait compté une deuxième fois
        // (chaque saut de ligne doublé, d'où les grands vides signalés le 27/10/2026).
        return str_replace("\n", '<br>', $out);
    }

    /**
     * Texte collé depuis Word, Facebook, WhatsApp… : fins de ligne Windows, espaces
     * insécables ou invisibles en bout de ligne, lignes « vides » qui n'en sont pas.
     * On garde au plus UNE ligne vide entre deux paragraphes.
     */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{2028}", "\u{2029}"], "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}\x{2007}\x{202F}\x{200B}\x{FEFF}]+$/mu', '', $text) ?? $text;   // fin de ligne
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text, "\n");
    }

    /** Première adresse web d'un texte (pour l'aperçu du lien). */
    public static function firstUrl(?string $text): ?string
    {
        if (! $text || ! preg_match('~' . self::URL . '~u', $text, $m)) {
            return null;
        }
        $url = rtrim($m[0], '.,;:!?)]}»');
        $url = str_starts_with(strtolower($url), 'www.') ? 'https://' . $url : $url;

        return filter_var($url, FILTER_VALIDATE_URL) ? Str::limit($url, 490, '') : null;
    }
}

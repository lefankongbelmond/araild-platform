<?php

namespace App\Support;

/**
 * Contrôle d'un fichier APK déposé dans l'App Center (2026-11-13).
 *
 * Refuse tout ce qui n'est pas un APK signé : archive ZIP valide contenant AndroidManifest.xml,
 * et au moins un certificat de signature lisible. Lit, dans l'ordre :
 *  - le bloc de signature APK (schémas v2 / v3, « APK Sig Block 42 », placé juste avant le
 *    répertoire central de l'archive) ;
 *  - à défaut, la signature JAR historique (schéma v1 : META-INF/*.RSA|DSA|EC, PKCS#7).
 * Renvoie les empreintes SHA-256 des certificats (format AA:BB:…), à comparer aux empreintes
 * déclarées dans « Application Android → Réglages » (celles de assetlinks.json).
 *
 * Ce contrôle prouve QUI a signé le fichier ; il ne remplace pas la vérification
 * cryptographique complète faite par Android à l'installation (apksigner).
 */
class ApkInspector
{
    private const SIG_BLOCK_MAGIC = 'APK Sig Block 42';
    private const V2_ID = 0x7109871a;
    private const V3_ID = 0xf05368c0;
    private const V31_ID = 0x1b93ad61;

    /**
     * @return array{fingerprints: string[], scheme: string}
     * @throws \RuntimeException message lisible, affiché tel quel
     */
    public static function inspect(string $path): array
    {
        if (! is_file($path) || filesize($path) < 100) {
            throw new \RuntimeException('Le fichier est vide ou illisible.');
        }
        $fh = fopen($path, 'rb');
        if (fread($fh, 4) !== "PK\x03\x04") {
            fclose($fh);
            throw new \RuntimeException('Ce fichier n’est pas un APK (archive ZIP attendue).');
        }
        fclose($fh);

        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('L’extension PHP zip est indisponible sur le serveur : contrôle impossible.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Archive ZIP endommagée.');
        }
        if ($zip->locateName('AndroidManifest.xml') === false) {
            $zip->close();
            throw new \RuntimeException('AndroidManifest.xml absent : ce fichier n’est pas un APK.');
        }

        $prints = self::signingBlockCertificates($path);
        $scheme = $prints ? 'v2/v3' : 'v1';
        if (! $prints) {
            $prints = self::jarCertificates($zip);
        }
        $zip->close();

        if (! $prints) {
            throw new \RuntimeException('APK non signé : aucun certificat de signature trouvé. Seuls les APK signés avec la clé officielle sont acceptés.');
        }

        return ['fingerprints' => array_values(array_unique($prints)), 'scheme' => $scheme];
    }

    public static function fingerprint(string $der): string
    {
        return strtoupper(implode(':', str_split(hash('sha256', $der), 2)));
    }

    /** Schémas v2 / v3 : certificats lus dans le bloc de signature APK. */
    private static function signingBlockCertificates(string $path): array
    {
        $size = filesize($path);
        $fh = fopen($path, 'rb');
        // Fin du répertoire central (EOCD) : 22 octets + commentaire (64 Kio max).
        $tail = min($size, 22 + 65535);
        fseek($fh, $size - $tail);
        $buf = fread($fh, $tail);
        $pos = strrpos($buf, "PK\x05\x06");
        if ($pos === false || $pos + 22 > strlen($buf)) {
            fclose($fh);

            return [];
        }
        $cdOffset = unpack('V', substr($buf, $pos + 16, 4))[1];
        if ($cdOffset < 32 || $cdOffset > $size) {
            fclose($fh);

            return [];
        }
        fseek($fh, $cdOffset - 24);
        $footer = fread($fh, 24);
        if (substr($footer, 8, 16) !== self::SIG_BLOCK_MAGIC) {
            fclose($fh);

            return [];
        }
        $blockSize = self::u64(substr($footer, 0, 8));
        $start = $cdOffset - $blockSize - 8;
        if ($blockSize < 24 || $start < 0 || $blockSize > 64 * 1024 * 1024) {
            fclose($fh);

            return [];
        }
        fseek($fh, $start);
        $block = fread($fh, $blockSize + 8);
        fclose($fh);

        $prints = [];
        $off = 8;
        $end = strlen($block) - 24;
        while ($off + 12 <= $end) {
            $len = self::u64(substr($block, $off, 8));
            if ($len < 4 || $off + 8 + $len > $end + 24) {
                break;
            }
            $id = unpack('V', substr($block, $off + 8, 4))[1];
            if (in_array($id, [self::V2_ID, self::V3_ID, self::V31_ID], true)) {
                $prints = array_merge($prints, self::signerCertificates(substr($block, $off + 12, $len - 4)));
            }
            $off += 8 + $len;
        }

        return $prints;
    }

    /** Valeur d'un schéma v2/v3 : signataires → données signées → certificats (DER). */
    private static function signerCertificates(string $value): array
    {
        $prints = [];
        try {
            $signers = self::lp($value, 0);
            $o = 0;
            while ($o < strlen($signers)) {
                $signer = self::lp($signers, $o);
                $o += 4 + strlen($signer);
                $signed = self::lp($signer, 0);
                $digests = self::lp($signed, 0);
                $certs = self::lp($signed, 4 + strlen($digests));
                $c = 0;
                while ($c < strlen($certs)) {
                    $der = self::lp($certs, $c);
                    $c += 4 + strlen($der);
                    if ($der !== '') {
                        $prints[] = self::fingerprint($der);
                    }
                }
            }
        } catch (\Throwable) {
            return $prints;
        }

        return $prints;
    }

    /** Schéma v1 : signature PKCS#7 dans META-INF. */
    private static function jarCertificates(\ZipArchive $zip): array
    {
        $prints = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('#^META-INF/[^/]+\.(RSA|DSA|EC)$#i', $name)) {
                continue;
            }
            $der = (string) $zip->getFromIndex($i);
            $pem = "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PKCS7-----\n";
            $certs = [];
            if (function_exists('openssl_pkcs7_read') && @openssl_pkcs7_read($pem, $certs)) {
                foreach ($certs as $cert) {
                    if (preg_match('#-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----#s', $cert, $m)) {
                        $prints[] = self::fingerprint(base64_decode(preg_replace('/\s+/', '', $m[1])));
                    }
                }
            }
        }

        return $prints;
    }

    /** Champ préfixé par sa longueur (uint32 little-endian). */
    private static function lp(string $buf, int $off): string
    {
        if ($off + 4 > strlen($buf)) {
            throw new \RuntimeException('bloc tronqué');
        }
        $len = unpack('V', substr($buf, $off, 4))[1];
        if ($off + 4 + $len > strlen($buf)) {
            throw new \RuntimeException('bloc tronqué');
        }

        return substr($buf, $off + 4, $len);
    }

    private static function u64(string $b): int
    {
        $p = unpack('Vlo/Vhi', $b);

        return $p['lo'] + $p['hi'] * 4294967296;
    }
}

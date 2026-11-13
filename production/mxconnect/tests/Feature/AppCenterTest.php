<?php

/*
 * App Center (2026-11-13) : page publique, statistiques anonymes (clics ≠ installations),
 * application installable par mutuelle (/installer, manifeste propre), administration,
 * APK publié seulement s'il est signé avec la clé officielle.
 */

use App\Models\AppCenter\AppCenterApp;
use App\Models\AppCenter\AppCenterInstall;
use App\Models\AppCenter\AppCenterStat;
use App\Models\Central\NetworkUser;
use App\Models\Social\SocialSetting;
use App\Support\ApkInspector;
use App\Support\AppCenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function acAdmin(string $role = 'super_admin'): \Illuminate\Foundation\Testing\TestCase
{
    Role::findOrCreate($role, 'network');
    $u = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $u->assignRole($role);

    return test()->actingAs($u, 'network')->withSession(['mfa_passed' => true]);
}

/** Certificat auto-signé de test : [pem du certificat, clé privée, empreinte SHA-256 AA:BB:…]. */
function acCert(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'MX-CONNECT test ' . random_int(1, 99999)], $key);
    $x = openssl_csr_sign($csr, null, $key, 365);
    openssl_x509_export($x, $pem);
    $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem));

    return [$pem, $key, ApkInspector::fingerprint($der), $der];
}

/** Petit APK de test : ZIP avec AndroidManifest.xml, signé en v1 (PKCS#7) et/ou v2 (bloc de signature). */
function acApk(?array $cert, bool $v1 = false, bool $v2 = true, bool $manifest = true): string
{
    $path = tempnam(sys_get_temp_dir(), 'apk');
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::OVERWRITE);
    if ($manifest) {
        $zip->addFromString('AndroidManifest.xml', str_repeat("\x03\x00", 64));
    }
    $zip->addFromString('classes.dex', "dex\n035\0" . str_repeat('x', 200));
    if ($cert && $v1) {
        $in = tempnam(sys_get_temp_dir(), 'sf'); $out = tempnam(sys_get_temp_dir(), 'p7');
        file_put_contents($in, "Signature-Version: 1.0\r\n");
        openssl_pkcs7_sign($in, $out, $cert[0], $cert[1], [], PKCS7_BINARY);
        $smime = file_get_contents($out);
        $body = substr($smime, strpos($smime, "\n\n") + 2);
        $zip->addFromString('META-INF/CERT.RSA', base64_decode(preg_replace('/\s+/', '', $body)));
    }
    $zip->close();
    if (! ($cert && $v2)) {
        return $path;
    }
    // Bloc de signature APK v2 inséré juste avant le répertoire central.
    $bin = file_get_contents($path);
    $eocd = strrpos($bin, "PK\x05\x06");
    $cd = unpack('V', substr($bin, $eocd + 16, 4))[1];
    $u32 = fn (int $n) => pack('V', $n);
    $u64 = fn (int $n) => pack('VV', $n & 0xFFFFFFFF, $n >> 32);
    $certs = $u32(strlen($cert[3])) . $cert[3];
    $signed = $u32(0) . $u32(strlen($certs)) . $certs . $u32(0);
    $signer = $u32(strlen($signed)) . $signed . $u32(0) . $u32(0);
    $signers = $u32(strlen($signer)) . $signer;
    $value = $u32(strlen($signers)) . $signers;
    $pair = $u64(strlen($value) + 4) . $u32(0x7109871a) . $value;
    $size = strlen($pair) + 8 + 16;
    $block = $u64($size) . $pair . $u64($size) . 'APK Sig Block 42';
    $out = substr($bin, 0, $cd) . $block . substr($bin, $cd, $eocd + 16 - $cd) . $u32($cd + strlen($block)) . substr($bin, $eocd + 20);
    file_put_contents($path, $out);

    return $path;
}

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();   // sinon la transaction du test suivant reste ouverte sur la mauvaise connexion
    }
});

beforeEach(function () {
    AppCenter::forget();
    AppCenterStat::query()->delete();
    AppCenterInstall::query()->delete();
    AppCenterApp::query()->delete();
    AppCenter::forget();
});

it('présente les deux applications avec leurs boutons d’installation, depuis une page liée à l’accueil', function () {
    $this->get('/applications')->assertOk()
        ->assertSee('BIENVENUE DANS', false)->assertSee('MX-CONNECT MUTUELLE')->assertSee('MX-CONNECT SOCIAL')
        ->assertSee('INSTALLER SUR PC')->assertSee('INSTALLER SUR ANDROID')
        ->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
        ->assertSee('data-app-install', false)
        ->assertSee('Gestion des cotisations')->assertSee('Messagerie privée')
        ->assertSee('Aperçu · données fictives');
    $this->get('/')->assertOk()->assertSee(route('appcenter.index'), false)->assertSee('Installer nos applications');
});

it('compte les clics à part : seule une installation signalée ou une ouverture en mode application est confirmée', function () {
    $device = str_repeat('ab', 16);
    $this->postJson('/applications/evenement', ['app' => 'social', 'event' => 'click_pc', 'os' => 'windows', 'browser' => 'chrome'])->assertNoContent();
    $this->postJson('/applications/evenement', ['app' => 'social', 'event' => 'click_android', 'os' => 'android', 'browser' => 'chrome', 'device' => $device])->assertNoContent();
    expect(AppCenterInstall::count())->toBe(0);

    $this->postJson('/applications/evenement', ['app' => 'social', 'event' => 'installed', 'os' => 'android', 'browser' => 'chrome', 'device' => $device])->assertNoContent();
    $this->postJson('/applications/evenement', ['app' => 'social', 'event' => 'installed', 'os' => 'android', 'browser' => 'chrome', 'device' => $device])->assertNoContent();
    expect(AppCenterInstall::count())->toBe(1);
    $i = AppCenterInstall::first();
    expect($i->device_hash)->not->toBe($device)->and(strlen($i->device_hash))->toBe(64)
        ->and($i->form)->toBe('mobile')->and($i->last_launch_at)->toBeNull();

    $this->postJson('/applications/evenement', ['app' => 'social', 'event' => 'launched', 'os' => 'android', 'browser' => 'chrome', 'device' => $device])->assertNoContent();
    $s = AppCenter::summary(30)['social'];
    expect($s['installs_total'])->toBe(1)->and($s['active'])->toBe(1)->and($s['installs_android'])->toBe(1)
        ->and($s['events']['click_pc'])->toBe(1)->and($s['events']['installed'])->toBe(2);

    // Valeurs inconnues : refusées ou ramenées à « other » ; aucune colonne d'adresse IP ni de compte.
    $this->postJson('/applications/evenement', ['app' => 'social', 'event' => 'pirater'])->assertStatus(422);
    $this->postJson('/applications/evenement', ['app' => 'social', 'event' => 'click_pc', 'os' => '<script>', 'browser' => 'x'])->assertNoContent();
    expect(AppCenterStat::where('os', 'other')->where('browser', 'other')->exists())->toBeTrue();
    $cols = Schema::connection('central')->getColumnListing('app_center_installs');
    expect($cols)->not->toContain('ip')->not->toContain('ip_address')->not->toContain('public_account_id')->not->toContain('user_id');
});

it('désactive une application : carte « Bientôt disponible », boutons inactifs, installation fermée', function () {
    AppCenter::apps();
    AppCenterApp::where('key', 'mutuelle')->update(['enabled' => false]);
    AppCenter::forget();
    $this->get('/applications')->assertOk()->assertSee('Bientôt disponible')->assertSee('disabled', false);
    $this->post('/applications/mutuelle', ['mutuelle' => 'x'])->assertNotFound();
});

it('administre les applications : édition réservée au super-admin, lecture des statistiques pour le modérateur', function () {
    acAdmin('moderator')->get('/reseau/app-center')->assertOk()->assertSee('Installations confirmées');
    acAdmin('moderator')->get('/reseau/app-center/social')->assertForbidden();

    acAdmin()->post('/reseau/app-center/social', [
        'name' => 'MX-CONNECT SOCIAL', 'description' => 'Le réseau social.', 'features' => "Profils\nPublications\n\n",
        'version' => '2026.11.13', 'released_on' => now()->toDateString(), 'enabled' => '1',
        'play_store_url' => 'https://exemple.com/app',
    ])->assertSessionHasErrors('play_store_url');
    acAdmin()->post('/reseau/app-center/social', [
        'name' => 'MX-CONNECT SOCIAL', 'description' => 'Le réseau social.', 'features' => "Profils\nPublications\n\n",
        'version' => '2026.11.13', 'released_on' => now()->toDateString(), 'enabled' => '1',
    ])->assertRedirect();
    $app = AppCenterApp::where('key', 'social')->first();
    expect($app->features)->toBe(['Profils', 'Publications'])->and($app->version)->toBe('2026.11.13');
    $this->get('/applications')->assertSee('Version <b>2026.11.13</b>', false);
});

it('contrôle les fichiers APK : non-ZIP, sans manifeste, non signé, signatures v1 et v2', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'x');
    file_put_contents($tmp, str_repeat('MZ', 100));
    expect(fn () => ApkInspector::inspect($tmp))->toThrow(RuntimeException::class, 'n’est pas un APK');
    expect(fn () => ApkInspector::inspect(acApk(acCert(), manifest: false)))->toThrow(RuntimeException::class, 'AndroidManifest.xml absent');
    expect(fn () => ApkInspector::inspect(acApk(null)))->toThrow(RuntimeException::class, 'APK non signé');

    $c = acCert();
    $r = ApkInspector::inspect(acApk($c, v1: false, v2: true));
    expect($r['scheme'])->toBe('v2/v3')->and($r['fingerprints'])->toBe([$c[2]]);
    $r = ApkInspector::inspect(acApk($c, v1: true, v2: false));
    expect($r['scheme'])->toBe('v1')->and($r['fingerprints'])->toBe([$c[2]]);
});

it('ne publie un APK que s’il est signé avec la clé officielle, puis le sert en téléchargement', function () {
    Storage::fake('local');
    $official = acCert(); $other = acCert();
    $upload = fn (string $p) => new UploadedFile($p, 'mx-connect.apk', 'application/vnd.android.package-archive', null, true);

    SocialSetting::put('android_sha256', null);
    acAdmin()->post('/reseau/app-center/social/apk', ['apk' => $upload(acApk($official)), 'apk_version' => '0.2.0'])
        ->assertSessionHasErrors(['apk' => 'Déclarez d’abord l’empreinte SHA-256 de la clé officielle dans « Application Android → Réglages ». Sans elle, aucun APK ne peut être vérifié.']);

    SocialSetting::put('android_sha256', $official[2]);
    acAdmin()->post('/reseau/app-center/social/apk', ['apk' => $upload(acApk($other)), 'apk_version' => '0.2.0'])->assertSessionHasErrors('apk');
    expect(AppCenterApp::where('key', 'social')->first()->hasApk())->toBeFalse();
    $this->get('/applications/social/android.apk')->assertNotFound();

    acAdmin()->post('/reseau/app-center/social/apk', ['apk' => $upload(acApk($official)), 'apk_version' => '0.2.0'])->assertSessionHasNoErrors();
    $app = AppCenterApp::where('key', 'social')->first();
    expect($app->hasApk())->toBeTrue()->and($app->apk_signer)->toBe($official[2])->and(strlen($app->apk_sha256))->toBe(64);
    AppCenter::forget();

    $r = $this->get('/applications/social/android.apk')->assertOk();
    expect($r->headers->get('Content-Type'))->toBe('application/vnd.android.package-archive');
    expect((int) AppCenterStat::where('event', 'apk_download')->sum('count'))->toBe(1);
    $this->get('/applications')->assertSee('social\\/android.apk', false);

    acAdmin()->post('/reseau/app-center/social/apk/retirer')->assertRedirect();
    AppCenter::forget();
    $this->get('/applications/social/android.apk')->assertNotFound();
});

it('rend l’espace de chaque mutuelle installable depuis son sous-domaine', function () {
    $mutual = provisionTestMutual();
    $host = test()->host;

    $this->get($host . '/installer?appareil=android')->assertOk()
        ->assertSee('Installer MX-CONNECT MUTUELLE')->assertSee('Mutuelle Équipe')
        ->assertSee('rel="manifest" href="/app-mutuelle.webmanifest"', false)->assertSee("/sw-mutuelle.js", false);
    $m = $this->get($host . '/app-mutuelle.webmanifest')->assertOk()->json();
    expect($m['name'])->toBe('Mutuelle Équipe · MX-CONNECT Mutuelle')->and($m['id'])->toBe('/?app=mx-mutuelle')
        ->and($m['start_url'])->toBe('/?source=pwa')->and($m['scope'])->toBe('/')->and($m['display'])->toBe('standalone');
    $this->get($host . '/connexion')->assertOk()->assertSee('/app-mutuelle.webmanifest', false);

    $device = str_repeat('cd', 16);
    $this->postJson($host . '/installer/evenement', ['event' => 'installed', 'os' => 'windows', 'browser' => 'edge', 'device' => $device])->assertNoContent();
    tenancy()->end();
    expect(AppCenterInstall::where('app', 'mutuelle')->value('mutual_id'))->toBe($mutual->id);

    // Depuis l'App Center : choix de la mutuelle → page d'installation de SON sous-domaine.
    $central = rtrim(config('app.url'), '/');   // après une requête vers la mutuelle, les adresses relatives visent son sous-domaine
    $port = parse_url((string) config('app.url'), PHP_URL_PORT);
    $target = $host . ($port ? ':' . $port : '');   // même port que le site principal (copie locale : 8080)
    $this->post($central . '/applications/mutuelle', ['mutuelle' => $mutual->slug, 'appareil' => 'android'])
        ->assertRedirect($target . '/installer?appareil=android');
    $this->post($central . '/applications/mutuelle', ['mutuelle' => 'Mutuelle Équipe', 'appareil' => 'pc'])
        ->assertRedirect($target . '/installer?appareil=pc');
    $this->post($central . '/applications/mutuelle', ['mutuelle' => 'inconnue'])->assertSessionHasErrors('mutuelle');

    AppCenterApp::where('key', 'mutuelle')->update(['enabled' => false]);
    AppCenter::forget();
    $this->get($host . '/installer')->assertNotFound();
    $this->get($host . '/app-mutuelle.webmanifest')->assertNotFound();
});

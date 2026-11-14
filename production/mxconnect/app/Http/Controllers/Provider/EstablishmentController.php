<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Central\CareProvider;
use App\Models\Provider\ProviderAccount;
use App\Models\Provider\ProviderDocument;
use App\Models\Provider\ProviderMembership;
use App\Support\ProviderAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion de l'établissement dans l'espace prestataire : fiche, coordonnées bancaires
 * (chiffrées, droit « bank.edit »), personnel et droits, agréments et documents (PDF, disque
 * privé, téléchargement forcé). Toujours limité à l'établissement en cours.
 */
class EstablishmentController extends Controller
{
    public const DAYS = ['mon' => 'Lundi', 'tue' => 'Mardi', 'wed' => 'Mercredi', 'thu' => 'Jeudi', 'fri' => 'Vendredi', 'sat' => 'Samedi', 'sun' => 'Dimanche'];
    private const DOC_MAX_KB = 5120;

    // --- Fiche de l'établissement ---

    public function show()
    {
        $m = ProviderAccess::current();

        return view('provider.profile', [
            'p' => $m->provider, 'canEdit' => ProviderAccess::allowed('profile.edit'), 'canBank' => ProviderAccess::allowed('bank.edit'),
            'bank' => ProviderAccess::allowed('bank.edit') ? (array) ($m->provider->bank_details ?? []) : [],
            'days' => self::DAYS,
        ]);
    }

    public function update(Request $request)
    {
        $p = ProviderAccess::current()->provider;
        $data = $request->validate([
            'name'                => ['required', 'string', 'min:3', 'max:180'],
            'type'                => ['required', Rule::in(array_keys(CareProvider::TYPES))],
            'approval_number'     => ['nullable', 'string', 'max:60'],
            'approval_expires_on' => ['nullable', 'date'],
            'specialties'         => ['nullable', 'string', 'max:500'],
            'locality'            => ['nullable', 'string', 'max:120'],
            'address'             => ['nullable', 'string', 'max:255'],
            'latitude'            => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'           => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'phone2'              => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:180'],
            'manager_name'        => ['nullable', 'string', 'max:120'],
            'hours'               => ['array'],
            'hours.*'             => ['nullable', 'string', 'max:40'],
        ], ['name.required' => 'Indiquez le nom de l’établissement.', 'latitude.required_with' => 'Indiquez la latitude ET la longitude.', 'longitude.required_with' => 'Indiquez la latitude ET la longitude.']);

        $hours = collect($data['hours'] ?? [])->only(array_keys(self::DAYS))->map(fn ($v) => trim((string) $v))->filter()->all();
        $specialties = collect(preg_split('/[,;\n]+/', (string) ($data['specialties'] ?? '')))->map(fn ($s) => trim($s))->filter()->unique()->take(20)->values()->all();
        $p->update(collect($data)->except(['hours', 'specialties'])->all() + ['opening_hours' => $hours ?: null, 'specialties' => $specialties ?: null]);

        return back()->with('success', 'Fiche de l’établissement enregistrée.');
    }

    public function bank(Request $request)
    {
        $p = ProviderAccess::current()->provider;
        $data = $request->validate([
            'bank_name'      => ['nullable', 'string', 'max:120'],
            'account_holder' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'swift'          => ['nullable', 'string', 'max:20'],
            'mobile_money'   => ['nullable', 'string', 'max:30'],
        ]);
        $clean = array_filter(array_map(fn ($v) => $v === null ? null : trim($v), $data));
        $p->forceFill(['bank_details' => $clean ?: null])->save();
        // Le journal d'audit ne garde pas les coordonnées (chiffrées) : on note seulement le fait.
        \Illuminate\Support\Facades\Log::info('provider.bank_updated', ['care_provider_id' => $p->id, 'by' => $request->user('provider')->id]);

        return back()->with('success', 'Coordonnées bancaires enregistrées (stockées chiffrées). Elles servent aux paiements faits par les mutuelles, hors plateforme.');
    }

    // --- Personnel et droits ---

    public function staff()
    {
        $m = ProviderAccess::current();

        return view('provider.staff', [
            'members' => ProviderMembership::with('account')->where('care_provider_id', $m->care_provider_id)->orderByDesc('active')->orderBy('role')->get(),
            'me' => $m,
        ]);
    }

    public function storeStaff(Request $request)
    {
        $m = ProviderAccess::current();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180'], 'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys(ProviderAccess::ROLES))], 'job_title' => ['nullable', 'string', 'max:120'],
        ], ['email.required' => 'Indiquez l’adresse e-mail de la personne (elle sert d’identifiant).']);
        $email = Str::lower(trim($data['email']));

        $existing = ProviderAccount::where('email', $email)->first();
        if ($existing && ProviderMembership::where('provider_account_id', $existing->id)->where('care_provider_id', $m->care_provider_id)->exists()) {
            return back()->withInput()->withErrors(['email' => 'Cette personne fait déjà partie du personnel de l’établissement.']);
        }

        $temp = null;
        DB::connection('central')->transaction(function () use ($data, $email, $existing, $m, $request, &$temp) {
            $account = $existing;
            if (! $account) {
                $temp = self::temporaryPassword();
                $account = ProviderAccount::create([
                    'first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']), 'email' => $email,
                    'phone' => $data['phone'] ?? null, 'password' => $temp, 'must_change_password' => true, 'active' => true,
                ]);
            }
            ProviderMembership::create(['provider_account_id' => $account->id, 'care_provider_id' => $m->care_provider_id,
                'role' => $data['role'], 'job_title' => $data['job_title'] ?? null, 'active' => true, 'created_by' => $request->user('provider')->id]);
        });

        return back()->with('success', $existing
            ? 'Cette personne avait déjà un compte MX-CONNECT prestataire : elle est rattachée à l’établissement et se connecte avec son mot de passe habituel.'
            : 'Compte créé. Transmettez le mot de passe provisoire à la personne : il ne sera plus affiché.')
            ->with('temp_password', $temp ? ['email' => $email, 'password' => $temp] : null);
    }

    public function updateStaff(Request $request, int $membership)
    {
        $m = ProviderAccess::current();
        $target = ProviderMembership::where('care_provider_id', $m->care_provider_id)->findOrFail($membership);
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(ProviderAccess::ROLES))], 'job_title' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'], 'perms' => ['array'], 'perms.*' => ['string', Rule::in(ProviderAccess::all())],
        ]);
        $active = $request->boolean('active');

        // L'établissement garde toujours au moins un administrateur actif.
        $losesAdmin = $target->role === 'admin' && $target->active && ($data['role'] !== 'admin' || ! $active);
        if ($losesAdmin && ProviderMembership::where('care_provider_id', $m->care_provider_id)->where('role', 'admin')->where('active', true)->count() <= 1) {
            return back()->with('error', 'L’établissement doit garder au moins un administrateur actif : nommez-en un autre d’abord.');
        }
        // Les cases cochées sont comparées aux droits du NOUVEAU rôle (écarts enregistrés).
        $overrides = $request->has('perms_sent')
            ? ProviderAccess::overridesFor($data['role'], (array) ($data['perms'] ?? []))
            : (array) ($target->permissions ?? []);
        $target->update(['role' => $data['role'], 'job_title' => $data['job_title'] ?? null, 'active' => $active,
            'permissions' => array_filter($overrides) ? $overrides : null]);

        return back()->with('success', 'Droits de ' . ($target->account?->name ?? 'la personne') . ' enregistrés.');
    }

    public function resetStaff(Request $request, int $membership)
    {
        $m = ProviderAccess::current();
        $target = ProviderMembership::with('account')->where('care_provider_id', $m->care_provider_id)->findOrFail($membership);
        $account = $target->account;
        abort_unless($account, 404);
        // Un compte rattaché à d'autres établissements n'est pas réinitialisé d'ici (il ne nous appartient pas seul).
        if (ProviderMembership::where('provider_account_id', $account->id)->where('care_provider_id', '!=', $m->care_provider_id)->exists()) {
            return back()->with('error', 'Ce compte est aussi utilisé dans un autre établissement : la personne utilise « Mot de passe oublié » sur la page de connexion.');
        }
        if ($account->id === $request->user('provider')->id) {
            return back()->with('error', 'Pour votre propre compte, utilisez « Paramètres ».');
        }
        $temp = self::temporaryPassword();
        $account->forceFill(['password' => $temp, 'must_change_password' => true, 'mfa_enabled' => false, 'mfa_secret' => null, 'remember_token' => Str::random(60)])->save();

        return back()->with('success', 'Mot de passe et double authentification réinitialisés. Transmettez le mot de passe provisoire : il ne sera plus affiché.')
            ->with('temp_password', ['email' => $account->email, 'password' => $temp]);
    }

    public static function temporaryPassword(): string
    {
        // Lisible au téléphone (sans 0/O, 1/l), avec lettres et chiffres.
        $alpha = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $pw = '';
        for ($i = 0; $i < 8; $i++) {
            $pw .= $alpha[random_int(0, strlen($alpha) - 1)];
        }

        return $pw . '-' . random_int(1000, 9999);
    }

    // --- Agréments et documents ---

    public function documents()
    {
        $m = ProviderAccess::current();

        return view('provider.documents', [
            'docs' => ProviderDocument::where('care_provider_id', $m->care_provider_id)->orderByRaw('expires_on is null')->orderBy('expires_on')->latest('id')->get(),
            'mutuals' => app(\App\Services\Provider\ProviderSpaceService::class)->affiliations($m->provider)->pluck('mutual')->filter()->keyBy('id'),
            'canManage' => ProviderAccess::allowed('documents.manage'),
        ]);
    }

    public function storeDocument(Request $request)
    {
        $m = ProviderAccess::current();
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ProviderDocument::KINDS))], 'title' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:80'], 'issued_on' => ['nullable', 'date'], 'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'mutual_id' => ['nullable', 'string', 'max:64'],
            'file' => ['required', 'file', 'max:' . self::DOC_MAX_KB, 'mimetypes:application/pdf,application/x-pdf'],
        ], [
            'file.required' => 'Choisissez le fichier PDF.', 'file.max' => 'Le document ne doit pas dépasser 5 Mo.',
            'file.mimetypes' => 'Le document doit être un fichier PDF.', 'title.required' => 'Donnez un titre au document.',
            'expires_on.after_or_equal' => 'La date d’expiration doit suivre la date de délivrance.',
        ]);
        $fh = fopen($request->file('file')->getRealPath(), 'rb');
        $head = $fh ? fread($fh, 5) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($head !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'Ce fichier n’est pas un PDF valide.']);
        }
        $mutualId = ! empty($data['mutual_id']) && \App\Models\Provider\ProviderAffiliation::where('care_provider_id', $m->care_provider_id)->where('mutual_id', $data['mutual_id'])->exists()
            ? $data['mutual_id'] : null;
        $path = $request->file('file')->storeAs('provider-documents/' . $m->care_provider_id, Str::uuid() . '.pdf', 'local');

        ProviderDocument::create([
            'care_provider_id' => $m->care_provider_id, 'kind' => $data['kind'], 'title' => trim($data['title']), 'reference' => $data['reference'] ?? null,
            'issued_on' => $data['issued_on'] ?? null, 'expires_on' => $data['expires_on'] ?? null, 'mutual_id' => $mutualId,
            'path' => $path, 'original_name' => Str::limit($request->file('file')->getClientOriginalName(), 170, ''),
            'size' => (int) $request->file('file')->getSize(), 'uploaded_by' => $request->user('provider')->id,
        ]);
        // L'agrément porte une date d'expiration : on la reporte sur la fiche (alertes du tableau de bord).
        if ($data['kind'] === 'approval' && ! empty($data['expires_on'])) {
            $m->provider->update(['approval_expires_on' => $data['expires_on']] + (! empty($data['reference']) ? ['approval_number' => $data['reference']] : []));
        }

        return back()->with('success', 'Document enregistré.');
    }

    public function download(int $document)
    {
        $doc = ProviderDocument::where('care_provider_id', ProviderAccess::current()->care_provider_id)->findOrFail($document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($doc->path), 404);

        return response()->download($disk->path($doc->path), Str::slug($doc->title) . '.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox',
        ])->setPrivate()->setCache(['no_store' => true]);
    }

    public function destroyDocument(int $document)
    {
        $doc = ProviderDocument::where('care_provider_id', ProviderAccess::current()->care_provider_id)->findOrFail($document);
        $doc->delete();   // suppression logique : l'historique reste consultable par l'audit, le fichier est conservé

        return back()->with('success', 'Document retiré de la liste (conservé dans l’historique).');
    }
}

<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Social\BetaFeedback;
use App\Models\Social\LegalDocument;
use App\Models\Social\SocialSetting;
use App\Services\Social\LegalService;
use App\Support\AndroidApp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Espace super-admin — application Android (2026-11-09) :
 *  - documents légaux versionnés : brouillon modifiable, publication (définitive), archivage
 *    automatique de la version précédente ; une version publiée n'est jamais modifiée ;
 *  - retours des testeurs de la bêta ;
 *  - réglages de l'application : version minimale, maintenance, lien de téléchargement,
 *    empreintes du certificat (publiques) pour la liaison du domaine.
 */
class AndroidAdminController extends Controller
{
    private const EDITORS = ['super_admin'];
    private const READERS = ['super_admin', 'moderator'];

    public function legal(Request $request)
    {
        $this->allow($request, self::EDITORS);

        return view('network.android.legal', [
            'documents' => LegalService::ready() ? LegalDocument::orderBy('type')->orderByDesc('id')->get()->groupBy('type') : collect(),
            'edit' => $request->integer('modifier') ? LegalDocument::where('status', 'draft')->find($request->integer('modifier')) : null,
            'base' => $request->filled('type') ? LegalDocument::where('type', $request->get('type'))->orderByDesc('id')->first() : null,
        ]);
    }

    /** Nouvelle version (brouillon) ou modification d'un brouillon. */
    public function saveLegal(Request $request)
    {
        $this->allow($request, self::EDITORS);
        $draft = $request->integer('id') ? LegalDocument::where('status', 'draft')->findOrFail($request->integer('id')) : null;
        $data = $request->validate([
            'type'    => ['required', 'regex:/^(terms|privacy|community|consent_[a-z_]{2,30})$/'],
            'version' => ['required', 'regex:/^[0-9A-Za-z.\-]{1,20}$/', Rule::unique('central.legal_documents')->where('type', $request->input('type'))->ignore($draft?->id)],
            'title'   => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body'    => ['required', 'string', 'min:20', 'max:60000'],
            'requires_reacceptance' => ['nullable', 'boolean'],
        ], ['version.unique' => 'Cette version existe déjà pour ce document : choisissez un nouveau numéro.']);
        $fields = [
            'type' => $data['type'], 'version' => $data['version'], 'title' => $data['title'], 'summary' => $data['summary'] ?? null, 'body' => $data['body'],
            'action' => LegalDocument::TYPES[$data['type']][1] ?? 'consent',
            'requires_reacceptance' => $request->boolean('requires_reacceptance'),
            'is_template' => str_contains($data['body'], 'À COMPLÉTER') || str_contains($data['body'], 'MODÈLE'),
        ];
        $draft ? $draft->update($fields) : LegalDocument::create($fields + ['status' => 'draft', 'created_by' => $request->user()->id]);

        return redirect()->route('network.android.legal')->with('success', 'Brouillon enregistré. Il n’est visible des utilisateurs qu’après publication.');
    }

    /** Publication : définitive ; la version publiée précédente du même document est archivée. */
    public function publish(Request $request, LegalDocument $document)
    {
        $this->allow($request, self::EDITORS);
        abort_unless($document->status === 'draft', 422, 'Seul un brouillon peut être publié.');
        if ($document->is_template && ! $request->boolean('confirm_template')) {
            return back()->with('error', 'Ce texte contient encore des parties « À COMPLÉTER » ou la mention « MODÈLE ». Complétez-le, ou cochez la confirmation pour le publier malgré tout.');
        }
        LegalDocument::where('type', $document->type)->where('status', 'published')->update(['status' => 'archived']);
        $document->update(['status' => 'published', 'published_at' => now()]);

        return redirect()->route('network.android.legal')->with('success', "{$document->label()} version {$document->version} publiée."
            . ($document->requires_reacceptance ? ' Tous les comptes devront la valider à leur prochaine action.' : ' Elle s’applique aux nouveaux comptes ; les comptes existants ne sont pas bloqués.'));
    }

    public function feedback(Request $request)
    {
        $this->allow($request, self::READERS);

        return view('network.android.feedback', [
            'items' => LegalService::ready() ? BetaFeedback::with('account')->latest('id')->paginate(30) : collect(),
        ]);
    }

    public function feedbackStatus(Request $request, BetaFeedback $feedback)
    {
        $this->allow($request, self::READERS);
        $feedback->update($request->validate(['status' => ['required', Rule::in(array_keys(BetaFeedback::STATUSES))]]));

        return back()->with('success', 'Retour mis à jour.');
    }

    public function settings(Request $request)
    {
        $this->allow($request, self::EDITORS);

        return view('network.android.settings', [
            'package' => AndroidApp::package(), 'sha256' => implode("\n", AndroidApp::fingerprints()),
            'min' => AndroidApp::minVersion(), 'latest' => AndroidApp::latestVersion(), 'store' => AndroidApp::storeUrl(),
            'maintenance' => SocialSetting::get('android_maintenance') === '1', 'message' => SocialSetting::get('android_maintenance_message'),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->allow($request, self::EDITORS);
        $data = $request->validate([
            'package' => ['required', 'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/'],
            'sha256'  => ['nullable', 'string', 'max:2000'],
            'min'     => ['required', 'integer', 'min:1', 'max:2100000000'],
            'latest'  => ['required', 'integer', 'gte:min', 'max:2100000000'],
            'store'   => ['nullable', 'url', 'max:300'],
            'message' => ['nullable', 'string', 'max:300'],
        ], ['latest.gte' => 'La dernière version doit être supérieure ou égale à la version minimale.']);
        $prints = array_filter(preg_split('/[\s,;]+/', strtoupper((string) ($data['sha256'] ?? ''))));
        foreach ($prints as $p) {
            if (! preg_match('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $p)) {
                return back()->withInput()->withErrors(['sha256' => "Empreinte invalide : {$p}. Format attendu : 32 octets en hexadécimal séparés par « : »."]);
            }
        }
        SocialSetting::put('android_package', $data['package']);
        SocialSetting::put('android_sha256', implode(',', $prints) ?: null);
        SocialSetting::put('android_min_version', $data['min']);
        SocialSetting::put('android_latest_version', $data['latest']);
        SocialSetting::put('android_store_url', $data['store'] ?? null);
        SocialSetting::put('android_maintenance', $request->boolean('maintenance') ? '1' : '0');
        SocialSetting::put('android_maintenance_message', $data['message'] ?? null);

        return back()->with('success', 'Réglages de l’application enregistrés.');
    }

    private function allow(Request $request, array $roles): void
    {
        abort_unless($request->user()?->hasAnyRole($roles), 403);
    }
}

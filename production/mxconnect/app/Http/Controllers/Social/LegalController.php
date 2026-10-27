<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\LegalAcceptance;
use App\Models\Social\LegalDocument;
use App\Services\Social\LegalService;
use Illuminate\Http\Request;

/**
 * Documents légaux versionnés (2026-11-09) : consultation de chaque version, écran
 * « Conditions d'utilisation et confidentialité » (maquette 5), historique personnel.
 */
class LegalController extends Controller
{
    public function __construct(private LegalService $legal) {}

    /** Un document : version en vigueur, ou version demandée ; liste des versions publiées. */
    public function show(string $type, ?string $version = null)
    {
        abort_unless(LegalService::ready(), 404);
        $doc = $version
            ? LegalDocument::where('type', $type)->where('version', $version)->where('status', '!=', 'draft')->firstOrFail()
            : $this->legal->currentOf($type);
        abort_unless($doc, 404);

        return view('social.legal.show', [
            'doc' => $doc,
            'current' => $this->legal->currentOf($type),
            'versions' => LegalDocument::where('type', $type)->where('status', '!=', 'draft')->whereNotNull('published_at')->orderByDesc('published_at')->get(),
        ]);
    }

    /** Écran de validation (inscription, nouvelle version, ou depuis « Mon profil »). */
    public function pending(Request $request)
    {
        $account = auth('member')->user();
        $pending = $this->legal->pendingFor($account);
        if ($pending->isEmpty() && ! $request->boolean('voir')) {
            return redirect()->to($this->after($request));
        }

        return view('social.legal.pending', [
            'documents' => $this->legal->current(),
            'pending' => $pending->pluck('id')->all(),
            'accepted' => $this->legal->acceptedMap($account),
            'app' => $request->boolean('app'),
            'next' => $request->query('suite'),
        ]);
    }

    public function accept(Request $request)
    {
        $account = auth('member')->user();
        $pending = $this->legal->pendingFor($account);
        $rules = [];
        foreach ($pending as $d) {
            $rules['doc_' . $d->id] = ['accepted'];
        }
        $request->validate($rules, collect($pending)->mapWithKeys(fn ($d) => ['doc_' . $d->id . '.accepted' => 'Cochez la case « ' . $d->checkboxText() . ' » pour continuer.'])->all());
        $this->legal->record($account, $pending, $request);

        return redirect()->to($this->after($request))->with('ok', $pending->isNotEmpty() ? 'Merci, vos choix sont enregistrés.' : null);
    }

    /** Historique des acceptations du compte (Paramètres et confidentialité). */
    public function history()
    {
        $account = auth('member')->user();

        return view('social.legal.history', [
            'rows' => LegalService::ready() ? LegalAcceptance::with('document')->where('public_account_id', $account->id)->orderByDesc('id')->get() : collect(),
            'documents' => $this->legal->current(),
            'pending' => $this->legal->pendingFor($account)->pluck('id')->all(),
        ]);
    }

    private function after(Request $request): string
    {
        $next = (string) ($request->input('suite') ?: $request->query('suite'));
        if ($next !== '' && str_starts_with($next, '/') && ! str_starts_with($next, '//')) {
            return $next;
        }

        return route('social.home');
    }
}

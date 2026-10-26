<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Mutual;
use App\Models\Central\MutualOffer;
use App\Models\Social\AdhesionDocument;
use App\Models\Social\AdhesionRequest;
use App\Models\Social\Conversation;
use App\Services\Social\AdhesionService;
use App\Services\Social\FeedService;
use App\Services\Social\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Demande d'adhésion en ligne (côté membre). Suivre une mutuelle ne crée
 * jamais de demande : c'est une démarche explicite, avec consentement.
 * Aucune donnée médicale n'est demandée. Aucun paiement n'est déclenché.
 */
class AdhesionController extends SocialController
{
    public function __construct(FeedService $feed, private AdhesionService $adhesions, private MessagingService $messaging)
    {
        parent::__construct($feed);
    }

    public function create(string $slug)
    {
        $mutual = $this->eligibleMutual($slug);
        $viewer = $this->viewer();
        $open = AdhesionRequest::where('mutual_id', $mutual->id)->where('public_account_id', $viewer->id)
            ->whereIn('status', [...AdhesionRequest::OPEN, 'accepted'])->latest()->first();
        if ($open) {
            return redirect()->route('social.adhesion', $open)->with('info', $open->status === 'accepted'
                ? 'Votre adhésion à cette mutuelle a déjà été acceptée.'
                : 'Vous avez déjà une demande en cours auprès de cette mutuelle.');
        }

        return view('social.adhesion.create', [
            'mutual' => $mutual, 'account' => $viewer,
            'offer' => MutualOffer::where('mutual_id', $mutual->id)->where('published', true)->with('currency')->first(),
        ]);
    }

    public function store(Request $request, string $slug)
    {
        $mutual = $this->eligibleMutual($slug);
        $docKb = (int) config('mxconnect.social.adhesion_doc_kb', 5120);
        $request->merge(['phone' => AuthController::normalizePhone($request->input('phone'))]);

        $data = $request->validate([
            'first_name'         => ['required', 'string', 'max:120'],
            'last_name'          => ['required', 'string', 'max:120'],
            'birth_date'         => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'sex'                => ['required', Rule::in(['M', 'F'])],
            'phone'              => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
            'email'              => ['nullable', 'email:rfc', 'max:180'],
            'id_document_number' => ['nullable', 'string', 'max:60'],
            'city'               => ['required', 'string', 'max:120'],
            'address'            => ['nullable', 'string', 'max:255'],
            'profession'         => ['nullable', 'string', 'max:120'],
            'household_size'     => ['required', 'integer', 'min:1', 'max:30'],
            'beneficiaries'                => ['nullable', 'array', 'max:10'],
            'beneficiaries.*.name'         => ['nullable', 'string', 'max:180'],
            'beneficiaries.*.relation'     => ['nullable', Rule::in(array_keys(AdhesionRequest::RELATIONS))],
            'beneficiaries.*.birth_date'   => ['nullable', 'date', 'before:today'],
            'formula'            => ['nullable', 'string', 'max:180'],
            'message'            => ['nullable', 'string', 'max:2000'],
            'documents'          => ['nullable', 'array', 'max:' . config('mxconnect.social.adhesion_max_docs', 4)],
            'documents.*'        => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:' . $docKb],
            'kinds'              => ['nullable', 'array'],
            'kinds.*'            => ['nullable', Rule::in(array_keys(AdhesionRequest::DOC_KINDS))],
            'consent'            => ['accepted'],
            'no_medical'         => ['accepted'],
        ], [
            'phone.regex'         => 'Numéro de téléphone invalide.',
            'documents.*.mimes'   => 'Pièces acceptées : PDF ou image (JPEG, PNG, WebP).',
            'documents.*.max'     => 'Chaque pièce doit faire moins de ' . round($docKb / 1024) . ' Mo.',
            'consent.accepted'    => 'Votre accord est nécessaire pour transmettre le dossier à la mutuelle.',
            'no_medical.accepted' => 'Merci de confirmer que vous n’avez joint aucune donnée médicale.',
        ]);

        $beneficiaries = collect($data['beneficiaries'] ?? [])
            ->filter(fn ($b) => trim((string) ($b['name'] ?? '')) !== '')
            ->map(fn ($b) => ['name' => trim($b['name']), 'relation' => $b['relation'] ?? 'autre', 'birth_date' => $b['birth_date'] ?? null])
            ->values()->all();

        $fields = collect($data)->except(['beneficiaries', 'documents', 'kinds', 'consent', 'no_medical'])->all();
        $fields['beneficiaries'] = $beneficiaries ?: null;

        $r = $this->adhesions->submit($mutual, $this->viewer(), $fields, $request->file('documents', []), $data['kinds'] ?? []);

        return redirect()->route('social.adhesion', $r)
            ->with('ok', "Votre demande a été transmise à {$mutual->name} (référence {$r->reference}). Vous serez notifié de chaque étape.");
    }

    public function index()
    {
        $requests = AdhesionRequest::where('public_account_id', $this->viewer()->id)->with('mutual.profile')->latest()->get();

        return view('social.adhesion.index', compact('requests'));
    }

    public function show(AdhesionRequest $adhesion)
    {
        $this->own($adhesion);
        $adhesion->load(['mutual.profile', 'documents', 'events', 'conversation']);

        return view('social.adhesion.show', ['r' => $adhesion]);
    }

    public function withdraw(AdhesionRequest $adhesion)
    {
        $this->own($adhesion);
        $this->adhesions->withdraw($adhesion);

        return redirect()->route('social.adhesion', $adhesion)->with('ok', 'Votre demande a été retirée.');
    }

    /** Réponse à une demande d'information : note + pièces complémentaires. */
    public function complete(Request $request, AdhesionRequest $adhesion)
    {
        $this->own($adhesion);
        abort_unless($adhesion->status === 'info_requested', 422);
        $data = $request->validate([
            'note'        => ['required', 'string', 'max:2000'],
            'documents'   => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:' . config('mxconnect.social.adhesion_doc_kb', 5120)],
            'kinds'       => ['nullable', 'array'],
            'kinds.*'     => ['nullable', Rule::in(array_keys(AdhesionRequest::DOC_KINDS))],
        ]);
        $this->adhesions->addDocuments($adhesion, $request->file('documents', []), $data['kinds'] ?? []);
        $this->adhesions->resubmit($adhesion, $data['note']);

        return redirect()->route('social.adhesion', $adhesion)->with('ok', 'Complément envoyé à la mutuelle.');
    }

    /** Téléchargement d'une pièce : uniquement par le demandeur. */
    public function document(AdhesionRequest $adhesion, int $document)
    {
        $this->own($adhesion);
        $doc = AdhesionDocument::where('adhesion_request_id', $adhesion->id)->findOrFail($document);

        return self::download($doc);
    }

    /** Écrire à la mutuelle au sujet de ce dossier (une conversation par dossier). */
    public function message(Request $request, AdhesionRequest $adhesion)
    {
        $this->own($adhesion);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $conv = Conversation::where('adhesion_request_id', $adhesion->id)->first();
        if ($conv) {
            $this->messaging->postFromAccount($conv, $data['body']);
        } else {
            $conv = $this->messaging->start($adhesion->mutual, $this->viewer(),
                'Demande d’adhésion ' . $adhesion->reference, $data['body'], $adhesion->id);
        }

        return redirect()->route('social.messages.show', $conv)->with('ok', 'Message envoyé.');
    }

    /** Réponse de fichier privé, jamais mise en cache, jamais affichée « en ligne » pour les PDF. */
    public static function download(AdhesionDocument $doc)
    {
        $disk = Storage::disk('social_private');
        abort_unless($disk->exists($doc->path), 404);

        // Le nom proposé suit le format réel du fichier (ex. une photo PNG devenue WebP).
        $ext = pathinfo($doc->path, PATHINFO_EXTENSION);
        $name = $doc->original_name ? pathinfo($doc->original_name, PATHINFO_FILENAME) . '.' . $ext : basename($doc->path);

        // setPrivate() : sans lui, la réponse de téléchargement est marquée « public » (cache partagé possible).
        return response()->download($disk->path($doc->path), $name, [
            'Content-Type' => $doc->mime, 'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate()->setCache(['no_store' => true]);
    }

    private function own(AdhesionRequest $r): void
    {
        abort_unless($r->public_account_id === $this->viewer()->id, 404);
    }

    private function eligibleMutual(string $slug): Mutual
    {
        $mutual = Mutual::where('slug', $slug)->with('profile')->firstOrFail();
        abort_if($mutual->status === 'suspended', 404);
        abort_if($mutual->profile?->is_demo, 403, 'Organisation de démonstration : l’adhésion en ligne n’est pas possible.');
        abort_if($mutual->profile?->owner_account_id, 403, 'Cette page n’est pas une mutuelle gérée sur MX-CONNECT : l’adhésion en ligne n’est pas possible.');

        return $mutual;
    }
}

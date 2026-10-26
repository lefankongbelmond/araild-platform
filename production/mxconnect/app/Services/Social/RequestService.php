<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Opportunity;
use App\Models\Social\PageAdmin;
use App\Models\Social\Project;
use App\Models\Social\SocialRequest;
use App\Models\Social\SocialRequestEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Demandes adressées à une page : « Je m'engage », « Soutenir ou devenir partenaire »,
 * candidatures aux opportunités. Règles :
 *  - une seule demande ouverte par personne et par sujet (projet, offre ou organisation) ;
 *  - jamais vers sa propre page, ni vers une organisation de démonstration ;
 *  - CV : PDF uniquement, sur le disque PRIVÉ, lisible par le candidat et les gestionnaires habilités ;
 *  - chaque changement de statut est historisé et notifié ;
 *  - aucun paiement : un soutien financier se règle directement avec l'organisation.
 */
class RequestService
{
    public const TRANSITIONS = [
        'submitted' => ['in_review', 'accepted', 'rejected'],
        'in_review' => ['accepted', 'rejected'],
        'accepted'  => ['closed'],
    ];

    public function __construct(private NotificationService $notifications, private MailService $mail) {}

    /** Comptes habilités à lire les demandes d'une page : propriétaire et administrateurs (pas les éditeurs). */
    public static function inboxManagerIds(string $mutualId): array
    {
        $ids = \App\Models\Social\MutualProfile::whereKey($mutualId)->whereNotNull('owner_account_id')->pluck('owner_account_id')->all();
        $ids = array_merge($ids, PageAdmin::where('mutual_id', $mutualId)->where('role', 'admin')->pluck('public_account_id')->all());

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param array{subtype?:string,message:string,organisation_name?:string,availability?:string,contact_name:string,contact_phone?:string,contact_email?:string} $data
     */
    public function submit(string $kind, Mutual $page, PublicAccount $account, array $data,
                           ?Project $project = null, ?Opportunity $opportunity = null, ?UploadedFile $cv = null): SocialRequest
    {
        abort_unless(array_key_exists($kind, SocialRequest::KINDS), 422);
        abort_if($page->status === 'suspended', 404);
        if ($page->profile?->is_demo) {
            throw ValidationException::withMessages(['message' => 'Organisation de démonstration : aucune demande ne peut lui être envoyée.']);
        }
        if (PageService::canPublish($account, $page)) {
            throw ValidationException::withMessages(['message' => 'Vous gérez cette page : vous ne pouvez pas lui adresser une demande.']);
        }
        if ($kind === 'application') {
            if (! $opportunity || ! $opportunity->isPublic() || ! $opportunity->isOpen()) {
                throw ValidationException::withMessages(['message' => 'Cette offre n’accepte plus de candidatures.']);
            }
            if (! $opportunity->acceptsPlatform()) {
                throw ValidationException::withMessages(['message' => 'Pour cette offre, la candidature se fait sur le site indiqué par l’organisation.']);
            }
        }

        $duplicate = SocialRequest::where('public_account_id', $account->id)->where('mutual_id', $page->id)->where('kind', $kind)
            ->where('project_id', $project?->id)->where('opportunity_id', $opportunity?->id)
            ->whereIn('status', SocialRequest::OPEN)->first();
        if ($duplicate) {
            throw ValidationException::withMessages(['message' => "Vous avez déjà une demande en cours sur ce sujet (référence {$duplicate->reference})."]);
        }

        $r = DB::connection('central')->transaction(function () use ($kind, $page, $account, $data, $project, $opportunity) {
            $r = SocialRequest::create([
                'reference' => SocialRequest::newReference($kind), 'kind' => $kind,
                'mutual_id' => $page->id, 'public_account_id' => $account->id,
                'project_id' => $project?->id, 'opportunity_id' => $opportunity?->id,
                'subtype' => $data['subtype'] ?? null,
                'organisation_name' => $data['organisation_name'] ?? null,
                'availability' => $data['availability'] ?? null,
                'message' => trim($data['message']),
                'contact_name' => trim($data['contact_name']),
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'status' => 'submitted', 'consent_at' => now(),
            ]);
            SocialRequestEvent::create(['social_request_id' => $r->id, 'actor' => 'requester', 'actor_label' => $account->name, 'to_status' => 'submitted']);
            if ($opportunity) {
                Opportunity::whereKey($opportunity->id)->increment('requests_count');
            }

            return $r;
        });

        if ($cv) {
            try {
                $this->storeCv($r, $cv);
            } catch (\Throwable $e) {
                $this->purge($r);
                throw $e;
            }
        }

        // Prévenir la page : notification des gestionnaires habilités + e-mail d'alerte des mutuelles.
        $label = $r->kindLabel();
        $about = $opportunity?->title ?? $project?->title;
        foreach (self::inboxManagerIds($page->id) as $id) {
            $this->notifications->notify($id, 'demande', "{$label} reçue pour {$page->name}",
                $about ? "Sujet : {$about}" : null, route('social.manage.request', [$page->slug, $r->reference]));
        }
        if (! $page->isMemberPage()) {
            $this->mail->alertMutual($page, "{$label} reçue sur le réseau",
                "Une nouvelle demande (référence {$r->reference}) attend votre réponse.", '/projets-opportunites/demandes/' . $r->reference);
        }

        return $r;
    }

    /** Changement de statut par la page (vérifie la transition). */
    public function decide(SocialRequest $r, string $to, ?string $note, string $actorLabel): void
    {
        abort_unless(in_array($to, self::TRANSITIONS[$r->status] ?? [], true), 422, 'Changement de statut non autorisé.');

        DB::connection('central')->transaction(function () use ($r, $to, $note, $actorLabel) {
            $from = $r->status;
            $r->update(['status' => $to, 'decided_at' => in_array($to, ['accepted', 'rejected'], true) ? now() : $r->decided_at]);
            SocialRequestEvent::create(['social_request_id' => $r->id, 'actor' => 'organisation', 'actor_label' => $actorLabel,
                'from_status' => $from, 'to_status' => $to, 'note' => $note ? trim($note) : null]);
        });

        $name = $r->mutual?->name ?? 'L’organisation';
        $this->notifications->notify($r->public_account_id, 'demande',
            "{$r->kindLabel()} auprès de {$name} : " . mb_strtolower(SocialRequest::STATUSES[$to]),
            $note ? Str::limit(trim($note), 200) : null, route('social.request', $r->reference));
    }

    public function withdraw(SocialRequest $r): void
    {
        abort_unless($r->isOpen(), 422);
        $from = $r->status;
        $r->update(['status' => 'withdrawn']);
        SocialRequestEvent::create(['social_request_id' => $r->id, 'actor' => 'requester', 'actor_label' => $r->account?->name,
            'from_status' => $from, 'to_status' => 'withdrawn']);
    }

    /** Téléchargement du CV : jamais mis en cache, jamais affiché « en ligne ». */
    public function cvResponse(SocialRequest $r)
    {
        $disk = Storage::disk('social_private');
        abort_unless($r->cv_path && $disk->exists($r->cv_path), 404);

        // setPrivate() : sans lui, la réponse de téléchargement est marquée « public » (cache partagé possible).
        return response()->download($disk->path($r->cv_path), $r->cv_name ?: 'cv.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate()->setCache(['no_store' => true]);
    }

    /** Suppression complète (fichier compris). */
    public function purge(SocialRequest $r): void
    {
        Storage::disk('social_private')->deleteDirectory("requests/{$r->mutual_id}/{$r->reference}");
        $r->delete();
    }

    private function storeCv(SocialRequest $r, UploadedFile $file): void
    {
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 5);
        if ($head !== '%PDF-') {
            throw ValidationException::withMessages(['cv' => 'Le CV doit être un fichier PDF valide.']);
        }
        $path = "requests/{$r->mutual_id}/{$r->reference}/" . Str::uuid() . '.pdf';
        Storage::disk('social_private')->put($path, file_get_contents($file->getRealPath()));
        $name = Str::limit(preg_replace('/[^\pL\pN ._()-]/u', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 170, '') . '.pdf';
        $r->update(['cv_path' => $path, 'cv_name' => $name]);
    }
}

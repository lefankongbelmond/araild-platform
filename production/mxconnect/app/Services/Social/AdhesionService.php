<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\AdhesionDocument;
use App\Models\Social\AdhesionEvent;
use App\Models\Social\AdhesionRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dossiers d'adhésion en ligne. Règles :
 *  - une seule demande ouverte par compte et par mutuelle ;
 *  - pièces stockées sur le disque PRIVÉ, images réencodées (métadonnées supprimées),
 *    PDF vérifiés (signature %PDF) ;
 *  - chaque changement de statut est historisé et notifié au demandeur ;
 *  - aucun paiement n'est déclenché ni simulé ici.
 */
class AdhesionService
{
    /** Transitions autorisées côté mutuelle. */
    public const TRANSITIONS = [
        'submitted'      => ['in_review', 'info_requested', 'accepted', 'rejected'],
        'in_review'      => ['info_requested', 'accepted', 'rejected'],
        'info_requested' => ['in_review', 'accepted', 'rejected'],
    ];

    public function __construct(private MediaService $media, private NotificationService $notifications, private MailService $mail) {}

    /** @param UploadedFile[] $files  @param array<int,string> $kinds */
    public function submit(Mutual $mutual, PublicAccount $account, array $data, array $files, array $kinds): AdhesionRequest
    {
        if (AdhesionRequest::where('mutual_id', $mutual->id)->where('public_account_id', $account->id)
            ->whereIn('status', [...AdhesionRequest::OPEN, 'accepted'])->exists()) {
            throw ValidationException::withMessages(['first_name' => 'Vous avez déjà une demande en cours (ou acceptée) auprès de cette mutuelle.']);
        }

        $request = DB::connection('central')->transaction(function () use ($mutual, $account, $data) {
            $r = AdhesionRequest::create($data + [
                'reference' => AdhesionRequest::newReference(),
                'mutual_id' => $mutual->id, 'public_account_id' => $account->id,
                'status' => 'submitted', 'consent_at' => now(),
            ]);
            AdhesionEvent::create(['adhesion_request_id' => $r->id, 'actor' => 'applicant',
                'actor_label' => $account->name, 'to_status' => 'submitted']);

            return $r;
        });

        try {
            $this->addDocuments($request, $files, $kinds);
        } catch (\Throwable $e) {
            $this->purge($request);
            throw $e;
        }

        $this->mail->alertMutual($mutual, 'Nouvelle demande d’adhésion en ligne',
            "Une nouvelle demande d’adhésion (référence {$request->reference}) attend votre étude.", '/adhesions-en-ligne/' . $request->reference);

        return $request;
    }

    /** @param UploadedFile[] $files */
    public function addDocuments(AdhesionRequest $r, array $files, array $kinds): void
    {
        $max = (int) config('mxconnect.social.adhesion_max_docs', 4);
        if ($r->documents()->count() + count($files) > $max) {
            throw ValidationException::withMessages(['documents' => "Maximum {$max} pièces par dossier."]);
        }
        foreach (array_values($files) as $i => $file) {
            $kind = $kinds[$i] ?? 'autre';
            $kind = array_key_exists($kind, AdhesionRequest::DOC_KINDS) ? $kind : 'autre';
            $dir = "adhesions/{$r->mutual_id}/{$r->reference}";
            $mime = $file->getMimeType();

            if ($mime === 'application/pdf') {
                $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 5);
                if ($head !== '%PDF-') {
                    throw ValidationException::withMessages(['documents' => 'PDF invalide : ' . $file->getClientOriginalName()]);
                }
                $path = "$dir/" . Str::uuid() . '.pdf';
                Storage::disk('social_private')->put($path, file_get_contents($file->getRealPath()));
                $size = $file->getSize();
            } else {
                $img = $this->media->privateImage($file);
                $path = "$dir/" . Str::uuid() . '.' . $img['ext'];
                Storage::disk('social_private')->put($path, $img['data']);
                $mime = $img['mime'];
                $size = strlen($img['data']);
            }

            AdhesionDocument::create([
                'adhesion_request_id' => $r->id, 'kind' => $kind, 'path' => $path, 'mime' => $mime, 'size' => $size,
                'original_name' => Str::limit(preg_replace('/[^\pL\pN ._()-]/u', '_', $file->getClientOriginalName()), 180, ''),
            ]);
        }
    }

    /** Changement de statut par la mutuelle (vérifie la transition). */
    public function decide(AdhesionRequest $r, string $to, ?string $note, int $userId, string $userLabel): void
    {
        abort_unless(in_array($to, self::TRANSITIONS[$r->status] ?? [], true), 422, 'Changement de statut non autorisé.');
        if (in_array($to, ['info_requested', 'rejected'], true) && trim((string) $note) === '') {
            throw ValidationException::withMessages(['note' => 'Expliquez au demandeur ce qui manque ou le motif du refus.']);
        }

        DB::connection('central')->transaction(function () use ($r, $to, $note, $userId, $userLabel) {
            $from = $r->status;
            $r->update([
                'status' => $to,
                'decided_at' => in_array($to, ['accepted', 'rejected'], true) ? now() : $r->decided_at,
                'decided_by' => in_array($to, ['accepted', 'rejected'], true) ? $userId : $r->decided_by,
            ]);
            AdhesionEvent::create(['adhesion_request_id' => $r->id, 'actor' => 'mutual', 'actor_label' => $userLabel,
                'from_status' => $from, 'to_status' => $to, 'note' => $note ? trim($note) : null]);
        });

        $name = $r->mutual?->name ?? 'La mutuelle';
        $this->notifications->notify($r->public_account_id, 'adhesion',
            "Votre demande d’adhésion à {$name} : " . mb_strtolower(AdhesionRequest::STATUSES[$to]),
            $to === 'info_requested' ? 'La mutuelle demande un complément d’information.' : null,
            '/compte/adhesions/' . $r->reference);
    }

    /** Le demandeur retire sa demande, ou répond à une demande d'information. */
    public function withdraw(AdhesionRequest $r): void
    {
        abort_unless($r->isOpen(), 422);
        $from = $r->status;
        $r->update(['status' => 'withdrawn']);
        AdhesionEvent::create(['adhesion_request_id' => $r->id, 'actor' => 'applicant',
            'actor_label' => $r->account?->name, 'from_status' => $from, 'to_status' => 'withdrawn']);
    }

    public function resubmit(AdhesionRequest $r, ?string $note): void
    {
        abort_unless($r->status === 'info_requested', 422);
        $r->update(['status' => 'in_review']);
        AdhesionEvent::create(['adhesion_request_id' => $r->id, 'actor' => 'applicant',
            'actor_label' => $r->account?->name, 'from_status' => 'info_requested', 'to_status' => 'in_review',
            'note' => $note ? trim($note) : null]);
    }

    /** Suppression complète (fichiers compris). */
    public function purge(AdhesionRequest $r): void
    {
        Storage::disk('social_private')->deleteDirectory("adhesions/{$r->mutual_id}/{$r->reference}");
        $r->delete();
    }
}

<?php

namespace App\Services\Social;

use App\Enums\ContributionType;
use App\Enums\EngagementStatus;
use App\Enums\OrganizationManagerRole;
use App\Enums\SupportType;
use App\Models\Central\EngagementRequest;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\Project;
use App\Models\Central\PublicAccount;
use App\Models\Central\SupportOffer;
use App\Notifications\Social\RequestReceived;
use App\Notifications\Social\RequestStatusChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The two engagement workflows — Action A "Je m'engage" (EngagementRequest) and
 * Action C "Soutenir ou devenir partenaire" (SupportOffer). They share one lifecycle:
 *
 *   envoyee ─┬─> en_cours ─┬─> acceptee ──> cloturee
 *            ├─> acceptee  └─> refusee
 *            └─> refusee
 *   (the requester may withdraw while envoyee / en_cours → cloturee)
 *
 * A support offer is an intent only — nothing here moves or promises money.
 */
class RequestService
{
    private const OPEN = [EngagementStatus::Envoyee, EngagementStatus::EnCours];

    public function submitEngagement(PublicAccount $requester, Organization $org, array $data): EngagementRequest
    {
        [$post, $project] = $this->guardSubmission($requester, $org, $data, 'engagement');

        $request = EngagementRequest::create([
            'organization_id' => $org->getKey(),
            'post_id' => $post?->getKey(),
            'project_id' => $project?->getKey(),
            'public_account_id' => $requester->getKey(),
            'contribution_type' => ContributionType::from($data['contribution_type']),
            'message' => $data['message'] ?? null,
            'status' => EngagementStatus::Envoyee,
        ])->refresh();

        $this->notifyAdministrators($org, new RequestReceived('engagement', $request));

        return $request;
    }

    public function submitSupport(PublicAccount $requester, Organization $org, array $data): SupportOffer
    {
        [$post, $project] = $this->guardSubmission($requester, $org, $data, 'support');

        $offer = SupportOffer::create([
            'organization_id' => $org->getKey(),
            'post_id' => $post?->getKey(),
            'project_id' => $project?->getKey(),
            'public_account_id' => $requester->getKey(),
            'support_type' => SupportType::from($data['support_type']),
            'message' => $data['message'] ?? null,
            'contact_info' => array_filter([
                'organization' => $data['contact_organization'] ?? null,
                'email' => $data['contact_email'] ?? null,
            ]) ?: null,
            'status' => EngagementStatus::Envoyee,
        ])->refresh();

        $this->notifyAdministrators($org, new RequestReceived('support', $offer));

        return $offer;
    }

    /** An owner/admin of the receiving organization moves the request along its lifecycle. */
    public function respond(EngagementRequest|SupportOffer $request, PublicAccount $manager, EngagementStatus $to, ?string $note = null): void
    {
        if (! $request->organization->isAdministeredBy($manager)) {
            throw new AuthorizationException(__('mxconnect.social.errors.not_admin'));
        }
        if (! in_array($to, self::allowedTransitions($request->status), true)) {
            throw ValidationException::withMessages(['status' => __('mxconnect.social.errors.bad_transition')]);
        }

        $request->update([
            'status' => $to,
            'responded_by_account_id' => $manager->getKey(),
            'responded_at' => now(),
            'response_note' => filled($note) ? $note : $request->response_note,
        ]);

        $request->requester->notify(new RequestStatusChanged($this->kindOf($request), $request->refresh()));
    }

    /** The requester closes their own still-open request. */
    public function withdraw(EngagementRequest|SupportOffer $request, PublicAccount $requester): void
    {
        if ((int) $request->public_account_id !== (int) $requester->getKey()) {
            throw new AuthorizationException(__('mxconnect.social.errors.not_allowed'));
        }
        if (! in_array($request->status, self::OPEN, true)) {
            throw ValidationException::withMessages(['status' => __('mxconnect.social.errors.bad_transition')]);
        }

        $request->update(['status' => EngagementStatus::Cloturee]);
    }

    /** @return list<EngagementStatus> */
    public static function allowedTransitions(EngagementStatus $from): array
    {
        return match ($from) {
            EngagementStatus::Envoyee => [EngagementStatus::EnCours, EngagementStatus::Acceptee, EngagementStatus::Refusee],
            EngagementStatus::EnCours => [EngagementStatus::Acceptee, EngagementStatus::Refusee],
            EngagementStatus::Acceptee => [EngagementStatus::Cloturee],
            EngagementStatus::Refusee, EngagementStatus::Cloturee => [],
        };
    }

    /** @return array{0: ?Post, 1: ?Project} */
    private function guardSubmission(PublicAccount $requester, Organization $org, array $data, string $kind): array
    {
        if (! $org->isPublished()) {
            throw ValidationException::withMessages(['organization' => __('mxconnect.social.errors.org_unavailable')]);
        }
        if ($org->roleOf($requester) !== null) {
            throw ValidationException::withMessages(['organization' => __('mxconnect.social.errors.own_organization')]);
        }

        $post = null;
        if (filled($data['post_id'] ?? null)) {
            $post = Post::where('organization_id', $org->getKey())->find($data['post_id']);
            $relevant = $post && ($kind === 'engagement' ? $post->offersEngagement() : $post->offersSupport());
            if (! $relevant || ! $post->isVisibleTo($requester)) {
                throw ValidationException::withMessages(['post_id' => __('mxconnect.social.errors.bad_target')]);
            }
        }

        $project = null;
        $projectId = filled($data['project_id'] ?? null) ? $data['project_id'] : $post?->project_id;
        if (filled($projectId)) {
            $project = Project::where('organization_id', $org->getKey())->find($projectId);
            if (! $project) {
                throw ValidationException::withMessages(['project_id' => __('mxconnect.social.errors.bad_target')]);
            }
        }

        $model = $kind === 'engagement' ? EngagementRequest::class : SupportOffer::class;
        $duplicate = $model::query()
            ->where('organization_id', $org->getKey())
            ->where('public_account_id', $requester->getKey())
            ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN))
            ->where(fn (Builder $q) => $post ? $q->where('post_id', $post->getKey()) : $q->whereNull('post_id'))
            ->where(fn (Builder $q) => $project ? $q->where('project_id', $project->getKey()) : $q->whereNull('project_id'))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['organization' => __('mxconnect.social.errors.duplicate_request')]);
        }

        return [$post, $project];
    }

    private function notifyAdministrators(Organization $org, object $notification): void
    {
        $admins = $org->managers()
            ->wherePivotIn('role', [OrganizationManagerRole::Owner->value, OrganizationManagerRole::Admin->value])
            ->get();

        Notification::send($admins, $notification);
    }

    private function kindOf(EngagementRequest|SupportOffer $request): string
    {
        return $request instanceof EngagementRequest ? 'engagement' : 'support';
    }
}

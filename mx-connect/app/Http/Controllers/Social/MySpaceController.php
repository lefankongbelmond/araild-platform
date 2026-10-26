<?php

namespace App\Http\Controllers\Social;

use App\Enums\EngagementStatus;
use App\Http\Controllers\Controller;
use App\Models\Central\EngagementRequest;
use App\Models\Central\Organization;
use App\Models\Central\SupportOffer;
use App\Services\Social\OrganizationService;
use App\Services\Social\RequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** "Mon espace": my requests, my organizations' inboxes, my notifications. */
class MySpaceController extends Controller
{
    public function __construct(
        private RequestService $requests,
        private OrganizationService $organizations,
    ) {}

    public function requests(Request $request)
    {
        $account = $request->user('member');

        return view('social.my.requests', [
            'engagements' => $account->engagementRequests()->with(['organization', 'project'])->latest()->get(),
            'offers' => $account->supportOffers()->with(['organization', 'project'])->latest()->get(),
        ]);
    }

    public function withdraw(Request $request, string $kind, int $id)
    {
        $model = $this->findRequest($kind, $id);
        $this->requests->withdraw($model, $request->user('member'));

        return back()->with('success', __('mxconnect.social.requests.withdrawn'));
    }

    public function organizations(Request $request)
    {
        $organizations = $request->user('member')->managedOrganizations()
            ->withCount([
                'engagementRequests as open_engagements' => fn ($q) => $q->whereIn('status', ['envoyee', 'en_cours']),
                'supportOffers as open_offers' => fn ($q) => $q->whereIn('status', ['envoyee', 'en_cours']),
            ])
            ->orderBy('name')->get();

        return view('social.my.organizations', ['organizations' => $organizations]);
    }

    public function inbox(Request $request, Organization $organization)
    {
        $this->organizations->authorizeAdmin($organization, $request->user('member'));

        $tab = $request->query('tab') === 'support' ? 'support' : 'engagement';
        $status = EngagementStatus::tryFrom((string) $request->query('statut'));

        $query = $tab === 'support' ? $organization->supportOffers() : $organization->engagementRequests();
        $items = $query->with(['requester', 'project', 'post'])
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->latest()->paginate(20)->withQueryString();

        return view('social.my.inbox', [
            'organization' => $organization,
            'tab' => $tab,
            'status' => $status,
            'items' => $items,
        ]);
    }

    public function respond(Request $request, Organization $organization, string $kind, int $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(EngagementStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $model = $this->findRequest($kind, $id);
        abort_unless((int) $model->organization_id === (int) $organization->getKey(), 404);

        $this->requests->respond($model, $request->user('member'), EngagementStatus::from($data['status']), $data['note'] ?? null);

        return back()->with('success', __('mxconnect.social.requests.updated'));
    }

    public function notifications(Request $request)
    {
        return view('social.my.notifications', [
            'notifications' => $request->user('member')->notifications()->paginate(20),
        ]);
    }

    public function markNotificationsRead(Request $request)
    {
        $request->user('member')->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    private function findRequest(string $kind, int $id): EngagementRequest|SupportOffer
    {
        return match ($kind) {
            'engagement' => EngagementRequest::findOrFail($id),
            'support' => SupportOffer::findOrFail($id),
            default => abort(404),
        };
    }
}

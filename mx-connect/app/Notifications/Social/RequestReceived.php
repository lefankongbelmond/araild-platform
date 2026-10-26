<?php

namespace App\Notifications\Social;

use App\Models\Central\EngagementRequest;
use App\Models\Central\SupportOffer;
use Illuminate\Notifications\Notification;

/** To an organization's owners/admins: a new "Je m'engage" or support proposal arrived. */
class RequestReceived extends Notification
{
    public function __construct(
        public string $kind,
        public EngagementRequest|SupportOffer $request,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $org = $this->request->organization;

        return [
            'event' => 'request_received',
            'kind' => $this->kind,
            'request_id' => $this->request->getKey(),
            'organization_name' => $org->name,
            'organization_slug' => $org->slug,
            'requester_name' => $this->request->requester->displayName(),
            'type' => $this->kind === 'engagement'
                ? $this->request->contribution_type->value
                : $this->request->support_type->value,
            'url' => route('my.organizations.inbox', ['organization' => $org->slug, 'tab' => $this->kind]),
        ];
    }
}

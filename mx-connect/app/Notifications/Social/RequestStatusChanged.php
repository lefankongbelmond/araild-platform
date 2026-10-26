<?php

namespace App\Notifications\Social;

use App\Models\Central\EngagementRequest;
use App\Models\Central\SupportOffer;
use Illuminate\Notifications\Notification;

/** To the requester: the organization moved their request (en cours / acceptée / refusée / clôturée). */
class RequestStatusChanged extends Notification
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
            'event' => 'request_status_changed',
            'kind' => $this->kind,
            'request_id' => $this->request->getKey(),
            'organization_name' => $org->name,
            'organization_slug' => $org->slug,
            'status' => $this->request->status->value,
            'url' => route('my.requests'),
        ];
    }
}

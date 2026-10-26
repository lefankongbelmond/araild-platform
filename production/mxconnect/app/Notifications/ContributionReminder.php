<?php

namespace App\Notifications;

use App\Models\Tenant\ContributionSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Contribution reminder — delivered on the member's preferred channels.
 * The 'sms' channel is a custom channel configured per deployment (SMS_DRIVER).
 * Queued so a reminder sweep across thousands of members doesn't block the request.
 */
class ContributionReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContributionSchedule $schedule) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];   // 'sms' added where a gateway is configured
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mxconnect.contribution.reminder_subject'))
            ->line(__('mxconnect.contribution.reminder_line', [
                'period' => $this->schedule->period,
                'date'   => optional($this->schedule->due_date)->format('d/m/Y'),
            ]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'period'      => $this->schedule->period,
            'due_date'    => optional($this->schedule->due_date)->toDateString(),
        ];
    }
}

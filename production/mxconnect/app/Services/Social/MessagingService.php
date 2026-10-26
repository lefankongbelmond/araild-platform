<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Conversation;
use App\Models\Social\Message;
use Illuminate\Support\Facades\DB;

/** Messagerie privée membre ↔ mutuelle (aucun message entre membres). */
class MessagingService
{
    public function __construct(private NotificationService $notifications, private MailService $mail) {}

    public function start(Mutual $mutual, PublicAccount $account, string $subject, string $body, ?int $adhesionId = null): Conversation
    {
        return DB::connection('central')->transaction(function () use ($mutual, $account, $subject, $body, $adhesionId) {
            $c = Conversation::create([
                'mutual_id' => $mutual->id, 'public_account_id' => $account->id,
                'adhesion_request_id' => $adhesionId, 'subject' => trim($subject),
            ]);
            $this->postFromAccount($c, $body);

            return $c;
        });
    }

    public function postFromAccount(Conversation $c, string $body): Message
    {
        $wasRead = (int) Conversation::whereKey($c->id)->value('mutual_unread') === 0;
        $m = DB::connection('central')->transaction(function () use ($c, $body) {
            $m = Message::create(['conversation_id' => $c->id, 'sender' => 'account', 'body' => trim($body)]);
            $c->forceFill(['last_message_at' => now(), 'status' => 'open'])->save();
            Conversation::whereKey($c->id)->increment('mutual_unread');

            return $m;
        });
        // Une seule alerte tant que la mutuelle n'a pas lu la conversation (pas de rafale d'e-mails).
        if ($wasRead && ($mutual = $c->mutual()->with('profile')->first())) {
            $this->mail->alertMutual($mutual, 'Nouveau message d’un membre', 'Un membre vous a écrit sur MX-CONNECT.', '/messagerie/' . $c->id);
        }

        return $m;
    }

    public function postFromMutual(Conversation $c, string $body, int $tenantUserId): Message
    {
        $m = DB::connection('central')->transaction(function () use ($c, $body, $tenantUserId) {
            $m = Message::create(['conversation_id' => $c->id, 'sender' => 'mutual', 'tenant_user_id' => $tenantUserId, 'body' => trim($body)]);
            $c->forceFill(['last_message_at' => now()])->save();
            Conversation::whereKey($c->id)->increment('account_unread');

            return $m;
        });
        $this->notifications->notify($c->public_account_id, 'message',
            'Nouveau message de ' . ($c->mutual?->name ?? 'la mutuelle'), $c->subject, '/messages/' . $c->id);

        return $m;
    }

    public function markReadByAccount(Conversation $c): void
    {
        if ($c->account_unread) {
            $c->forceFill(['account_unread' => 0])->save();
        }
    }

    public function markReadByMutual(Conversation $c): void
    {
        if ($c->mutual_unread) {
            $c->forceFill(['mutual_unread' => 0])->save();
        }
    }
}

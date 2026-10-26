<?php

namespace App\Support;

/** L'envoi d'e-mails est-il réellement configuré (SMTP) ? Sinon, aucune promesse d'envoi n'est faite. */
class MailStatus
{
    public static function configured(): bool
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array', ''], true)) {
            return false;
        }
        if ($mailer === 'smtp') {
            return filled(config('mail.mailers.smtp.host')) && filled(config('mail.from.address'));
        }

        return true;
    }
}

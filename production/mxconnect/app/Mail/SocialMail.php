<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail simple aux couleurs MX-CONNECT : un titre, quelques lignes, un bouton.
 * Ne contient jamais de données de dossier d'adhésion ni d'information médicale.
 */
class SocialMail extends Mailable
{
    /** @param string[] $lines */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public array $lines = [],
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $footnote = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.social', text: 'mail.social-text');
    }
}

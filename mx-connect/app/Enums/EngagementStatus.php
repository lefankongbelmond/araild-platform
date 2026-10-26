<?php

namespace App\Enums;

/** Shared lifecycle for engagement_requests ("Je m'engage") and support_offers. */
enum EngagementStatus: string
{
    case Envoyee = 'envoyee';
    case EnCours = 'en_cours';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';
    case Cloturee = 'cloturee';
}

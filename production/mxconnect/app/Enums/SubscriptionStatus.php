<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Captured  = 'captured';   // saisie
    case Validated = 'validated';  // validée
    case Terminated = 'terminated';
}

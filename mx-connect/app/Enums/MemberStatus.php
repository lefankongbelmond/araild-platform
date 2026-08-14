<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Active    = 'active';
    case Suspended = 'suspended';
    case Left      = 'left';
    case Revoked   = 'revoked';
}

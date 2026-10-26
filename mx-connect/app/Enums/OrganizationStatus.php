<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Suspended = 'suspended';
}

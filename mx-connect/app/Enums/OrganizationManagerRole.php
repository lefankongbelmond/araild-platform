<?php

namespace App\Enums;

enum OrganizationManagerRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
}

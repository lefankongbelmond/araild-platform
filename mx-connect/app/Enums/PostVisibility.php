<?php

namespace App\Enums;

enum PostVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
}

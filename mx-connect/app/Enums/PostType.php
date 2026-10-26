<?php

namespace App\Enums;

enum PostType: string
{
    case Text = 'text';
    case Photo = 'photo';
    case Video = 'video';
    case Event = 'event';
    case Poll = 'poll';
    case Opportunity = 'opportunity';
    case Result = 'result';
}

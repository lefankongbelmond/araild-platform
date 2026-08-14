<?php

namespace App\Enums;

enum AlertLevel: string
{
    case Normal   = 'normal';   // green
    case Watch     = 'watch';   // amber
    case Critical = 'critical'; // red
}

<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planifie = 'planifie';
    case EnCours = 'en_cours';
    case Termine = 'termine';
}

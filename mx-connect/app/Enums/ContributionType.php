<?php

namespace App\Enums;

enum ContributionType: string
{
    case Benevolat = 'benevolat';
    case Participation = 'participation';
    case Competence = 'competence';
    case Accompagnement = 'accompagnement';
    case Autre = 'autre';
}

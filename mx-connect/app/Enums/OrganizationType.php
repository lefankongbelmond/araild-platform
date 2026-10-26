<?php

namespace App\Enums;

enum OrganizationType: string
{
    case Mutual = 'mutual';
    case Ong = 'ong';
    case Association = 'association';
    case EntrepriseSociale = 'entreprise_sociale';
    case InstitutionPublique = 'institution_publique';
    case Autre = 'autre';
}

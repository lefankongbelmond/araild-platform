<?php

namespace App\Enums;

enum SupportType: string
{
    case Financier = 'financier';
    case Materiel = 'materiel';
    case Expertise = 'expertise';
    case Accompagnement = 'accompagnement';
    case PartenariatOperationnel = 'partenariat_operationnel';
    case Autre = 'autre';
}

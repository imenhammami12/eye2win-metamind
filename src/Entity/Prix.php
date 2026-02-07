<?php

namespace App\Entity;

enum Prix: string
{
    case ARGENT = 'ARGENT';
    case TROPHEE = 'TROPHEE';
    case MEDAILLE = 'MEDAILLE';
    case CERTIFICAT = 'CERTIFICAT';
    case AUCUN = 'AUCUN';
}

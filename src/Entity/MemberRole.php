<?php

namespace App\Entity;

enum MemberRole: string
{
    case OWNER = 'OWNER';
    case CO_CAPTAIN = 'CO_CAPTAIN';
    case MEMBER = 'MEMBER';

    public function getLabel(): string
    {
        return match($this) {
            self::OWNER => 'Propriétaire',
            self::CO_CAPTAIN => 'Co-Capitaine',
            self::MEMBER => 'Membre',
        };
    }
}
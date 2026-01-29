<?php

namespace App\Entity;

enum MembershipStatus: string
{
    case INVITED = 'INVITED';
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case LEFT = 'LEFT';

    public function getLabel(): string
    {
        return match($this) {
            self::INVITED => 'Invité',
            self::ACTIVE => 'Actif',
            self::INACTIVE => 'Inactif',
            self::LEFT => 'A quitté',
        };
    }
}
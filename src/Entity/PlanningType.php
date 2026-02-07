<?php

namespace App\Entity;

enum PlanningType: string
{
    case FPS = 'FPS';
    case MOBA = 'MOBA';
    case BATTLE_ROYALE = 'Battle Royale';
    case SPORT = 'Sport';
    case COMBAT = 'Combat';
    case RPG_MMORPG = 'RPG/MMORPG';
    case STRATEGY = 'Stratégie';

    public function getLabel(): string
    {
        return match($this) {
            self::FPS => 'First Person Shooter',
            self::MOBA => 'Multiplayer Online Battle Arena',
            self::BATTLE_ROYALE => 'Battle Royale (BR)',
            self::SPORT => 'Sports Games',
            self::COMBAT => 'Fighting Games',
            self::RPG_MMORPG => 'Role-Playing Game / Massively Multiplayer Online RPG',
            self::STRATEGY => 'RTS / TBS',
        };
    }
}

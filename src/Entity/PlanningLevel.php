<?php

namespace App\Entity;

enum PlanningLevel: string
{
    case BEGINNER = 'Beginner';
    case INTERMEDIATE = 'Intermediate';
    case ADVANCED = 'Advanced';
    case PROFESSIONAL = 'Professional';

    public function getLabel(): string
    {
        return match($this) {
            self::BEGINNER => 'Beginner (Entry Level)',
            self::INTERMEDIATE => 'Intermediate (Mid Level)',
            self::ADVANCED => 'Advanced (High Level)',
            self::PROFESSIONAL => 'Professional (Pro Level)',
        };
    }
}

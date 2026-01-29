<?php

namespace App\Entity;

enum NotificationType: string
{
    case TEAM_INVITATION = 'TEAM_INVITATION';
    case TEAM_ACCEPTED = 'TEAM_ACCEPTED';
    case COACH_APPLICATION = 'COACH_APPLICATION';
    case COACH_APPROVED = 'COACH_APPROVED';
    case COACH_REJECTED = 'COACH_REJECTED';
    case ACCOUNT_WARNING = 'ACCOUNT_WARNING';
    case SYSTEM = 'SYSTEM';

    public function getLabel(): string
    {
        return match($this) {
            self::TEAM_INVITATION => 'Invitation d\'équipe',
            self::TEAM_ACCEPTED => 'Invitation acceptée',
            self::COACH_APPLICATION => 'Demande de coach',
            self::COACH_APPROVED => 'Coach approuvé',
            self::COACH_REJECTED => 'Coach rejeté',
            self::ACCOUNT_WARNING => 'Avertissement',
            self::SYSTEM => 'Système',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::TEAM_INVITATION => '📨',
            self::TEAM_ACCEPTED => '✅',
            self::COACH_APPLICATION => '📋',
            self::COACH_APPROVED => '🎓',
            self::COACH_REJECTED => '❌',
            self::ACCOUNT_WARNING => '⚠️',
            self::SYSTEM => 'ℹ️',
        };
    }
}
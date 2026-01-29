<?php

namespace App\Entity;

enum AccountStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case BANNED = 'BANNED';
    case PENDING = 'PENDING';

    public function getLabel(): string
    {
        return match($this) {
            self::ACTIVE => 'Actif',
            self::SUSPENDED => 'Suspendu',
            self::BANNED => 'Banni',
            self::PENDING => 'En attente',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::BANNED => 'danger',
            self::PENDING => 'info',
        };
    }
}
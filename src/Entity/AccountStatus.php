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
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::BANNED => 'Banned',
            self::PENDING => 'Pending',
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

    public function getDescription(): string
    {
        return match($this) {
            self::ACTIVE => 'Account is active and can access the application',
            self::SUSPENDED => 'Account is temporarily suspended. Access is restricted until reactivated.',
            self::BANNED => 'Account is permanently banned. User cannot access the application.',
            self::PENDING => 'Account is pending approval from an administrator',
        };
    }
}
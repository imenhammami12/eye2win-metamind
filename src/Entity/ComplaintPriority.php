<?php

namespace App\Entity;

enum ComplaintPriority: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case URGENT = 'URGENT';

    public function getLabel(): string
    {
        return match($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::LOW => 'secondary',
            self::MEDIUM => 'info',
            self::HIGH => 'warning',
            self::URGENT => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::LOW => 'bi-arrow-down',
            self::MEDIUM => 'bi-dash',
            self::HIGH => 'bi-arrow-up',
            self::URGENT => 'bi-exclamation-circle-fill',
        };
    }
}

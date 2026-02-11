<?php

namespace App\Entity;

enum ComplaintStatus: string
{
    case PENDING = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';
    case REJECTED = 'REJECTED';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::REJECTED => 'Rejected',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::IN_PROGRESS => 'info',
            self::RESOLVED => 'success',
            self::CLOSED => 'secondary',
            self::REJECTED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::PENDING => 'bi-clock-history',
            self::IN_PROGRESS => 'bi-arrow-repeat',
            self::RESOLVED => 'bi-check-circle',
            self::CLOSED => 'bi-x-circle',
            self::REJECTED => 'bi-exclamation-triangle',
        };
    }
}

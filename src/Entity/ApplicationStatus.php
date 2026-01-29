<?php

namespace App\Entity;

enum ApplicationStatus: string
{
    case PENDING = 'PENDING';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::UNDER_REVIEW => 'En révision',
            self::APPROVED => 'Approuvé',
            self::REJECTED => 'Rejeté',
            self::CANCELLED => 'Annulé',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::UNDER_REVIEW => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'secondary',
        };
    }
}
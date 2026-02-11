<?php

namespace App\Entity;

enum ComplaintCategory: string
{
    case TECHNICAL = 'TECHNICAL';
    case ACCOUNT = 'ACCOUNT';
    case TOURNAMENT = 'TOURNAMENT';
    case TEAM = 'TEAM';
    case PAYMENT = 'PAYMENT';
    case CONTENT = 'CONTENT';
    case HARASSMENT = 'HARASSMENT';
    case BUG = 'BUG';
    case OTHER = 'OTHER';

    public function getLabel(): string
    {
        return match($this) {
            self::TECHNICAL => 'Technical Issue',
            self::ACCOUNT => 'Account Problem',
            self::TOURNAMENT => 'Tournament Issue',
            self::TEAM => 'Team Problem',
            self::PAYMENT => 'Payment Issue',
            self::CONTENT => 'Content Violation',
            self::HARASSMENT => 'Harassment',
            self::BUG => 'Bug Report',
            self::OTHER => 'Other',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::TECHNICAL => 'bi-tools',
            self::ACCOUNT => 'bi-person-circle',
            self::TOURNAMENT => 'bi-trophy',
            self::TEAM => 'bi-people',
            self::PAYMENT => 'bi-credit-card',
            self::CONTENT => 'bi-file-earmark-text',
            self::HARASSMENT => 'bi-shield-exclamation',
            self::BUG => 'bi-bug',
            self::OTHER => 'bi-question-circle',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::TECHNICAL => 'Technical problems with the platform',
            self::ACCOUNT => 'Issues related to your account',
            self::TOURNAMENT => 'Problems with tournaments',
            self::TEAM => 'Team-related issues',
            self::PAYMENT => 'Payment and billing issues',
            self::CONTENT => 'Inappropriate content',
            self::HARASSMENT => 'Report harassment or abuse',
            self::BUG => 'Report a bug or error',
            self::OTHER => 'Other issues',
        };
    }
}

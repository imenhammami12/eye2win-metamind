<?php

namespace App\Entity;

enum MembershipStatus: string
{
    case INVITED = 'INVITED';
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case LEFT = 'LEFT';
    case PENDING = 'PENDING';

    public function getLabel(): string
    {
        return match($this) {
            self::INVITED => 'Invited',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::LEFT => 'Left',
            self::PENDING => 'Pending', 
        };
    }
    
    public function getBadgeClass(): string
    {
        return match($this) {
            self::INVITED => 'info',
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::LEFT => 'dark',
            self::PENDING => 'warning',
        };
    }
}
<?php

namespace App\Enums;

enum SalonStaffRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Stylist = 'stylist';
    case Receptionist = 'receptionist';

    public function isPlatformRole(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function isSalonOwner(): bool
    {
        return $this === self::Admin;
    }

    public function isSalonEmployee(): bool
    {
        return in_array($this, [self::Manager, self::Stylist, self::Receptionist], true);
    }
}

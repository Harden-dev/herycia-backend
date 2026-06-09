<?php

namespace App\Enums;

enum AdminUserType: string
{
    case Owner = 'owner';
    case Employee = 'employee';
    case SuperAdmin = 'super_admin';
}

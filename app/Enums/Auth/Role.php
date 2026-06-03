<?php

namespace App\Enums\Auth;

enum Role: string
{
    case CUSTOMER = 'CUSTOMER';
    case ADMIN = 'ADMIN';
    case OWNER = 'OWNER';
}

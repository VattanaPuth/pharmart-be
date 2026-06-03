<?php

namespace App\Enums\Invoice;

enum DeliveredMethod: string
{
    case PICKUP = 'pickup';
    case DELIVERY = 'delivery';
}

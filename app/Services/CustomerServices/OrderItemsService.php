<?php

namespace App\Services\CustomerServices;

use Illuminate\Support\Collection;

interface OrderItemsService
{
	public function createFromCartItems(int $orderId, Collection $cartItems): void;
}

<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\OrderItems;
use App\Services\CustomerServices\OrderItemsService;
use Illuminate\Support\Collection;

class OrderItemsServiceImpl implements OrderItemsService
{
	public function createFromCartItems(int $orderId, Collection $cartItems): void
	{
		foreach ($cartItems as $cartItem) {
			OrderItems::query()->create([
				'order_id' => $orderId,
				'product_id' => $cartItem->product_id,
				'owner_id' => $cartItem->owner_id,
				'product_name' => $cartItem->product->product_name,
				'product_image' => $cartItem->product->main_image,
				'unit_price' => (string) $cartItem->unit_price,
				'quantity' => (int) $cartItem->quantity,
				'line_total' => (string) $cartItem->line_total,
				'package_id' => $cartItem->package_id,
				'package_name' => $cartItem->package->package_name,
			]);
		}
	}

}

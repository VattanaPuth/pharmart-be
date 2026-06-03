<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\CartItems;
use App\Services\CustomerServices\CartItemsService;

class CartItemsServiceImpl implements CartItemsService
{
     public function addOrUpdateItem(
        int $cartId,
        int $productId,
        int $ownerId,
        int $packageId,
        int $quantity,
        string $unitPrice,
        string $lineTotal
    ): CartItems{
        $item = CartItems::query()
            ->where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->where('package_id', $packageId)
            ->first();

        if ($item) {
            $item->quantity += $quantity;
            $item->unit_price = $unitPrice;

            // recompute safely
            $item->line_total = number_format(
                $item->quantity * (float) $unitPrice,
                2,
                '.',
                ''
            );

            $item->save();

            return $item->fresh();
        }

        return CartItems::create([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'owner_id' => $ownerId,
            'package_id' => $packageId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);
    }
}
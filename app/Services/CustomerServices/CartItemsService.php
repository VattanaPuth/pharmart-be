<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\CartItems;

interface CartItemsService
{
    public function addOrUpdateItem(
        int $cartId,
        int $productId,
        int $ownerId,
        int $packageId,
        int $quantity,
        string $unitPrice,
        string $lineTotal
    ): CartItems;
}


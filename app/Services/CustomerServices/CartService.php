<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\Cart;

interface CartService
{
    public function getOrCreateActiveCart(int $customerId): Cart;

    public function addToCart(int $customerId, int $productId, int $quantity, ?int $packageId = null): array;

    public function getCart(int $customerId, int $perPage): array;

    public function updateItem(int $customerId, int $cartItemId, int $quantity): array;

    public function removeItem(int $customerId, int $cartItemId): void;

    public function removeProduct(int $customerId, int $productId): void;
    public function getCartItemCount(int $customerId): int;
}
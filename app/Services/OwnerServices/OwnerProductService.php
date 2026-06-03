<?php

namespace App\Services\OwnerServices;

use App\Models\Owner\OwnerProduct;
use Illuminate\Pagination\LengthAwarePaginator;

interface OwnerProductService
{
    public function visible(array $specification = [], ?int $ownerId = null): LengthAwarePaginator;

    public function addProduct(int $ownerId, array $data): OwnerProduct;

    public function updateProduct(int $ownerId, int $productId, array $data): OwnerProduct;

    public function hideProduct(int $ownerId, int $productId): bool;
}

<?php

namespace App\Specifications\OwnerProduct\impl;

use App\Specifications\OwnerProduct\OwnerProductSpecification;
use Illuminate\Database\Eloquent\Builder;

class OwnerProductSortSpecification implements OwnerProductSpecification
{
    private const ALLOWED_SORT_COLUMNS = [
        'created_at',
        'updated_at',
        'product_name',
        'expiry_date',
        'price',
        'distance'
    ];

    public function __construct(
        private ?string $sortBy,
        private ?string $sortDir
    ) {}

    public function apply(Builder $query): Builder
    {
        $sortDir = strtolower((string) $this->sortDir) === 'asc' ? 'asc' : 'desc';

        $sortBy = in_array($this->sortBy, self::ALLOWED_SORT_COLUMNS, true)
            ? $this->sortBy
            : 'created_at';

        // ----------------------------
        // PRICE SORT (FINAL FIX)
        // ----------------------------
        if ($sortBy === 'price') {

            return $query
                ->leftJoin('owner_package as op', function ($join) {
                    $join->on('op.owner_product_id', '=', 'owner_product.id')
                        ->where('op.is_default', '=', 1);
                })
                ->orderBy('op.price', $sortDir)
                ->select('owner_product.*'); // IMPORTANT
        }

        if ($sortBy === 'distance') {
            return $query;
        }

        // ----------------------------
        // NORMAL SORT
        // ----------------------------
        return $query->orderBy($sortBy, $sortDir);
    }
}

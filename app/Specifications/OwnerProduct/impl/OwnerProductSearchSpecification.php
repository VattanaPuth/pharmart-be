<?php

namespace App\Specifications\OwnerProduct\impl;

use App\Specifications\OwnerProduct\OwnerProductSpecification;
use Illuminate\Database\Eloquent\Builder;

class OwnerProductSearchSpecification implements OwnerProductSpecification
{
    public function __construct(private ?string $search) {}

    public function apply(Builder $query): Builder
    {
        if ($this->search === null || trim($this->search) === '') {
            return $query;
        }

        $needle = trim($this->search);

        return $query->where(function (Builder $q) use ($needle): void {
            $keyword = '%' . strtolower($needle) . '%';

            $q->whereRaw('LOWER(product_name) LIKE ?', [$keyword])
                ->orWhereRaw('LOWER(generic_name) LIKE ?', [$keyword])
                ->orWhereRaw('LOWER(strength) LIKE ?', [$keyword])
                ->orWhereRaw('LOWER(form) LIKE ?', [$keyword])
                ->orWhereRaw('LOWER(description) LIKE ?', [$keyword]);
        });
    }
}

<?php

namespace App\Specifications\OwnerProduct\impl;

use App\Specifications\OwnerProduct\OwnerProductSpecification;
use Illuminate\Database\Eloquent\Builder;

class OwnerProductFilterSpecification implements OwnerProductSpecification
{
    public function __construct(private array $filters)
    {
    }

    public function apply(Builder $query): Builder
    {
        if (array_key_exists('category_id', $this->filters) && $this->filters['category_id'] !== null) {
            $query->where('category_id', $this->filters['category_id']);
        }

        if (array_key_exists('subcategory_id', $this->filters) && $this->filters['subcategory_id'] !== null) {
            $query->where('subcategory_id', $this->filters['subcategory_id']);
        }

        return $query;
    }
}

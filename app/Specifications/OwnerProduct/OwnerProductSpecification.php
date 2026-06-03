<?php

namespace App\Specifications\OwnerProduct;

use Illuminate\Database\Eloquent\Builder;

interface OwnerProductSpecification
{
    public function apply(Builder $query): Builder;
}

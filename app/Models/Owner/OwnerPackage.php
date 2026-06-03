<?php

namespace App\Models\Owner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerPackage extends Model
{
    protected $table = 'owner_package';

    protected $fillable = [
        'owner_product_id',
        'package_name',
        'contains',
        'price',
        'stock_quantity',
        'low_stock_threshold',
        'is_default',
    ];

    protected $casts = [
    'price' => 'decimal:2',
    'stock_quantity' => 'integer',
    'is_default' => 'boolean',
];

    public function ownerProduct(): BelongsTo
    {
        return $this->belongsTo(OwnerProduct::class, 'owner_product_id');
    }

    public function scopeDefault($query)
{
    return $query->where('is_default', true);
}
}

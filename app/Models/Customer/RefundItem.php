<?php

namespace App\Models\Customer;

use App\Models\Owner\OwnerProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundItem extends Model
{
    protected $table = 'customer_refund_items';

    public const UPDATED_AT = null;

    protected $fillable = [
        'refund_id',
        'order_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_refund_amount',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_refund_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class, 'refund_id', 'id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItems::class, 'order_item_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(OwnerProduct::class, 'product_id', 'id');
    }
}

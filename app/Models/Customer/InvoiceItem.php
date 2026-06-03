<?php

namespace App\Models\Customer;

use App\Models\Owner\OwnerProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $table = 'customer_invoice_items';

    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'product_id',
        'item_name',
        'item_description',
        'quantity',
        'unit_price',
        'line_total',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
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

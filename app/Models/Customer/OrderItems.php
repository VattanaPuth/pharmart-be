<?php

namespace App\Models\Customer;

use App\Models\Owner\Owner;
use App\Models\Owner\OwnerProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItems extends Model
{
	protected $table = 'customer_order_items';

	public const UPDATED_AT = null;

	protected $fillable = [
		'order_id',
		'product_id',
		'product_sku',
		'owner_id',
		'product_name',
		'product_image',
		'unit_price',
		'quantity',
		'line_total',
		'package_id',        // ✅ ADD
		'package_name',      // ✅ ADD

		'product_snapshot',  // ✅ ADD
	];

	protected $casts = [
		'unit_price' => 'decimal:2',
		'quantity' => 'integer',
		'line_total' => 'decimal:2',
		'product_snapshot' => 'array',
	];

	public function order(): BelongsTo
	{
		return $this->belongsTo(Order::class, 'order_id', 'id');
	}

	public function owner(): BelongsTo
	{
		return $this->belongsTo(Owner::class, 'owner_id', 'id');
	}

	public function product(): BelongsTo
	{
		return $this->belongsTo(OwnerProduct::class, 'product_id', 'id');
	}

	public function refundItems(): HasMany
	{
		return $this->hasMany(RefundItem::class, 'order_item_id', 'id');
	}

	public function getProductTotalAttribute()
	{
		return $this->unit_price * $this->quantity;
	}
}

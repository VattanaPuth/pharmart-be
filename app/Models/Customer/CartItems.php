<?php

namespace App\Models\Customer;

use App\Models\Owner\Owner;
use App\Models\Owner\OwnerProduct;
use App\Models\Owner\OwnerPackage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItems extends Model
{
	protected $table = 'customer_cart_items';

	protected $fillable = [
		'cart_id',
		'product_id',
		'owner_id',
		'package_id',
		'quantity',
		'unit_price',
		'line_total',
	];

	protected $casts = [
		'quantity' => 'integer',
		'unit_price' => 'decimal:2',
		'line_total' => 'decimal:2',
	];

	public function cart(): BelongsTo
	{
		return $this->belongsTo(Cart::class, 'cart_id', 'id');
	}

	public function product()
	{
		return $this->belongsTo(OwnerProduct::class, 'product_id');
	}

	public function owner(): BelongsTo
	{
		return $this->belongsTo(Owner::class, 'owner_id', 'id');
	}

	public function package()
	{
		return $this->belongsTo(OwnerPackage::class, 'package_id');
	}
}

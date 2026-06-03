<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
	protected $table = 'customer_cart';

	protected $fillable = [
		'customer_id',
		'status',
	];

	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class, 'customer_id', 'id');
	}

	public function items(): HasMany
	{
		return $this->hasMany(CartItems::class, 'cart_id', 'id');
	}
}

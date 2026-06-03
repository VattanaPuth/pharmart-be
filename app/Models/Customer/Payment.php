<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
	protected $table = 'payments';

	protected $fillable = [
		'customer_id',
		'payment_provider',
		'transaction_id',
		'stripe_charge_id',
		'amount',
		'currency',
		'status',
		'paid_at',
		'checkout_session_id'

	];

	protected $casts = [
		'amount' => 'decimal:2',
		'paid_at' => 'datetime',
	];

	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class, 'customer_id', 'id');
	}

	// public function orders(): BelongsToMany
	// {
	// 	return $this->belongsToMany(Order::class, 'customer_payment_orders', 'payment_id', 'order_id')
	// 		->withPivot('amount');
	// }

	public function refunds(): HasMany
	{
		return $this->hasMany(Refund::class, 'payment_id', 'id');
	}

	public function invoices(): HasMany
	{
		return $this->hasMany(Invoice::class, 'payment_id', 'id');
	}

	public function orders()
	{
		return $this->hasMany(Order::class, 'payment_id');
	}
}

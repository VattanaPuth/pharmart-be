<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
	protected $table = 'customer_payment_orders';

	public const UPDATED_AT = null;

	protected $fillable = [
		'payment_id',
		'order_id',
		'amount',
		'created_at',
	];

	protected $casts = [
		'amount' => 'decimal:2',
		'created_at' => 'datetime',
	];

	public function payment(): BelongsTo
	{
		return $this->belongsTo(Payment::class, 'payment_id', 'id');
	}

	public function order(): BelongsTo
	{
		return $this->belongsTo(Order::class, 'order_id', 'id');
	}
}

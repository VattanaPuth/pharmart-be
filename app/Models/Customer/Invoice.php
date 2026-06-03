<?php

namespace App\Models\Customer;

use App\Enums\Invoice\DeliveredMethod;
use App\Models\Owner\Owner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
	protected $table = 'customer_invoice';

	protected $fillable = [
		'invoice_number',
		'order_id',
		'payment_id',
		'customer_id',
		'owner_id',
		'order_number',
		'payment_ref',
		'bill_to_name',
		'bill_to_email',
		'bill_to_address',
		'from_name',
		'from_tax_id',
		'from_address',
		'delivered_method',
		'invoice_date',
		'subtotal',
		'shipping_fee',
		'discount_amount',
		'tax_amount',
		'total',
		'currency',
		'notes',
		'issued_at',
	];

	protected $casts = [
		'delivered_method' => DeliveredMethod::class,
		'invoice_date' => 'date',
		'subtotal' => 'decimal:2',
		'shipping_fee' => 'decimal:2',
		'discount_amount' => 'decimal:2',
		'tax_amount' => 'decimal:2',
		'total' => 'decimal:2',
		'issued_at' => 'datetime',
	];

	public function order(): BelongsTo
	{
		return $this->belongsTo(Order::class, 'order_id', 'id');
	}

	public function payment(): BelongsTo
	{
		return $this->belongsTo(Payment::class, 'payment_id', 'id');
	}

	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class, 'customer_id', 'id');
	}

	public function owner(): BelongsTo
	{
		return $this->belongsTo(Owner::class, 'owner_id', 'id');
	}

	public function items(): HasMany
	{
		return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id');
	}

}

<?php

namespace App\Models\Customer;

use App\Models\Owner\Owner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Customer\RefundImage;

class Refund extends Model
{
	protected $table = 'customer_refunds';

	protected $fillable = [
		'order_id',
		'payment_id',
		'customer_id',
		'owner_id',
		'refund_number',
		'reason',
		'note',
		'status',
		'refund_type',
		'refund_amount',
		'requested_by',
		'reviewed_by',
		'inspection_note',
		'requested_at',
		'reviewed_at',
		'processed_at',
		'stripe_refund_id',
	
	];

	protected $casts = [
		'refund_amount' => 'decimal:2',
		'requested_at' => 'datetime',
		'reviewed_at' => 'datetime',
		'processed_at' => 'datetime',
	];

	protected $appends = ['evidence'];

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
		return $this->hasMany(RefundItem::class, 'refund_id', 'id');
	}

	public function isFinal()
	{
		return in_array($this->status, ['refunded', 'rejected']);
	}

	public function images()
	{
		return $this->hasMany(RefundImage::class, 'refund_id');
	}


	public function customerImages()
	{
		return $this->hasMany(RefundImage::class, 'refund_id')
			->where('uploaded_by_type', 'customer');
	}

	public function inspectionImages()
	{
		return $this->hasMany(RefundImage::class, 'refund_id')
			->where('uploaded_by_type', 'pharmacy');
	}

	public function getEvidenceAttribute()
{
    return [
        'customer' => $this->images
            ->where('uploaded_by_type', 'customer')
            ->values(),

        'inspection' => $this->images
            ->where('uploaded_by_type', 'pharmacy')
            ->values(),
    ];
}
}

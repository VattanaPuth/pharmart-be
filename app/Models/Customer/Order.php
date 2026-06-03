<?php

namespace App\Models\Customer;

use App\Models\Owner\Owner;
use App\Models\Owner\OwnerSetting;
use App\Models\Customer\ProductReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $table = 'customer_order';

    protected $fillable = [
        'customer_id',
        'owner_id',
        'order_number',
        'status',
        'status_history',
        'fulfillment_method',
        'payment_method',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'total',
        'delivery_address',
        'payment_id',
        'pharmacy_completed_at',
        'customer_completed_at',
        'checkout_session_id',
        'decline_reason'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'status_history' => 'array',
        'pharmacy_completed_at' => 'datetime',
        'customer_completed_at' => 'datetime',
    ];

    /* =========================
       RELATIONS
    ========================= */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function ownerSetting(): BelongsTo
    {
        return $this->belongsTo(OwnerSetting::class, 'owner_id', 'owner_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItems::class, 'order_id');
    }



  public function refund()
{
    // return $this->hasOne(Refund::class);
      return $this->hasOne(Refund::class, 'order_id', 'id');
}

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id');
    }

    /* =========================
       UI HELPERS (VERY IMPORTANT)
    ========================= */

    public function getPharmacyNameAttribute()
    {
        return $this->ownerSetting?->pharmacy_name ?? 'Unknown Pharmacy';
    }

    public function getPharmacyLogoAttribute()
    {
        return $this->ownerSetting?->logo ?? null;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->pharmacy_completed_at && $this->customer_completed_at;
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'declined']);
    }

    public function isActive(): bool
    {
        return !in_array($this->status, ['cancelled', 'declined', 'completed']);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'order_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }
}

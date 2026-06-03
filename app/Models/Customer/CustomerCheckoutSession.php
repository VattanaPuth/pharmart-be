<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Customer\Order;

class CustomerCheckoutSession extends Model
{
    protected $table = 'customer_checkout_sessions';

    protected $fillable = [
        'customer_id',
        'items',
        'subtotal',
        'fulfillment_method',
        'payment_method',
        'delivery_address',
        'status',
        'expires_at'
    ];

    protected $casts = [
        'items' => 'array',
        'expires_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'checkout_session_id', 'id');
    }
}
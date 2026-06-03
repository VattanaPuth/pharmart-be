<?php

namespace App\Models\Owner;
use App\Models\Customer\Order;
use App\Models\Customer\Customer;
use App\Models\Customer\Refund;
use App\Models\Owner\OwnerProduct;

use Illuminate\Database\Eloquent\Model;

class OwnerNotification extends Model
{
    protected $fillable = [
        'owner_id',
        'customer_id',
        'order_id',
        'refund_id',
        'product_id',
        'type',
        'title',
        'message',
        'data',
        'channels',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'channels' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    public function product()
    {
        return $this->belongsTo(OwnerProduct::class);
    }
}
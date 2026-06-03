<?php

namespace App\Models\Notification;

use App\Enums\Notification\NotificationType;
use App\Models\Customer\Customer;
use App\Models\Customer\Order;
use App\Models\Customer\Refund;
use App\Models\Owner\Owner;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'customer_notifications';

    protected $fillable = [
        'customer_id',
        'order_id',
        'refund_id',
        'owner_id',
        'product_id',
        'type',
        'title',
        'message',
        'target_role',
        'is_read',
        
        'read_at',
    ];

    protected $casts = [
        'type'     => NotificationType::class,
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }
}

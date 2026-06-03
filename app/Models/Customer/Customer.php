<?php

namespace App\Models\Customer;

use App\Models\Auth\Register;
use App\Models\Notification\Notification;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customer';

    protected $fillable = ['register_id', 'phone'];

    public function register()
    {
        return $this->belongsTo(Register::class, 'register_id', 'id');
    }

    public function information()
    {
        return $this->hasOne(Information::class, 'customer_id', 'id');
    }

    public function deliveryAddresses()
    {
        return $this->hasMany(DeliveryAddress::class, 'customer_id', 'id');
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'customer_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id', 'id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'customer_id', 'id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'customer_id', 'id');
    }
}

<?php


namespace App\Models\Customer;

use App\Models\Owner\OwnerProduct;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $table = 'product_reviews';

    protected $fillable = [
        'customer_id',
        'order_id',
        'product_id',
        'rating',
        'review',
        'created_at'
    ];

    public function product()
    {
        return $this->belongsTo(OwnerProduct::class, 'product_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(
            \App\Models\Customer\Information::class,
            'customer_id',
            'customer_id'
        );
    }

    public function ownerSetting()
    {
        return $this->belongsTo(
            \App\Models\Owner\OwnerSetting::class,
            'owner_id',
            'owner_id'
        );
    }
}

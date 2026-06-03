<?php

namespace App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customer\Refund;

class RefundImage extends Model
{
    protected $table = 'refund_images';

    protected $fillable = [
        'refund_id',
        'image_path',
        'uploaded_by_id',
         'uploaded_by_type'
    ];

    // =========================
    // RELATION
    // =========================
        public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function isCustomerUpload()
    {
        return $this->uploaded_by_type === 'customer';
    }

    public function isPharmacyUpload()
    {
        return $this->uploaded_by_type === 'pharmacy';
    }
}
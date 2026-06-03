<?php

namespace App\Models\Owner;

use App\Models\Auth\Register;
use App\Models\Customer\Invoice;
use App\Models\Customer\Refund;
use App\Models\Ekyc\OwnerEkyc;
use Illuminate\Database\Eloquent\Model;

class Owner extends Model
{
    protected $table = 'owner';

    protected $fillable = ['register_id', 'phone'];

    public function register()
    {
        return $this->belongsTo(Register::class, 'register_id', 'id');
    }

    public function ekyc()
    {
        return $this->hasOne(OwnerEkyc::class, 'owner_id', 'id');
    }

    public function setting()
    {
        return $this->hasOne(OwnerSetting::class, 'owner_id', 'id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'owner_id', 'id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'owner_id', 'id');
    }


}

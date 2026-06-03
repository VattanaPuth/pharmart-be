<?php
namespace App\Models\Ekyc;

use App\Models\Owner\Owner;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ekyc\EkycFaceVerification;
use App\Models\Owner\PharmacyDocument;

class OwnerEkyc extends Model
{
    protected $table = 'owner_ekyc';

    protected $fillable = [
        'owner_id',

        'owner_name',
        'pharmacy_name',
        'date_of_birth',

        'full_address',
        'city',
        'phone_number',
        'email',
        'selfie_url',

        'status',
        'review_message',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function setting()
    {
        return $this->hasOne(
            \App\Models\Owner\OwnerSetting::class,
            'owner_id',
            'owner_id'
        );
    }

    // 🔥 NEW: documents relation
    public function documents()
    {
        return $this->hasMany(
            PharmacyDocument::class,
            'ekyc_id'
        );
    }

    // 🔥 NEW: face verification history
    public function faceVerifications()
    {
        return $this->hasMany(
            \App\Models\Ekyc\EkycFaceVerification::class,
            'ekyc_id'
        );
    }
}
<?php

namespace App\Models\Auth;

use App\Models\Customer\Customer;
use App\Models\Owner\Owner;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Register extends Authenticatable implements JWTSubject
{
    protected $table = 'registers';

protected $fillable = [
    'phone',
    'email',
    'role',
    'phone_verified_at',
    'oauth_provider',
    'oauth_provider_id',
    'onboarding_completed',
];

    public function owner()
    {
        return $this->hasOne(Owner::class, 'register_id', 'id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'register_id', 'id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'ekyc_status' => $this->role === 'OWNER' 
            ? ($this->owner?->ekyc?->status ?? 'review_pending') 
            : 'n/a',
        ];
    }
}

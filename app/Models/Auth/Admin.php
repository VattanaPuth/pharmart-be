<?php

namespace App\Models\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Admin\AdminNotification;

class Admin extends Authenticatable implements JWTSubject
{
    protected $table = 'admin';
    protected $guard = 'admin';

    protected $fillable = [
        'admin_name',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    // Optional computed field
    protected $appends = ['role'];

    public function getRoleAttribute(): string
    {
        return 'ADMIN';
    }

    /*
    |-----------------------------
    | JWT REQUIRED METHODS
    |-----------------------------
    */

    public function getJWTIdentifier()
    {
        return $this->getKey(); // admin.id
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => 'ADMIN',
        ];
    }

    /*
    |-----------------------------
    | RELATIONS
    |-----------------------------
    */

    public function notifications()
    {
        return $this->hasMany(AdminNotification::class, 'admin_id');
    }
}
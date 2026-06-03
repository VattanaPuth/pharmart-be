<?php

namespace App\Models\Admin;

use App\Models\Auth\Admin;
use App\Models\Ekyc\OwnerEkyc;
use App\Models\Owner\Owner;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $table = 'admin_notification';

    protected $fillable = [
        'admin_id',
        'owner_id',
        'ekyc_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function ekyc()
    {
        return $this->belongsTo(OwnerEkyc::class, 'ekyc_id');
    }
}

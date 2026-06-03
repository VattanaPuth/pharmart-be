<?php

namespace App\Models\Owner;

use Illuminate\Database\Eloquent\Model;

class PharmacyDocument extends Model
{
    protected $table = 'pharmacy_documents';
    
    protected $fillable = [
        'owner_id',
        'ekyc_id',
        'document_type',
        'file_url',
        'status',
        'review_message',
        'reviewed_at'
    ];
}
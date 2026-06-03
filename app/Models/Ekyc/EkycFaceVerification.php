<?php
namespace App\Models\Ekyc;

use Illuminate\Database\Eloquent\Model;

class EkycFaceVerification extends Model
{
    protected $table = 'ekyc_face_verifications';

    protected $fillable = [
        'owner_id',
        'ekyc_id',
        'score',
        'threshold',
        'passed',
    ];

    protected $casts = [
        'score' => 'float',
        'threshold' => 'float',
        'passed' => 'boolean',
    ];
}
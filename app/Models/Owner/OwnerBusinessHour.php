<?php

namespace App\Models\Owner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerBusinessHour extends Model
{
	protected $table = 'owner_business_hour';

	protected $fillable = [
		'owner_setting_id',
		'day_of_week',
		'open_time',
		'close_time',
		'is_open',
	];

	protected $casts = [
		'is_open' => 'boolean',
	];

	public function ownerSetting(): BelongsTo
	{
		return $this->belongsTo(OwnerSetting::class, 'owner_setting_id');
	}

}

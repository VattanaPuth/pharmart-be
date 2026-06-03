<?php

namespace App\Models\Owner;

use Illuminate\Database\Eloquent\Model;
use App\Models\Owner\PharmacyDocument;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnerSetting extends Model
{
	protected $table = 'owner_setting';

	protected $fillable = [
		'owner_id',
		'pharmacy_name',
		'owner_name',
		'address',
		'city',
		'gps_location',
		'phone_number',
		'displayable_email',
		'logo',
		'notification_enabled',
		'low_stock_alert',
		'status',
		'latitude',
		'longitude'
	];

	protected $casts = [
		'notification_enabled' => 'boolean',
		'low_stock_alert' => 'integer',
	];

	public function owner(): BelongsTo
	{
		return $this->belongsTo(Owner::class);
	}

	public function businessHours(): HasMany
	{
		return $this->hasMany(OwnerBusinessHour::class, 'owner_setting_id');
	}

	public function ekyc()
	{
		return $this->hasOne(
			\App\Models\Ekyc\OwnerEkyc::class,
			'owner_id',
			'owner_id'
		);
	}

	public function documents()
	{
		return $this->hasMany(PharmacyDocument::class, 'owner_id', 'owner_id');
	}
}

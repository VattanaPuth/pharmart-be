<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class DeliveryAddress extends Model
{
	protected $table = 'customer_delivery_address';

	protected $fillable = [
		'customer_id',
		'label',
		'recipient_name',
		'phone_number',
		'full_address',
		'city',
		'google_map_link',
		'is_default',
		'latitude',
		'longitude'

	];

	public function customer()
	{
		return $this->belongsTo(Customer::class, 'customer_id', 'id');
	}

}

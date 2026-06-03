<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class Information extends Model
{
	protected $table = 'customer_information';

	protected $fillable = [
		'customer_id',
		'customer_name',
		'phone_number',
		'email',
	];

	public function customer()
	{
		return $this->belongsTo(Customer::class, 'customer_id', 'id');
	}

}

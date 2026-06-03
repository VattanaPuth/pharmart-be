<?php

namespace App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'category';
    protected $fillable = ['name', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function subcategories(): HasMany
    {
        return $this->hasMany(SubCategory::class);
    }

    // Only categories that are active
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

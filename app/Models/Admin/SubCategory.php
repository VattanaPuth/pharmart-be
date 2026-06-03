<?php

namespace App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubCategory extends Model
{
    protected $table = 'subcategory';
    protected $fillable = ['category_id', 'name', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Only subcategories that are active AND whose category is active
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('active', true)
            ->whereHas('category', fn ($q) => $q->where('active', true));
    }
}

<?php

namespace App\Models\Owner;

use App\Models\Admin\Category;
use App\Models\Admin\SubCategory;
use App\Models\Owner\OwnerPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class OwnerProduct extends Model
{
    protected $table = 'owner_product';

    protected $fillable = [
        'owner_id',
        'product_name',
        'generic_name',
        'strength',
        'form',
        'expiry_date',
        'category_id',
        'subcategory_id',
        'main_image',
        'description',
        'status',
        'is_featured',
        'featured_rank',
        'featured_from',
        'featured_till',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'featured_from' => 'datetime',
        'featured_till' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class, 'subcategory_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(OwnerPackage::class, 'owner_product_id');
    }
    
public function scopeVisible(Builder $query): Builder
{
    return $query
        ->where('owner_product.status', true)

        ->where(function ($q) {
            $q->whereNull('category_id')
              ->orWhereHas('category', function (Builder $cat) {
                  $cat->where('active', true);
              });
        })

        ->where(function (Builder $q): void {
            $q->whereNull('subcategory_id')
              ->orWhereHas('subCategory', function (Builder $sq) {
                  $sq->where('active', true);
              });
        });
}

    public function scopeFeatured(Builder $query): Builder
    {
        return $query
            ->where('is_featured', true)
            ->where(function ($q) {
                $q->whereNull('featured_from')
                    ->orWhere('featured_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('featured_till')
                    ->orWhere('featured_till', '>=', now());
            })
            ->orderBy('featured_rank', 'asc');
    }

    public function defaultPackage()
    {
        return $this->hasOne(OwnerPackage::class, 'owner_product_id')
            ->where('is_default', true);
    }

    public function orderItems()
    {
        return $this->hasMany(
            \App\Models\Customer\OrderItems::class,
            'product_id'
        );
    }

    public function reviews()
    {
        return $this->hasMany(\App\Models\Customer\ProductReview::class, 'product_id');
    }

    public function getAverageRatingAttribute()
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

    public function getReviewCountAttribute()
    {
        return $this->reviews()->count();
    }

    public function ownerSetting()
    {
        return $this->belongsTo(
            \App\Models\Owner\OwnerSetting::class,
            'owner_id',
            'owner_id'
        );
    }
}

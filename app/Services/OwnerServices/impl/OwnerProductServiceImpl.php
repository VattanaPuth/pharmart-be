<?php

namespace App\Services\OwnerServices\impl;

use App\Models\Admin\Category;
use App\Models\Admin\SubCategory;
use App\Models\Owner\OwnerProduct;
use App\Services\OwnerServices\OwnerProductService;
use App\Specifications\OwnerProduct\impl\OwnerProductFilterSpecification;
use App\Specifications\OwnerProduct\impl\OwnerProductSearchSpecification;
use App\Specifications\OwnerProduct\impl\OwnerProductSortSpecification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Models\Ekyc\OwnerEkyc;
use App\Models\Owner\OwnerPackage;
use Illuminate\Support\Facades\Log;

class OwnerProductServiceImpl implements OwnerProductService
{

    public function visible(array $specification = [], ?int $ownerId = null): LengthAwarePaginator
    {


        $query = OwnerProduct::visible()
            ->whereHas('owner.ekyc', function ($q) {
                $q->where('status', 'approved');
            })
            ->with([
                'category',
                'subCategory',
                'packages',
                'owner.setting'
            ])
            ->withCount([
                'reviews as review_count'
            ])
            ->withAvg([
                'reviews as average_rating'
            ], 'rating')
            ->leftJoin('owner_setting as os', 'os.owner_id', '=', 'owner_product.owner_id')
            ->select('owner_product.*');

        // =====================================
        // OWNER FILTER
        // =====================================
        $productCount = OwnerProduct::count();

        Log::info('TOTAL PRODUCTS', [
            'count' => $productCount
        ]);

        $approvedCount = OwnerProduct::visible()
            ->whereHas('owner.ekyc', function ($q) {
                $q->where('status', 'approved');
            })
            ->count();



        Log::info('APPROVED PRODUCTS', [
            'count' => $approvedCount
        ]);

        // $products = OwnerProduct::with(['owner.ekyc'])
        //     ->get();

        // Log::info('DEBUG OWNER EKYC', [
        //     $products->map(function ($p) {
        //         return [
        //             'product_id' => $p->id,
        //             'owner_id' => $p->owner_id,
        //             'ekyc_status' => $p->owner?->ekyc?->status,
        //         ];
        //     })
        // ]);


        if ($ownerId !== null) {
            $query->where('owner_product.owner_id', $ownerId);
        }

        // =====================================
        // CATEGORY FILTER
        // =====================================
        $categoryId = $specification['category_id'] ?? null;

        if ($categoryId !== null && $categoryId !== 'all') {

            // ---------------------------------
            // UNCATEGORIZED
            // ---------------------------------
            if (
                $categoryId === 'uncategorized' ||
                $categoryId === 'null'
            ) {

                $query->where(function ($q) {

                    // category_id IS NULL
                    $q->whereNull('owner_product.category_id')

                        // OR inactive category
                        ->orWhereHas('category', function ($cat) {
                            $cat->where('active', 0);
                        });
                });
            }

            // ---------------------------------
            // NORMAL CATEGORY
            // ---------------------------------
            else {

                $query->where(
                    'owner_product.category_id',
                    $categoryId
                )
                    ->whereHas('category', function ($cat) {
                        $cat->where('active', 1);
                    });
            }
        }


        $lat = $specification['lat'] ?? null;
        $lng = $specification['lng'] ?? null;

        // fallback from logged in user
        $user = request()->user();

        if (
            (is_null($lat) || is_null($lng))
            && $user
            && $user->customer
        ) {

            $address = DB::table('customer_delivery_address')
                ->where('customer_id', $user->customer->id)
                ->first();

            if ($address) {
                $lat = $address->latitude ?? null;
                $lng = $address->longitude ?? null;
            }
        }

        // -----------------------------
        // CHECK IF DISTANCE SORT
        // -----------------------------
        $sortBy = $specification['sort_by'] ?? null;
        $isDistanceSort = $sortBy === 'distance';

        // -----------------------------
        // ADD DISTANCE FIELD
        // -----------------------------


        if (!is_null($lat) && !is_null($lng)) {

            $query->selectRaw("
            ROUND(
                6371 * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(os.latitude)) *
                    COS(RADIANS(os.longitude) - RADIANS(?)) +
                    SIN(RADIANS(?)) * SIN(RADIANS(os.latitude))
                ), 2
            ) AS distance
        ", [$lat, $lng, $lat]);

            // ONLY SORT WHEN DISTANCE SORT
            if ($isDistanceSort) {
                $query->orderByRaw('distance IS NULL, distance ASC');
            }
        }

        // -----------------------------
        // PAGINATION
        // -----------------------------
        $perPage = (int) ($specification['per_page'] ?? 10);

        $specifications = [
            new OwnerProductSearchSpecification(
                $specification['q'] ?? null
            ),

            // REMOVE category filter from specification
            // since handled directly above

            new OwnerProductSortSpecification(
                $specification['sort_by'] ?? null,
                $specification['sort_dir'] ?? null
            ),
        ];

        foreach ($specifications as $item) {

            // Skip default sort when using distance
            if (
                $item instanceof \App\Specifications\OwnerProduct\impl\OwnerProductSortSpecification
                && $isDistanceSort
            ) {
                continue;
            }

            $item->apply($query);
        }

        // -----------------------------
        // PAGINATION
        // -----------------------------
        $products = $query
            ->paginate($perPage)
            ->appends($specification);

        // -----------------------------
        // TRANSFORM
        // -----------------------------
        $products->getCollection()->transform(function ($product) {

            $defaultPackage = $product->packages
                ->firstWhere('is_default', 1)
                ?? $product->packages->first();

            $product->store_name =
                $product->owner?->setting?->pharmacy_name
                ?? 'Unknown Store';

            // DISTANCE
            $product->distance = $product->distance ?? null;

            // PRICE
            $product->price = $defaultPackage?->price ?? 0;

            // PACKAGE NAME
            $product->package_name =
                $defaultPackage?->package_name ?? 'N/A';

            // STOCK
            $product->stock_quantity =
                $defaultPackage?->stock_quantity ?? 0;

            $product->average_rating = round(
                $product->average_rating ?? 0,
                1
            );

            $product->review_count =
                $product->review_count ?? 0;

            return $product;
        });

        return $products;
    }


    private function validateCategorySubcategoryPair(array $data, ?OwnerProduct $product = null): void
    {
        $categoryId = array_key_exists('category_id', $data)
            ? $data['category_id']
            : $product?->category_id;

        $subcategoryId = array_key_exists('subcategory_id', $data)
            ? $data['subcategory_id']
            : $product?->subcategory_id;

        // ✅ CASE 1: both null → allowed
        if ($categoryId === null && $subcategoryId === null) {
            return;
        }

        // ✅ CASE 2: category optional → ONLY validate subcategory if it exists
        if ($subcategoryId !== null) {

            $subcategory = SubCategory::find($subcategoryId);

            if (
                !$subcategory ||
                !$subcategory->active
            ) {
                throw ValidationException::withMessages([
                    'subcategory_id' => ['Invalid subcategory.'],
                ]);
            }

            // ONLY enforce relation if category exists
            if (
                $categoryId !== null &&
                (int)$subcategory->category_id !== (int)$categoryId
            ) {
                throw ValidationException::withMessages([
                    'subcategory_id' => ['Subcategory does not belong to category.'],
                ]);
            }
        }

        // ✅ CASE 3: validate category only if provided
        if ($categoryId !== null) {

            $category = Category::find($categoryId);

            if (!$category || !$category->active) {
                throw ValidationException::withMessages([
                    'category_id' => ['Invalid category.'],
                ]);
            }
        }
    }


    private function checkEkycStatus(int $ownerId)
    {
        $status = OwnerEkyc::where('owner_id', $ownerId)->value('status');

        if ($status !== 'approved') {
            abort(403, "Action denied. Your eKYC status is: " . ($status ?? 'pending'));
        }
    }


    public function addProduct(int $ownerId, array $data): OwnerProduct
    {
        $this->checkEkycStatus($ownerId);


        $packages = $data['packages'] ?? null;
        unset($data['packages']);

        $data['owner_id'] = $ownerId;
        $data['status'] = $data['status'] ?? true;

        $this->validateCategorySubcategoryPair($data);

        if (isset($data['main_image'])) {
            $data['main_image'] = $data['main_image']->store('products', 'public');
        }

        return DB::transaction(function () use ($data, $packages) {

            // 🔴 VALIDATE PACKAGES
            if (empty($packages) || !is_array($packages)) {
                throw ValidationException::withMessages([
                    'packages' => ['Package data is required and cannot be empty.']
                ]);
            }

            $product = OwnerProduct::create($data);

            $createdPackages = [];

            foreach ($packages as $index => $pkg) {

                try {
                    $createdPackages[] = OwnerPackage::create([
                        'owner_product_id' => $product->id,
                        'package_name'     => $pkg['package_name'] ?? 'Box',
                        'contains'         => $pkg['contains'] ?? null,
                        'price'            => $pkg['price'] ?? 0,
                        'stock_quantity'   => $pkg['stock_quantity'] ?? 0,
                        'low_stock_threshold' => $pkg['low_stock_threshold'] ?? 10,
                        'is_default'       => $index === 0 ? 1 : 0,
                    ]);
                } catch (\Throwable $e) {
                    // 🔴 rollback everything + return clean error
                    throw ValidationException::withMessages([
                        'packages' => [
                            'Failed to create package at index ' . $index . ': ' . $e->getMessage()
                        ]
                    ]);
                }
            }

            return $product->load('packages');
        });
    }

    public function updateProduct(int $ownerId, int $productId, array $data): OwnerProduct
    {
        if (array_key_exists('category_id', $data)) {
            $data['category_id'] =
                $data['category_id'] == "null"
                ? null
                : $data['category_id'];
        }

        if (array_key_exists('subcategory_id', $data)) {
            $data['subcategory_id'] =
                $data['subcategory_id'] == "null"
                ? null
                : $data['subcategory_id'];
        }

        if (array_key_exists('description', $data)) {
            $data['description'] =
                $data['description'] == "null"
                ? null
                : $data['description'];
        }

        $this->checkEkycStatus($ownerId);

        $product = OwnerProduct::query()
            ->where('owner_id', $ownerId)
            ->where('id', $productId)
            ->firstOrFail();

        $this->validateCategorySubcategoryPair($data, $product);

        if (isset($data['main_image'])) {

            if ($product->main_image) {
                Storage::disk('public')->delete($product->main_image);
            }

            $data['main_image'] =
                $data['main_image']->store('products', 'public');
        }

        $packages = $data['packages'] ?? [];
        unset($data['packages']);

        $product->update($data);

        if (!empty($packages)) {

    OwnerPackage::where(
        'owner_product_id',
        $product->id
    )->delete();

    foreach ($packages as $index => $pkg) {

        OwnerPackage::create([
            'owner_product_id'    => $product->id,
            'package_name'        => $pkg['package_name'] ?? 'Box',
            'contains'            => $pkg['contains'] ?? null,
            'price'               => $pkg['price'] ?? 0,
            'stock_quantity'      => $pkg['stock_quantity'] ?? 0,
            'low_stock_threshold' => $pkg['low_stock_threshold'] ?? 10,
            'is_default'          => ($pkg['is_default'] ?? 0) ? 1 : 0,
        ]);
    }
}

return $product->fresh()->load('packages');

        return $product->fresh();
    }

    public function hideProduct(int $ownerId, int $productId): bool
    {
        $this->checkEkycStatus($ownerId);
        $product = OwnerProduct::query()
            ->where('owner_id', $ownerId)
            ->where('id', $productId)
            ->firstOrFail();

        return $product->update([
            'status' => false
        ]);
    }
}

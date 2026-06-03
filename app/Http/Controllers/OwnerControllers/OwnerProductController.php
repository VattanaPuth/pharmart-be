<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use App\Services\OwnerServices\OwnerProductService;
use App\Models\Customer\ProductReview;
use App\Models\Ekyc\OwnerEkyc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Owner\OwnerProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Validation\ValidationException;


class OwnerProductController extends Controller
{
    public function __construct(private OwnerProductService $ownerProductService) {}

    private function currentOwnerId(Request $request): int
    {
        return $request->user()->owner->id;
    }

    public function visible(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'category_id' => 'nullable',
            'subcategory_id' => 'nullable|integer|exists:subcategory,id',
            'sort_by' => 'nullable|in:created_at,updated_at,product_name,expiry_date,price,distance',
            'sort_dir' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'lat' => 'nullable',
            'lng' => 'nullable',
        ]);

        $ownerId = $request->user()?->owner?->id;



        return response()->json($this->ownerProductService->visible($validated, $ownerId));
    }
    

    public function AddProduct(Request $request): JsonResponse
    {
        try {

            Log::info('Add Product Request', [
                'data' => $request->all(),
                'files' => $request->allFiles(),
            ]);

            $validated = $request->validate([
                'product_name' => 'required|string|max:255',
                'generic_name' => 'nullable|string|max:255',
                'strength' => 'nullable|string|max:100',
                'form' => 'nullable|string|max:100',
                'expiry_date' => 'nullable|date',
                'category_id' => 'nullable|exists:category,id',
                'subcategory_id' => 'nullable|exists:subcategory,id',
                'main_image' => 'nullable|image',
                'description' => 'nullable|string',
                'packages' => 'nullable'
            ]);

            Log::info('Validated Product Data', [
                'validated' => $validated
            ]);

            $ownerId = $this->currentOwnerId($request);

            $product = $this->ownerProductService->addProduct(
                $ownerId,
                $validated
            );

            return response()->json([
                'message' => 'Product created successfully',
                'data' => $product
            ]);
        } catch (ValidationException $e) {

            Log::error('Product Validation Failed', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);

            throw $e;
        } catch (Throwable $e) {

            Log::error('Add Product Failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'message' => 'Server Error'
            ], 500);
        }
    }


    public function updateProduct(Request $request, int $productId): JsonResponse
    {
        Log::info('UPDATE PRODUCT REQUEST RAW', [
            'product_id' => $productId,
            'user_id' => $request->user()?->id,
            'payload' => $request->all(),
        ]);



        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'strength' => 'nullable|string|max:100',
            'form' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'category_id' => 'nullable|exists:category,id',
            'subcategory_id' => 'nullable|exists:subcategory,id',
            'main_image' => 'nullable|image',
            'description' => 'nullable|string',
            'packages' => 'nullable|array',
            'packages.*.package_name' => 'required|string',
            'packages.*.contains' => 'nullable|string',
            'packages.*.price' => 'required|numeric',
            'packages.*.stock_quantity' => 'required|integer',
            'packages.*.low_stock_threshold' => 'nullable|integer',
            'packages.*.is_default' => 'nullable|boolean',
        ]);

        Log::info('UPDATE PRODUCT VALIDATED DATA', [
            'validated' => $validated,
        ]);

        $ownerId = $this->currentOwnerId($request);

        Log::info('OWNER ID RESOLVED', [
            'owner_id' => $ownerId,
        ]);

        try {
            $product = $this->ownerProductService->updateProduct(
                $ownerId,
                $productId,
                $validated
            );

            Log::info('PRODUCT UPDATE SUCCESS', [
                'product_id' => $productId,
            ]);

            return response()->json([
                'message' => 'Product updated successfully',
                'data' => $product
            ]);
        } catch (Throwable $e) {

            Log::error('PRODUCT UPDATE FAILED', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }


    public function hideProduct(Request $request, int $productId): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        $ekycStatus = OwnerEkyc::where('owner_id', $ownerId)->value('status');

        if ($ekycStatus !== 'approved') {
            return response()->json([
                'data' => [],
                'message' => 'Products are hidden until eKYC is approved.',
                'status' => $ekycStatus
            ]);
        }

        $this->ownerProductService->hideProduct($ownerId, $productId);

        return response()->json([
            'message' => 'Product Delete successfully'
        ]);
    }

    public function featured(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $products = \App\Models\Owner\OwnerProduct::query()
            ->with(['owner.setting', 'category', 'packages'])
            // Only include products where the owner's eKYC is approved
            ->whereHas('owner.ekyc', function ($query) {
                $query->where('status', 'approved');
            })
            ->featured()
            ->orderBy('featured_rank')
            ->limit($limit)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->product_name,
                    'price' => optional($p->packages->first())->price ?? 0,
                    'image' => $p->main_image,
                    'pharmacy' => $p->owner?->setting?->pharmacy_name ?? 'Unknown',
                    'category' => $p->category?->name,
                ];
            });

        return response()->json([
            'data' => $products
        ]);
    }



    public function topNearby(Request $request): JsonResponse
    {
        $user = $request->user();

        $lat = null;
        $lng = null;

        // =========================
        // 1. GET USER LOCATION
        // =========================
        if ($user && $user->customer) {

            $address = DB::table('customer_delivery_address')
                ->where('customer_id', $user->customer->id)
                ->where('id', $request->address_id)
                ->first();



            if ($address) {
                $lat = $address->latitude ?? null;
                $lng = $address->longitude ?? null;
            }
        }

        // =========================
        // 2. BASE QUERY
        // =========================
        $query = DB::table('owner as o')
            ->selectRaw("
                o.id,
                os.pharmacy_name,
                os.latitude,
                os.longitude,
                COUNT(DISTINCT co.id) as total_sales
            ")
            ->join('owner_setting as os', 'os.owner_id', '=', 'o.id')
            ->join('owner_ekyc as e', 'e.owner_id', '=', 'o.id')
            ->where('e.status', '=', 'approved')
            ->leftJoin('customer_order as co', function ($join) {
                $join->on('co.owner_id', '=', 'o.id')
                    ->where('co.status', 'complete');
            });
        // =========================
        // 3. SELECT + DISTANCE
        // =========================
        if (!is_null($lat) && !is_null($lng)) {

            $query->selectRaw("
            o.id,
            os.pharmacy_name,
            os.gps_location,
            os.latitude,
            os.longitude,
            COUNT(co.id) as total_sales,

            ROUND(
                6371 * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(os.latitude)) *
                    COS(RADIANS(os.longitude) - RADIANS(?)) +
                    SIN(RADIANS(?)) * SIN(RADIANS(os.latitude))
                ),
            2) AS distance
        ", [$lat, $lng, $lat]);
        } else {

            $query->selectRaw("
            o.id,
            os.pharmacy_name,
            os.gps_location,
            os.latitude,
            os.longitude,
            COUNT(co.id) as total_sales,
            NULL AS distance
        ");
        }

        // =========================
        // 4. GROUP BY
        // =========================
        $query->groupBy(
            'o.id',
            'os.pharmacy_name',
            'os.gps_location',
            'os.latitude',
            'os.longitude'
        );

        // =========================
        // 5. FILTER + SORT
        // =========================
        if (!is_null($lat) && !is_null($lng)) {
            $query->having('distance', '<=', 10)
                ->orderBy('distance')
                ->orderByDesc('total_sales');
        } else {
            $query->orderByDesc('total_sales');
        }

        // =========================
        // 6. LIMIT
        // =========================
        $pharmacies = $query->limit(3)->get();

        return response()->json([
            'data' => $pharmacies
        ]);
    }

    public function trending(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $products = DB::table('customer_order_items as oi')
            ->join('customer_order as o', function ($join) {
                $join->on('o.id', '=', 'oi.order_id')
                    ->where('o.status', '=', 'complete');
            })
            ->join('owner_product as p', 'p.id', '=', 'oi.product_id')
            // ✅ Join ekyc table to check owner approval status
            ->join('owner_ekyc as e', 'e.owner_id', '=', 'p.owner_id')
            ->leftJoin('owner_package as pkg', function ($join) {
                $join->on('pkg.owner_product_id', '=', 'p.id')
                    ->where('pkg.is_default', 1);
            })
            ->whereBetween('o.created_at', [$startOfWeek, $endOfWeek])
            // ✅ Filter for approved status only
            ->where('e.status', '=', 'approved')
            ->select(
                'p.id',
                'p.product_name as name',
                'p.main_image as image',
                DB::raw('COALESCE(pkg.price, 0) as price'),
                DB::raw('SUM(oi.quantity) as total_sold')
            )
            ->groupBy(
                'p.id',
                'p.product_name',
                'p.main_image',
                'pkg.price'
            )
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $products
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $product = OwnerProduct::query()
            ->whereHas('owner.ekyc', function ($q) {
                $q->where('status', 'approved');
            })
            ->select('id', 'product_name', 'generic_name', 'strength', 'form', 'description', 'expiry_date', 'main_image', 'owner_id', 'category_id')
            ->with([
                'owner.setting:owner_id,pharmacy_name,latitude,longitude',
                'category:id,name',
                'packages:id,owner_product_id,package_name,price,is_default,stock_quantity',
                'reviews' => function ($q) {
                    $q->whereNotNull('rating')
                        ->whereNotNull('review')
                        ->latest();
                }
            ])
            ->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // =========================
        // USER LOCATION (same logic reused)
        // =========================
        $user = $request->user();

        $lat = null;
        $lng = null;

        if ($user && $user->customer) {

            $address = DB::table('customer_delivery_address')
                ->where('customer_id', $user->customer->id)
                // prioritize default address first
                ->orderByDesc('is_default')
                // fallback to most recently added
                ->orderByDesc('id')
                ->first();

            if ($address) {
                $lat = $address->latitude;
                $lng = $address->longitude;
            }
        }

        // =========================
        // PHARMACY LOCATION
        // =========================
        $pharmacyLat = $product->owner?->setting?->latitude;
        $pharmacyLng = $product->owner?->setting?->longitude;

        $distance = null;

        if ($lat && $lng && $pharmacyLat && $pharmacyLng) {
            $distance = round(
                6371 * acos(
                    cos(deg2rad($lat)) *
                        cos(deg2rad($pharmacyLat)) *
                        cos(deg2rad($pharmacyLng) - deg2rad($lng)) +
                        sin(deg2rad($lat)) *
                        sin(deg2rad($pharmacyLat))
                ),
                2
            );
        }

        // =========================
        // DEFAULT PACKAGE
        // =========================
        $defaultPackage = $product->packages->firstWhere('is_default', true)
            ?? $product->packages->first();

        return response()->json([
            'data' => [
                'id' => $product->id,
                'owner_id' => $product->owner_id,
                'name' => $product->product_name,
                'generic_name' => $product->generic_name,
                'strength' => $product->strength,
                'form' => $product->form,
                'description' => $product->description,
                'expiry_date' => $product->expiry_date,
                'image' => $product->main_image,
                'category' => $product->category?->name,
                'pharmacy' => $product->owner?->setting?->pharmacy_name,

                'distance_km' => $distance,

                'packages' => $product->packages,
                'default_package' => $defaultPackage,

                // ✅ ADD THESE
                'average_rating' => $product->average_rating,
                'review_count' => $product->review_count,
                'reviews' => $product->reviews->map(function ($r) {
                    return [
                        'rating' => $r->rating,
                        'review' => $r->review,
                        'customer_name' => $r->order->customer->name ?? 'Anonymous',
                        'created_at' => $r->created_at,
                    ];
                }),
            ]
        ]);
    }

    public function listPharmacies(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 50);
        $maxDistance = $request->input('max_distance');
        $sort = $request->input('sort', 'nearest');
        $search = $request->input('q');

        $user = $request->user();

        $lat = $request->lat;
        $lng = $request->lng;

        if (!$lat || !$lng) {
            if ($user && $user->customer) {
                $address = DB::table('customer_delivery_address')
                    ->where('customer_id', $user->customer->id)
                    ->orderByDesc('is_default')
                    ->first();

                if ($address) {
                    $lat = $address->latitude;
                    $lng = $address->longitude;
                }
            }
        }

        // =========================
        // BASE QUERY
        // =========================
        $query = DB::table('owner as o')
            ->join('owner_setting as os', 'os.owner_id', '=', 'o.id')
            ->join('owner_ekyc as e', 'e.owner_id', '=', 'o.id')
            ->where('e.status', '=', 'approved')
            ->leftJoin('owner_product as p', 'p.owner_id', '=', 'o.id')
            ->leftJoin('customer_order as co', function ($join) {
                $join->on('co.owner_id', '=', 'o.id')
                    ->where('co.status', '=', 'complete');
            });

        // =========================
        // SEARCH
        // =========================
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('os.pharmacy_name', 'like', "%$search%")
                    ->orWhere('os.address', 'like', "%$search%");
            });
        }

        // =========================
        // SELECT (NO ANY_VALUE FIX)
        // =========================
        $query->select(
            'o.id',
            'os.logo',
            'os.pharmacy_name',
            'os.address',
            'os.city',
            'os.latitude',
            'os.longitude',
            DB::raw('COUNT(DISTINCT p.id) as total_products'),
            DB::raw('COUNT(DISTINCT co.id) as total_sales')
        );

        // =========================
        // DISTANCE
        // =========================
        if (!is_null($lat) && !is_null($lng)) {
            $query->selectRaw("
            ROUND(
                6371 * ACOS(
                    COS(RADIANS(?)) *
                    COS(RADIANS(os.latitude)) *
                    COS(RADIANS(os.longitude) - RADIANS(?)) +
                    SIN(RADIANS(?)) *
                    SIN(RADIANS(os.latitude))
                ),
            2) AS distance
        ", [$lat, $lng, $lat]);
        } else {
            $query->selectRaw("NULL as distance");
        }

        // =========================
        // GROUP BY (FIXED)
        // =========================
        $query->groupBy(
            'o.id',
            'os.logo',
            'os.pharmacy_name',
            'os.address',
            'os.city',
            'os.latitude',
            'os.longitude'
        );

        // =========================
        // FILTER DISTANCE
        // =========================
        // if ($maxDistance && $lat && $lng) {
        //     $query->having('distance', '<=', $maxDistance);
        // }
        if (
            $maxDistance &&
            !is_null($lat) &&
            !is_null($lng)
        ) {
            $query->having('distance', '<=', $maxDistance);
        }

        // =========================
        // SORT
        // =========================
        if (
            $sort === 'nearest' &&
            !is_null($lat) &&
            !is_null($lng)
        ) {

            $query->orderBy('distance', 'asc');
        } elseif ($sort === 'name_asc') {

            $query->orderBy('os.pharmacy_name', 'asc');
        } elseif ($sort === 'name_desc') {

            $query->orderBy('os.pharmacy_name', 'desc');
        } elseif ($sort === 'popularity') {

            $query->orderByDesc('total_sales');
        } else {

            $query->orderBy('o.id', 'desc');
        }

        // =========================
        // PAGINATION
        // =========================
        return response()->json([
            'data' => $query->paginate($perPage)
        ]);
    }


    public function pharmacyDetails(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // =========================
        // 1. GET USER LOCATION
        // =========================
        $lat = $request->lat;
        $lng = $request->lng;

        if (!$lat || !$lng) {
            if ($user && $user->customer) {
                $address = DB::table('customer_delivery_address')
                    ->where('customer_id', $user->customer->id)
                    ->orderByDesc('is_default')
                    ->first();

                if ($address) {
                    $lat = $address->latitude;
                    $lng = $address->longitude;
                }
            }
        }

        // =========================
        // 2. GET PHARMACY
        // =========================
        $pharmacy = DB::table('owner as o')
            ->join('owner_setting as os', 'os.owner_id', '=', 'o.id')
            ->where('o.id', $id)
            ->select(
                'o.id',
                'os.pharmacy_name',
                'os.logo',
                'os.address',
                'os.city',
                'os.phone_number',
                'os.latitude',
                'os.longitude'
            )
            ->first();

        if (!$pharmacy) {
            return response()->json([
                'message' => 'Pharmacy not found'
            ], 404);
        }

        // =========================
        // 3. DISTANCE
        // =========================
        $distance = null;

        if ($lat && $lng && $pharmacy->latitude && $pharmacy->longitude) {
            $distance = round(
                6371 * acos(
                    cos(deg2rad($lat)) *
                        cos(deg2rad($pharmacy->latitude)) *
                        cos(deg2rad($pharmacy->longitude) - deg2rad($lng)) +
                        sin(deg2rad($lat)) *
                        sin(deg2rad($pharmacy->latitude))
                ),
                2
            );
        }

        // =========================
        // 4. GOOGLE MAP LINK
        // =========================
        $gpsLink = null;

        if ($pharmacy->latitude && $pharmacy->longitude) {
            $gpsLink = "https://www.google.com/maps?q={$pharmacy->latitude},{$pharmacy->longitude}";
        }

        // =========================
        // 5. WORKING HOURS
        // =========================
        $hours = DB::table('owner_business_hour')
            ->where('owner_setting_id', function ($q) use ($id) {
                $q->select('id')
                    ->from('owner_setting')
                    ->where('owner_id', $id)
                    ->limit(1);
            })
            ->select('day_of_week', 'open_time', 'close_time', 'is_open')
            ->orderByRaw("
            FIELD(day_of_week, 
                'monday','tuesday','wednesday',
                'thursday','friday','saturday','sunday'
            )
        ")
            ->get();

        // =========================
        // 6. COUNTS
        // =========================
        $totalProducts = DB::table('owner_product')
            ->where('owner_id', $id)
            ->count();

        $totalSales = DB::table('customer_order')
            ->where('owner_id', $id)
            ->where('status', 'complete')
            ->count();

        $orderAverages = ProductReview::query()
            ->select(
                'order_id',
                DB::raw('AVG(rating) as order_avg_rating')
            )
            ->whereHas('product', function ($query) use ($id) {
                $query->where('owner_id', $id);
            })
            ->groupBy('order_id')
            ->get();

        // Step 2:
        // Average among all order averages
        $overallAverage = round(
            (float) $orderAverages->avg('order_avg_rating'),
            1
        );

        // Step 3:
        // Distinct reviewed order count
        $totalReviewedOrders = $orderAverages->count();

     
         
        
        // =========================
        // 7. RESPONSE
        // =========================
        return response()->json([
            'data' => [
                'id' => $pharmacy->id,
                'name' => $pharmacy->pharmacy_name,
                'logo' => $pharmacy->logo,
                'address' => $pharmacy->address . ', ' . $pharmacy->city,
                'phone' => $pharmacy->phone_number,

                'gps_location' => $gpsLink,
                'latitude' => $pharmacy->latitude,
                'longitude' => $pharmacy->longitude,

                'distance_km' => $distance,

                'working_hours' => $hours,

                'total_products' => $totalProducts,
                'total_sales' => $totalSales,
                'average_rating'      => $overallAverage,
            'total_review_orders' => $totalReviewedOrders,
            ]
        ]);
    }

    public function productsByOwner(Request $request, int $ownerId): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 12), 50);
        $search = $request->input('q');
        $categoryId = $request->input('category_id');

        $query = DB::table('owner_product as p')
            ->leftJoin('owner_package as pkg', function ($join) {
                $join->on('pkg.owner_product_id', '=', 'p.id')
                    ->where('pkg.is_default', 1);
            })
            ->where('p.owner_id', $ownerId)
            ->where('p.status', 1);

        // =========================
        // 🔍 SEARCH BY NAME
        // =========================
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('p.product_name', 'like', "%$search%")
                    ->orWhere('p.generic_name', 'like', "%$search%");
            });
        }

        // =========================
        // 📂 CATEGORY FILTER
        // =========================
        if ($categoryId) {
            $query->where('p.category_id', $categoryId);
        }

        // =========================
        // SELECT
        // =========================
        $query->select(
            'p.id',
            'p.product_name as name',
            'p.main_image as image',
            'pkg.package_name as package_name',
            DB::raw('COALESCE(pkg.price, 0) as price')
        );

        return response()->json([
            'data' => $query->paginate($perPage)
        ]);
    }
}

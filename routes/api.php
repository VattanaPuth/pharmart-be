<?php

use App\Http\Controllers\AdminControllers\categoryController\CategoryController;
use App\Http\Controllers\AdminControllers\decisionController\EkycReviewController;
use App\Http\Controllers\AdminControllers\notificationController\AdminNotificationController;
use App\Http\Controllers\AdminControllers\subCategoryController\SubCategoryController;
use App\Http\Controllers\AdminControllers\AdminProductReviewController;
use App\Http\Controllers\AdminControllers\AdminPharmacyController;
use App\Http\Controllers\AdminControllers\AdminDashboardController;
use App\Http\Controllers\AuthControllers\adminController\AdminAuthController;
use App\Http\Controllers\AuthControllers\googleController\GoogleController;
use App\Http\Controllers\AuthControllers\loginController\LoginController;
use App\Http\Controllers\AuthControllers\registerController\RegisterController;
use App\Http\Controllers\CustomerControllers\CartController;
use App\Http\Controllers\CustomerControllers\CheckoutSessionController;
use App\Http\Controllers\CustomerControllers\DeliveryAddressController;
use App\Http\Controllers\CustomerControllers\InformationController;
use App\Http\Controllers\CustomerControllers\InvoiceController;
use App\Http\Controllers\CustomerControllers\OrderController;
use App\Http\Controllers\CustomerControllers\PaymentController;
use App\Http\Controllers\CustomerControllers\RefundController;
use App\Http\Controllers\EkycControllers\OwnerEkycController;
use App\Http\Controllers\NotificationControllers\NotificationController;
use App\Http\Controllers\OwnerControllers\OwnerDashboardController;
use App\Http\Controllers\OwnerControllers\OwnerBusinessHourController;
use App\Http\Controllers\OwnerControllers\OwnerOrderController;
use App\Http\Controllers\OwnerControllers\OwnerPackageController;
use App\Http\Controllers\OwnerControllers\OwnerProductController;
use App\Http\Controllers\OwnerControllers\OwnerSettingController;
use App\Http\Controllers\OwnerControllers\OwnerReviewController;
use App\Http\Controllers\OwnerControllers\OwnerNotificationController;
use App\Http\Controllers\OwnerControllers\ReportController;
use App\Http\Controllers\PaymentControllers\StripePaymentController;
use App\Http\Controllers\PaymentControllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Owner\Owner;
use App\Models\Ekyc\OwnerEkyc;



// Public Authentication Routes
Route::prefix('auth')->group(function (): void {
    // Register with phone
    // Send OTP (start registration)
    Route::post('/register/otp/send', [RegisterController::class, 'registerViaOtp']);
    // Resend OTP
    Route::post('/register/otp/resend', [RegisterController::class, 'resendOtp']);
    // Verify OTP
    Route::post('/register/otp/verify', [RegisterController::class, 'registerViaVerifyOtp']);
    // Complete registration
    Route::post('/register/complete', [RegisterController::class, 'registerComplete']);

    // Login with phone
    Route::post('/login/otp/send', [LoginController::class, 'loginViaOtp']);
    Route::post('/login/otp/verify', [LoginController::class, 'loginViaVerifyOtp']);

    // Google
    Route::post('/google/login', [GoogleController::class, 'loginOrRegisterWithGoogle']);
    Route::post('/google/role', [GoogleController::class, 'completeRole']);

    // Admin register and login
    Route::post('/admin/register', [AdminAuthController::class, 'register']);
    Route::post('/admin/login', [AdminAuthController::class, 'login']);
});



Route::middleware('auth:api')->post('/user_logout', function (Request $request) {
    try {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'No token provided'
            ], 200);
        }

        JWTAuth::setToken($token)->invalidate();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Logout failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::middleware('auth:api')->post('/refresh-token', function () {

    $user = auth('api')->user();

    $ekycStatus = null;

    if ($user->role === 'OWNER') {

        $owner = Owner::where('register_id', $user->id)->first();

        if ($owner) {
            $ekyc = OwnerEkyc::where('owner_id', $owner->id)->first();
            $ekycStatus = $ekyc?->status;
        }
    }

    $token = JWTAuth::claims([
        'role' => $user->role,
        'onboarding' => $user->onboarding_completed,
        'ekyc_status' => $ekycStatus,
    ])->fromUser($user);

    return response()->json([
        'token' => $token,
    ]);
});

// Whiteboard Routes - For unregistered users
Route::prefix('public')->group(function (): void {
    Route::get('/categories/read', [CategoryController::class, 'visible']);
    Route::get('/subcategories/read', [SubCategoryController::class, 'visible']);
    Route::get('/products/read', [OwnerProductController::class, 'visible']);
    Route::get('/products/featured', [OwnerProductController::class, 'featured']);
    Route::get('/products/{id}', [OwnerProductController::class, 'show']);
    Route::get('/pharmacies/top', [OwnerProductController::class, 'topNearby']);
    Route::get('/trending-products', [OwnerProductController::class, 'trending']);
    Route::get('/pharmacies/read', [OwnerProductController::class, 'listPharmacies']);

    Route::get('/pharmacies/{id}', [OwnerProductController::class, 'pharmacyDetails']);
    Route::get('/products/by-owner/{ownerId}', [OwnerProductController::class, 'productsByOwner']);
});


Route::middleware('auth:api')->get('/get_user_info', function () {
    $user = auth('api')->user();

    if (!$user) {
        return response()->json(null, 401);
    }

    $customer = null;
    $owner = null;
    $profile = null;
    $address = null;

    // =========================
    // CUSTOMER FLOW
    // =========================
    if ($user->role === 'CUSTOMER') {

        $customer = DB::table('customer')
            ->where('register_id', $user->id)
            ->first();

        if ($customer) {

            $profile = DB::table('customer_information')
                ->where('customer_id', $customer->id)
                ->first();

            $address = DB::table('customer_delivery_address')
                ->where('customer_id', $customer->id)
                ->where('is_default', 1)
                ->first();
        }
    }

    // =========================
    // OWNER FLOW
    // =========================
    if ($user->role === 'OWNER') {

        $owner = DB::table('owner')
            ->where('register_id', $user->id)
            ->first();

        if ($owner) {

            $profile = DB::table('owner_ekyc')
                ->where('owner_id', $owner->id)
                ->first();
        }
    }

    return response()->json([
        'user' => $user,
        'role' => $user->role,

        // unified profile (IMPORTANT)
        'profile' => $profile,

        // role-specific data
        'customer' => $customer,
        'owner' => $owner,
        'address' => $address,

        // UI-ready fields
        'display_name' =>
        $profile->customer_name ??
            $profile->owner_name ??
            $user->email,

        'email' =>
        $profile->email ??
            $user->email,

        'phone' =>
        $profile->phone_number ?? null,
    ]);
});

Route::get('/debug-auth', function () {
    return [
        'check' => auth('api')->check(),
        'user' => auth('api')->user(),
    ];
});


// Middleware: Role & Permission
// Admin
Route::prefix('admin')->middleware(['auth:admin', 'role:ADMIN'])->group(function () {
    // Authentication
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::post('/refresh', [AdminAuthController::class, 'refresh']);
    Route::get('/me', [AdminAuthController::class, 'me']);

    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);

    // eKYC
    Route::get('/ekyc/pending', [EkycReviewController::class, 'index']);
    Route::get('/ekyc/{owner}/images', [EkycReviewController::class, 'getEkycImages']);
    Route::post('/decision/{owner}', [EkycReviewController::class, 'adminDecision']);

    // Pharmacies (Admin)
    Route::get('/pharmacies', [AdminPharmacyController::class, 'index']);
    Route::get('/pharmacies/detail/{ownerId}', [AdminPharmacyController::class, 'show']);
    Route::get('/pharmacies/counts', [AdminPharmacyController::class, 'counts']);
    Route::put('/pharmacies/{owner}/status', [AdminPharmacyController::class, 'updateStatus']);

    // Notifications
    Route::get('/notifications', [AdminNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
    Route::put('/notifications/{notification}/read', [AdminNotificationController::class, 'markRead']);
    Route::put('/notifications/read-all', [AdminNotificationController::class, 'markAllRead']);

    // Categories
    Route::get('/categories/read', [CategoryController::class, 'getAllCategory']);
    Route::get('/categories/{category}', [CategoryController::class, 'getCategoryById']);
    Route::post('/categories', [CategoryController::class, 'addCategory']);
    Route::put('/categories/update/{category}', [CategoryController::class, 'updateCategory']);

    // Subcategories
    Route::get('/categories/{category}/subcategories', [SubCategoryController::class, 'getAllSubCategory']);
    Route::get('/subcategories/{subcategory}', [SubCategoryController::class, 'getSubCategoryById']);
    Route::post('/categories/{category}/subcategories', [SubCategoryController::class, 'addSubCategory']);
    Route::put('/subcategories/{subcategory}', [SubCategoryController::class, 'updateSubCategory']);


    Route::get('/reviews/summary', [AdminProductReviewController::class, 'summary']);
    Route::get('/reviews', [AdminProductReviewController::class, 'index']);
    Route::get('/reviews/{review}', [AdminProductReviewController::class, 'show']);
    Route::delete('/reviews/{review}', [AdminProductReviewController::class, 'destroy']);
});


// Owner
Route::middleware(['auth:api', 'role:OWNER'])->prefix('owners/{owner}/ekyc')->group(function () {
    Route::post('/step1', [OwnerEkycController::class, 'step1']);
    Route::post('/step2', [OwnerEkycController::class, 'step2']);
    Route::post('/selfie', [OwnerEkycController::class, 'uploadSelfie']);  
    Route::post('/step3', [OwnerEkycController::class, 'step3']);  
    Route::post('/step4', [OwnerEkycController::class, 'step4Submit']);

    Route::get('/getstep1', [OwnerEkycController::class, 'getStep1']);
    Route::get('/getstep2', [OwnerEkycController::class, 'getStep2']);
    Route::get('/progress', [OwnerEkycController::class, 'getProgress']);
    Route::get('/face-result',[OwnerEkycController::class, 'getFaceResult']);
    Route::get('/selfie', [OwnerEkycController::class, 'getSelfie']);
    Route::get('/review-status',[OwnerEkycController::class, 'getReviewStatus']
);
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/products')->group(function (): void {
    Route::get('/read', [OwnerProductController::class, 'visible']);
    Route::post('/addProduct', [OwnerProductController::class, 'addProduct']);
    Route::post('/updateProduct/{productId}', [OwnerProductController::class, 'updateProduct']);
    Route::delete('/deleteProduct/{productId}', [OwnerProductController::class, 'hideProduct']);
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/packages')->group(function (): void {
    Route::get('/read', [OwnerPackageController::class, 'read']);
    Route::post('/addPackage', [OwnerPackageController::class, 'addPackage']);
    Route::put('/updatePackage/{packageId}', [OwnerPackageController::class, 'updatePackage']);
    Route::put('/set-default/{productId}/{packageId}', [OwnerPackageController::class, 'setDefault']);
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/setting')->group(function (): void {
    Route::get('/getSetting', [OwnerSettingController::class, 'getSetting']);
    Route::post('/setSetting', [OwnerSettingController::class, 'setSetting']);
    Route::post('/updateSetting', [OwnerSettingController::class, 'updateSetting']);
    Route::post('/uploadLogo', [OwnerSettingController::class, 'uploadLogo']);
  
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/business-hour')->group(function (): void {
    Route::get('/getBusinessHour', [OwnerBusinessHourController::class, 'getBusinessHour']);
    Route::post('/setBusinessHour', [OwnerBusinessHourController::class, 'setBusinessHour']);
    Route::put('/updateBusinessHour/{businessHourId}', [OwnerBusinessHourController::class, 'updateBusinessHour']);
    Route::post('/bulk', [OwnerBusinessHourController::class, 'bulkUpsert']);
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/refunds')->group(function (): void {
    Route::get('/read', [RefundController::class, 'indexOwner']);
    Route::get('/{refundId}', [RefundController::class, 'showOwner']);
    Route::put('/{refundId}/review', [RefundController::class, 'reviewOwner']);
    Route::put('/{refundId}/process', [RefundController::class, 'processOwner']);
    Route::put('/{refundId}/verify', [RefundController::class, 'verifyOwner']);
    Route::put('/{refundId}/complete', [RefundController::class, 'completeOwner']);
    Route::put('/{refundId}/cancel', [RefundController::class, 'cancelOwner']);
    Route::post('/{refundId}/inspection/upload', [RefundController::class, 'uploadInspection']);
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/dashboard')->group(function (): void {
    Route::get('/read', [OwnerDashboardController::class, 'index']);
    Route::get('/revenue', [OwnerDashboardController::class, 'revenue']);
    Route::get('/pending-orders', [OwnerDashboardController::class, 'pendingOrders']);
    Route::get('/recent-orders', [OwnerDashboardController::class, 'recentOrders']);
    Route::get('/low-stock', [OwnerDashboardController::class, 'lowStock']);
    Route::get('/reviews-summary', [OwnerDashboardController::class, 'reviewsSummary']);
    Route::get('/near-expiry', [OwnerDashboardController::class, 'nearExpiry']);
    Route::get('/full', [OwnerDashboardController::class, 'fullDashboard']);
});


Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/reviews')->group(function (): void {
        Route::get('/', [OwnerReviewController::class, 'index']);
        Route::get('/{orderId}', [OwnerReviewController::class, 'show']);

    });

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner/orders')->group(function (): void {
    Route::get('/', [OwnerOrderController::class, 'index']);
    Route::get('/{orderId}', [OwnerOrderController::class, 'show']);
    Route::put('/{orderId}/confirm', [OwnerOrderController::class, 'confirm']);
    Route::put('/{orderId}/ready', [OwnerOrderController::class, 'ready']);
    Route::put('/{orderId}/complete', [OwnerOrderController::class, 'complete']);
    Route::put('/{orderId}/decline', [OwnerOrderController::class, 'decline']);
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner')->group(function () {

    Route::get('/reports/dashboard',[ReportController::class, 'dashboard']);
    Route::get('/reports/export', [ReportController::class, 'export']);
});

Route::middleware(['auth:api', 'role:OWNER'])->prefix('owner')->group(function () {
        Route::get('/notifications',[OwnerNotificationController::class, 'index']);
        Route::get('/notifications/unread-count',[OwnerNotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read',[OwnerNotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all',[OwnerNotificationController::class, 'markAllRead']);
    });



// CUSTOMER
Route::middleware(['auth:api', 'role:CUSTOMER'])->group(function () {
    Route::prefix('customer/information')->group(function (): void {
        Route::post('/addCustomerInformation', [InformationController::class, 'addCustomerInformation']);
        Route::get('/getCustomerInformation', [InformationController::class, 'getCustomerInformation']);
        Route::put('/updateCustomerInformation', [InformationController::class, 'updateCustomerInformation']);
    });


    Route::prefix('customer/delivery-address')->group(function (): void {
        Route::post('/addDeliveryAddress', [DeliveryAddressController::class, 'addDeliveryAddress']);
        Route::get('/getDeliveryAddress', [DeliveryAddressController::class, 'getDeliveryAddress']);
        Route::put('/updateDeliveryAddress/{deliveryAddressId}', [DeliveryAddressController::class, 'updateDeliveryAddress']);
        Route::delete('/deleteDeliveryAddress/{deliveryAddressId}', [DeliveryAddressController::class, 'deleteDeliveryAddress']);
        Route::put('/setDefault/{deliveryAddressId}', [DeliveryAddressController::class, 'setDefaultAddress']);
    });

    Route::prefix('customer/cart')->group(function (): void {
        Route::get('/read', [CartController::class, 'index']);
        Route::get('/count', [CartController::class, 'getCartCount']);
        Route::post('/addToCart', [CartController::class, 'addToCart']);
        Route::put('/update/{cartItemId}', [CartController::class, 'updateItem']);
        Route::delete('/remove-package/{cartItemId}', [CartController::class, 'removeItem']);
        Route::delete('/remove-product/{productId}', [CartController::class, 'removeProduct']);
    });

    Route::prefix('customer/checkout')->group(function (): void {
        // create checkout session from cart
        Route::post('/session', [CheckoutSessionController::class, 'create']);
        // get session
        Route::get('/session/{sessionId}', [CheckoutSessionController::class, 'show']);
        // update payment + fulfillment
        Route::put('/session/{sessionId}', [CheckoutSessionController::class, 'update']);
        // confirm checkout → creates orders
        Route::post('/session/{sessionId}/confirm', [CheckoutSessionController::class, 'confirm']);
    });

    Route::prefix('customer/order')->group(function (): void {
        Route::get('/read', [OrderController::class, 'index']);
        Route::get('/read/{orderId}', [OrderController::class, 'show']);
        Route::put('/cancel/{orderId}', [OrderController::class, 'cancel']);
        Route::put('/received/{orderId}', [OrderController::class, 'confirmReceived']);
        Route::post('/review/{orderId}', [OrderController::class, 'submitReview']);
    });

    Route::prefix('customer/payment')->group(function (): void {
        Route::get('/read', [PaymentController::class, 'getCustomerPayments']);
        Route::get('/read/{paymentId}', [PaymentController::class, 'getCustomerPaymentById']);
    });

    Route::prefix('refunds')->group(function (): void {
        Route::post('/create', [RefundController::class, 'create']);
        Route::get('/read/{refundId}', [RefundController::class, 'show']);
    });

    Route::prefix('customer/invoices')->group(function (): void {
        Route::post('/{orderId}/generate', [InvoiceController::class, 'generate']);
        Route::get('/read', [InvoiceController::class, 'index']);
        Route::get('/read/{invoiceId}', [InvoiceController::class, 'show']);
        Route::get('/read/{invoiceId}/print', [InvoiceController::class, 'print']);
    });

    Route::prefix('notifications')->group(function (): void {
        // unread-count and read-all must be registered before parameterised routes
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/filter', [NotificationController::class, 'index']);
        Route::get('/{notificationId}', [NotificationController::class, 'show']);
        Route::put('/{notificationId}/read', [NotificationController::class, 'markRead']);
        Route::get('/unread', [NotificationController::class, 'unread']);
    });
});



// =============== STRIPE PAYMENT ROUTES ==============
// Public webhook endpoint — called by Stripe
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Protected Stripe
Route::middleware(['auth:api', 'role:CUSTOMER'])->prefix('payment/stripe')->group(function (): void {
    Route::post('/create-intent', [StripePaymentController::class, 'createPaymentIntent']);
    Route::post('/confirm', [StripePaymentController::class, 'confirmPayment']);
    Route::post('/create-customer', [StripePaymentController::class, 'createCustomer']);
    Route::get('/status/{paymentIntentId}', [StripePaymentController::class, 'getPaymentStatus']);
    Route::post('/refund', [StripePaymentController::class, 'createRefund']);
});

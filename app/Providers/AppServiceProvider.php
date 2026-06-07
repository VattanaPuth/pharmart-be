<?php

namespace App\Providers;

use App\Services\AdminServices\CategoryServices\CategoryService;
use App\Services\AdminServices\CategoryServices\impl\CategoryServiceImpl;
use App\Services\AdminServices\DecisionServices\EkycReviewService;
use App\Services\AdminServices\DecisionServices\impl\EkycReviewServiceImpl;
use App\Services\AdminServices\SubCategoryServices\impl\SubCategoryServiceImpl;
use App\Services\AdminServices\SubCategoryServices\SubCategoryService;
use App\Services\AuthServices\adminService\AdminAuthService;
use App\Services\AuthServices\adminService\impl\AdminAuthServiceImpl;
use App\Services\AuthServices\googleService\GoogleService;
use App\Services\AuthServices\googleService\impl\GoogleServiceImpl;
use App\Services\AuthServices\loginService\impl\LoginServiceImpl;
use App\Services\AuthServices\loginService\LoginService;
use App\Services\AuthServices\otpService\impl\LogSmsSender;
use App\Services\AuthServices\otpService\impl\TwilioSmsSender;
use App\Services\AuthServices\otpService\impl\OtpServiceImpl;
use App\Services\AuthServices\otpService\OtpService;
use App\Services\AuthServices\otpService\SmsSenderInterface;
use App\Services\AuthServices\registerService\impl\RegisterServiceImpl;
use App\Services\AuthServices\registerService\RegisterService;
use App\Services\CustomerServices\impl\InformationServiceImpl;
use App\Services\CustomerServices\impl\DeliveryAddressServiceImpl;
use App\Services\CustomerServices\impl\InvoiceServiceImpl;
use App\Services\CustomerServices\impl\CartServiceImpl;
use App\Services\CustomerServices\impl\CartItemsServiceImpl;
use App\Services\CustomerServices\impl\OrderServiceImpl;
use App\Services\CustomerServices\impl\OrderItemsServiceImpl;
use App\Services\CustomerServices\impl\PaymentOrderServiceImpl;
use App\Services\CustomerServices\impl\PaymentServiceImpl;
use App\Services\CustomerServices\impl\RefundServiceImpl;
use App\Services\CustomerServices\CartService;
use App\Services\CustomerServices\CartItemsService;
use App\Services\CustomerServices\OrderService;
use App\Services\CustomerServices\OrderItemsService;
use App\Services\CustomerServices\PaymentOrderService;
use App\Services\CustomerServices\PaymentService;
use App\Services\CustomerServices\RefundService;
use App\Services\CustomerServices\DeliveryAddressService;
use App\Services\CustomerServices\InvoiceService;
use App\Services\CustomerServices\InformationService;
use App\Services\CustomerServices\CheckoutSessionServiceInterface;
use App\Services\CustomerServices\impl\CheckoutSessionServiceImpl;
use App\Services\NotificationServices\NotificationService;
use App\Services\NotificationServices\impl\NotificationServiceImpl;
use App\Services\PaymentServices\StripeWebhookService;
use App\Services\PaymentServices\impl\StripeWebhookServiceImpl;
use App\Services\EkycServices\FaceProviderService;
use App\Services\EkycServices\impl\FaceProviderServiceImpl;
use App\Services\EkycServices\impl\OwnerEkycServiceImpl;
use App\Services\EkycServices\OwnerEkycService;
use App\Services\OwnerServices\impl\OwnerBusinessHourServiceImpl;
use App\Services\OwnerServices\impl\OwnerPackageServiceImpl;
use App\Services\OwnerServices\impl\OwnerProductServiceImpl;
use App\Services\OwnerServices\impl\OwnerSettingServiceImpl;
use App\Services\OwnerServices\impl\OwnerDashboardServiceImpl;
use App\Services\OwnerServices\OwnerBusinessHourService;
use App\Services\OwnerServices\OwnerPackageService;
use App\Services\OwnerServices\OwnerProductService;
use App\Services\OwnerServices\OwnerSettingService;
use App\Services\OwnerServices\OwnerDashboardService;
use App\Services\OwnerServices\OwnerInvoiceService;
use App\Services\OwnerServices\impl\OwnerInvoiceServiceImpl;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RegisterService::class, RegisterServiceImpl::class);
        $this->app->bind(LoginService::class, LoginServiceImpl::class);
        $this->app->bind(OtpService::class, OtpServiceImpl::class);
        $this->app->bind(SmsSenderInterface::class, TwilioSmsSender::class);
        $this->app->bind(InformationService::class, InformationServiceImpl::class);
        $this->app->bind(DeliveryAddressService::class, DeliveryAddressServiceImpl::class);
        $this->app->bind(InvoiceService::class, InvoiceServiceImpl::class);
        $this->app->bind(CartService::class, CartServiceImpl::class);
        $this->app->bind(CartItemsService::class, CartItemsServiceImpl::class);
        $this->app->bind(OrderService::class, OrderServiceImpl::class);
        $this->app->bind(OrderItemsService::class, OrderItemsServiceImpl::class);
        $this->app->bind(PaymentOrderService::class, PaymentOrderServiceImpl::class);
        $this->app->bind(PaymentService::class, PaymentServiceImpl::class);
        $this->app->bind(RefundService::class, RefundServiceImpl::class);
        $this->app->bind(OwnerEkycService::class, OwnerEkycServiceImpl::class);
        $this->app->bind(FaceProviderService::class, FaceProviderServiceImpl::class);
        $this->app->bind(AdminAuthService::class, AdminAuthServiceImpl::class);
        $this->app->bind(GoogleService::class, GoogleServiceImpl::class);
        $this->app->bind(EkycReviewService::class, EkycReviewServiceImpl::class);
        $this->app->bind(CategoryService::class, CategoryServiceImpl::class);
        $this->app->bind(SubCategoryService::class, SubCategoryServiceImpl::class);
        $this->app->bind(OwnerProductService::class, OwnerProductServiceImpl::class);
        $this->app->bind(OwnerPackageService::class, OwnerPackageServiceImpl::class);
        $this->app->bind(OwnerSettingService::class, OwnerSettingServiceImpl::class);
        $this->app->bind(OwnerBusinessHourService::class, OwnerBusinessHourServiceImpl::class);
        $this->app->bind(OwnerDashboardService::class, OwnerDashboardServiceImpl::class);
        $this->app->bind(OwnerInvoiceService::class, OwnerInvoiceServiceImpl::class);
        $this->app->bind(NotificationService::class, NotificationServiceImpl::class);
        $this->app->bind(StripeWebhookService::class, StripeWebhookServiceImpl::class);
        $this->app->bind(
            CheckoutSessionServiceInterface::class,
            CheckoutSessionServiceImpl::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

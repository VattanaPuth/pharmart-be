<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * =========================
         * ADMIN
         * =========================
         */

        Schema::table('admin_notification', function (Blueprint $table) {
            $table->index('owner_id', 'admin_notification_owner_id_foreign');
            $table->index('ekyc_id', 'admin_notification_ekyc_id_foreign');
            $table->index(['admin_id', 'is_read'], 'admin_notification_admin_id_is_read_index');
            $table->index('type', 'admin_notification_type_index');
        });

        /*
         * =========================
         * CATEGORY / CUSTOMER
         * =========================
         */

        Schema::table('customer', function (Blueprint $table) {
            $table->index('register_id', 'customer_register_id_foreign');
        });

        Schema::table('customer_cart', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'customer_cart_customer_id_status_index');
        });

        Schema::table('customer_cart_items', function (Blueprint $table) {
            $table->index('product_id', 'customer_cart_items_product_id_foreign');
            $table->index('owner_id', 'customer_cart_items_owner_id_foreign');
            $table->index(
                ['cart_id', 'product_id'],
                'customer_cart_items_cart_id_product_id_index'
            );
        });

        /**
         * =========================
         * CHECKOUT
         * =========================
         */
        Schema::table('customer_checkout_sessions', function (Blueprint $table) {
            $table->index('customer_id', 'fk_customer_checkout_customer');
        });

        Schema::table('customer_delivery_address', function (Blueprint $table) {
            $table->index('customer_id', 'customer_delivery_address_customer_id_index');
        });

        Schema::table('customer_information', function (Blueprint $table) {
        });

        /**
         * =========================
         * INVOICE
         * =========================
         */
        Schema::table('customer_invoice', function (Blueprint $table) {
            $table->index(['customer_id', 'invoice_date'], 'customer_invoice_customer_id_invoice_date_index');
            $table->index(['owner_id', 'invoice_date'], 'customer_invoice_owner_id_invoice_date_index');
            $table->index('payment_id', 'customer_invoice_payment_id_index');
            $table->index('invoice_date', 'customer_invoice_invoice_date_index');
        });

        Schema::table('customer_invoice_items', function (Blueprint $table) {
            $table->index('order_item_id', 'customer_invoice_items_order_item_id_foreign');
            $table->index('product_id', 'customer_invoice_items_product_id_index');
        });

        /**
         * =========================
         * NOTIFICATIONS
         * =========================
         */
        Schema::table('customer_notifications', function (Blueprint $table) {
            $table->index('order_id', 'customer_notifications_order_id_foreign');
            $table->index('refund_id', 'customer_notifications_refund_id_foreign');
            $table->index('owner_id', 'customer_notifications_owner_id_foreign');
            $table->index('product_id', 'customer_notifications_product_id_foreign');

            $table->index(['customer_id', 'is_read'], 'customer_notifications_customer_id_is_read_index');
            $table->index(['customer_id', 'type'], 'customer_notifications_customer_id_type_index');
            $table->index(['customer_id', 'created_at'], 'customer_notifications_customer_id_created_at_index');
        });

        /**
         * =========================
         * ORDERS
         * =========================
         */
        Schema::table('customer_order', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'customer_order_customer_id_created_at_index');
            $table->index(['owner_id', 'status'], 'customer_order_owner_id_status_index');
            $table->index('payment_status', 'customer_order_payment_status_index');

            $table->index(['status', 'created_at'], 'idx_order_status_created');
            $table->index('customer_id', 'idx_customer_id');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created_at');

            $table->index('payment_id', 'fk_customer_orders_payment');
            $table->index('checkout_session_id', 'fk_checkout_session');

            $table->index('customer_id', 'idx_customer_order_customer_id');
            $table->index('status', 'idx_customer_order_status');
            $table->index('created_at', 'idx_customer_order_created_at');
            $table->index('owner_id', 'idx_customer_order_owner_id');
        });

        /**
         * =========================
         * CUSTOMER ORDER ITEMS
         * =========================
         */
        Schema::table('customer_order_items', function (Blueprint $table) {
          

            $table->index('order_id', 'customer_order_items_order_id_index');
            $table->index('owner_id', 'customer_order_items_owner_id_index');

            $table->index(['order_id', 'product_id'], 'idx_order_items_order_product');
            $table->index('order_id', 'idx_order_id');
            $table->index('product_id', 'idx_product_id');
        });

   
        /**
         * =========================
         * PAYMENT ORDERS
         * =========================
         */
        Schema::table('customer_payment_orders', function (Blueprint $table) {
           
            $table->index('order_id', 'customer_payment_orders_order_id_index');
        });

        /**
         * =========================
         * REFUNDS
         * =========================
         */
        Schema::table('customer_refunds', function (Blueprint $table) {
           

            $table->unique('refund_number', 'customer_refunds_refund_number_unique');

            $table->index(['owner_id', 'status'], 'customer_refunds_owner_id_status_index');
            $table->index(['customer_id', 'status'], 'customer_refunds_customer_id_status_index');

            $table->index('order_id', 'customer_refunds_order_id_index');
            $table->index('payment_id', 'customer_refunds_payment_id_index');
        });

        /**
         * =========================
         * REFUND ITEMS
         * =========================
         */
        Schema::table('customer_refund_items', function (Blueprint $table) {
           
            $table->index('order_item_id', 'customer_refund_items_order_item_id_foreign');
            $table->index('product_id', 'customer_refund_items_product_id_index');
        });

        /**
         * =========================
         * E-KYC FACE VERIFICATION
         * =========================
         */
        Schema::table('ekyc_face_verifications', function (Blueprint $table) {
         

            $table->index('owner_id');
            $table->index('ekyc_id');
        });

        /**
         * =========================
         * OWNER
         * =========================
         */
        Schema::table('owner', function (Blueprint $table) {
           

            $table->unique('phone', 'owner_phone_unique');
            $table->index('register_id', 'owner_register_id_foreign');
        });

        /**
         * =========================
         * OWNER BUSINESS HOURS
         * =========================
         */
        Schema::table('owner_business_hour', function (Blueprint $table) {
         
        });

        /**
         * =========================
         * OWNER EKYC
         * =========================
         */
        Schema::table('owner_ekyc', function (Blueprint $table) {
           

            $table->unique('owner_id', 'owner_ekyc_owner_id_unique');
            $table->index('owner_id', 'idx_owner_id');
        });

        /**
         * =========================
         * OWNER NOTIFICATIONS
         * =========================
         */
        Schema::table('owner_notifications', function (Blueprint $table) {
          
            $table->index('owner_id', 'idx_owner_id');
            $table->index('is_read', 'idx_is_read');
            $table->index('order_id', 'idx_order_id');
            $table->index('refund_id', 'idx_refund_id');

            $table->index('product_id', 'fk_owner_notifications_product');
            $table->index('customer_id', 'owner_notifications_customer_id_foreign');
        });

        /**
         * =========================
         * OWNER PACKAGE
         * =========================
         */
        Schema::table('owner_package', function (Blueprint $table) {
         

            $table->index('owner_product_id', 'owner_package_owner_product_id_foreign');
        });

        /**
         * =========================
         * OWNER PRODUCT
         * =========================
         */
        Schema::table('owner_product', function (Blueprint $table) {
            

            $table->index('owner_id', 'owner_product_owner_id_foreign');
            $table->index('category_id', 'owner_product_category_id_foreign');
            $table->index('subcategory_id', 'owner_product_subcategory_id_foreign');
        });

        /**
         * =========================
         * OWNER SETTINGS
         * =========================
         */
        Schema::table('owner_setting', function (Blueprint $table) {
          
            $table->index('owner_id', 'idx_owner_id');
        });


         /**
         * =========================
         * PAYMENTS
         * =========================
         */
        Schema::table('payments', function (Blueprint $table) {
         
            $table->index(
                ['customer_id', 'created_at'],
                'payments_customer_id_created_at_index'
            );

            $table->index('status', 'payments_status_index');
            $table->index('checkout_session_id', 'payments_checkout_session_id_index');
        });

        /**
         * =========================
         * PHARMACY DOCUMENTS
         * =========================
         */
        Schema::table('pharmacy_documents', function (Blueprint $table) {
        

            $table->index('ekyc_id', 'fk_documents_ekyc');
            $table->index('owner_id', 'idx_owner_id');
        });

        /**
         * =========================
         * PRODUCT REVIEWS
         * =========================
         */
        Schema::table('product_reviews', function (Blueprint $table) {
          
            $table->index('product_id', 'idx_product');
            $table->index('customer_id', 'idx_customer');
            $table->index('order_id', 'idx_order');
        });

        /**
         * =========================
         * REFUND IMAGES
         * =========================
         */
        Schema::table('refund_images', function (Blueprint $table) {
           

            $table->index('refund_id', 'refund_images_refund_id_foreign');
        });

        /**
         * =========================
         * REGISTERS
         * =========================
         */
        Schema::table('registers', function (Blueprint $table) {
          
        });


        /**
         * =========================
         * SESSIONS
         * =========================
         */
        Schema::table('sessions', function (Blueprint $table) {
        });

        /**
         * =========================
         * SUBCATEGORY
         * =========================
         */
        Schema::table('subcategory', function (Blueprint $table) {
      

            $table->index(
                ['category_id', 'active'],
                'subcategory_category_id_active_index'
            );
        });


    }

    public function down(): void
    {
        // Optional rollback (usually skipped for index-heavy systems)
    }
};

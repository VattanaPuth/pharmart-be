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
         * ADMIN NOTIFICATIONS
         * =========================
         */
        Schema::table('admin_notification', function (Blueprint $table) {
            $table->foreign('admin_id')
                ->references('id')
                ->on('admin')
                ->cascadeOnDelete();

            $table->foreign('ekyc_id')
                ->references('id')
                ->on('owner_ekyc')
                ->nullOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->nullOnDelete();
        });

        /**
         * =========================
         * CUSTOMER → REGISTER
         * =========================
         */
        Schema::table('customer', function (Blueprint $table) {
            $table->foreign('register_id')
                ->references('id')
                ->on('registers')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * CUSTOMER CART
         * =========================
         */
        Schema::table('customer_cart', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * CART ITEMS
         * =========================
         */
        Schema::table('customer_cart_items', function (Blueprint $table) {
            $table->foreign('cart_id')
                ->references('id')
                ->on('customer_cart')
                ->cascadeOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('owner_product')
                ->cascadeOnDelete();
        });

          /**
         * =========================
         * CHECKOUT SESSIONS
         * =========================
         */
        Schema::table('customer_checkout_sessions', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * DELIVERY ADDRESS
         * =========================
         */
        Schema::table('customer_delivery_address', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * CUSTOMER INFORMATION
         * =========================
         */
        Schema::table('customer_information', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * CUSTOMER INVOICE
         * =========================
         */
        Schema::table('customer_invoice', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();

            $table->foreign('order_id')
                ->references('id')
                ->on('customer_order')
                ->cascadeOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * INVOICE ITEMS
         * =========================
         */
        Schema::table('customer_invoice_items', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('customer_invoice')
                ->cascadeOnDelete();

            $table->foreign('order_item_id')
                ->references('id')
                ->on('customer_order_items')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('owner_product')
                ->cascadeOnDelete();
        });


         /**
         * =========================
         * CUSTOMER REFUNDS
         * =========================
         */
        Schema::table('customer_refunds', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();

            $table->foreign('order_id')
                ->references('id')
                ->on('customer_order')
                ->cascadeOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->nullOnDelete();
        });

        /**
         * =========================
         * REFUND ITEMS
         * =========================
         */
        Schema::table('customer_refund_items', function (Blueprint $table) {
            $table->foreign('order_item_id')
                ->references('id')
                ->on('customer_order_items')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('owner_product')
                ->cascadeOnDelete();

            $table->foreign('refund_id')
                ->references('id')
                ->on('customer_refunds')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * eKYC FACE VERIFICATIONS
         * =========================
         */
        Schema::table('ekyc_face_verifications', function (Blueprint $table) {
            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('ekyc_id')
                ->references('id')
                ->on('owner_ekyc')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * OWNER → REGISTER
         * =========================
         */
        Schema::table('owner', function (Blueprint $table) {
            $table->foreign('register_id')
                ->references('id')
                ->on('registers')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * OWNER BUSINESS HOURS
         * =========================
         */
        Schema::table('owner_business_hour', function (Blueprint $table) {
            $table->foreign('owner_setting_id')
                ->references('id')
                ->on('owner_setting')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * OWNER EKYC
         * =========================
         */
        Schema::table('owner_ekyc', function (Blueprint $table) {
            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * OWNER NOTIFICATIONS
         * =========================
         */
        Schema::table('owner_notifications', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')
                ->on('customer_order')
                ->cascadeOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('owner_product')
                ->cascadeOnDelete();

            $table->foreign('refund_id')
                ->references('id')
                ->on('customer_refunds')
                ->cascadeOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->nullOnDelete();
        });

        /**
         * =========================
         * OWNER PACKAGE
         * =========================
         */
        Schema::table('owner_package', function (Blueprint $table) {
            $table->foreign('owner_product_id')
                ->references('id')
                ->on('owner_product')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * OWNER PRODUCT
         * =========================
         */
        Schema::table('owner_product', function (Blueprint $table) {
            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('category_id')
                ->references('id')
                ->on('category');

            $table->foreign('subcategory_id')
                ->references('id')
                ->on('subcategory')
                ->nullOnDelete();
        });

         /**
         * =========================
         * OWNER SETTINGS
         * =========================
         */
        Schema::table('owner_setting', function (Blueprint $table) {
            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * PAYMENTS
         * =========================
         */
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * PHARMACY DOCUMENTS
         * =========================
         */
        Schema::table('pharmacy_documents', function (Blueprint $table) {
            $table->foreign('ekyc_id')
                ->references('id')
                ->on('owner_ekyc')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * PRODUCT REVIEWS
         * =========================
         */
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();

            $table->foreign('order_id')
                ->references('id')
                ->on('customer_order')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('owner_product')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * REFUND IMAGES
         * =========================
         */
        Schema::table('refund_images', function (Blueprint $table) {
            $table->foreign('refund_id')
                ->references('id')
                ->on('customer_refunds')
                ->cascadeOnDelete();
        });

        /**
         * =========================
         * SUBCATEGORY
         * =========================
         */
        Schema::table('subcategory', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('category')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_cart_items', function (Blueprint $table) {
            $table->dropForeign(['cart_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('customer_cart', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('customer', function (Blueprint $table) {
            $table->dropForeign(['register_id']);
        });

        Schema::table('admin_notification', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropForeign(['ekyc_id']);
            $table->dropForeign(['owner_id']);
        });

                Schema::table('customer_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['order_item_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('customer_invoice', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['payment_id']);
        });

        Schema::table('customer_information', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('customer_delivery_address', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('customer_checkout_sessions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

         /**
         * =========================
         * CUSTOMER NOTIFICATIONS
         * =========================
         */
        Schema::table('customer_notifications', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();

            $table->foreign('order_id')
                ->references('id')
                ->on('customer_order')
                ->nullOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->nullOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('owner_product')
                ->nullOnDelete();

            $table->foreign('refund_id')
                ->references('id')
                ->on('customer_refunds')
                ->nullOnDelete();
        });

        /**
         * =========================
         * CUSTOMER ORDER
         * =========================
         */
        Schema::table('customer_order', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer')
                ->cascadeOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('checkout_session_id')
                ->references('id')
                ->on('customer_checkout_sessions')
                ->nullOnDelete();

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->nullOnDelete();
        });

        /**
         * =========================
         * ORDER ITEMS
         * =========================
         */
        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')
                ->on('customer_order')
                ->cascadeOnDelete();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owner')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('owner_product');
        });

        /**
         * =========================
         * PAYMENT ORDERS (pivot)
         * =========================
         */
        Schema::table('customer_payment_orders', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')
                ->on('customer_order')
                ->cascadeOnDelete();

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->cascadeOnDelete();
        });

                Schema::table('customer_payment_orders', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['payment_id']);
        });

        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('customer_order', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['checkout_session_id']);
            $table->dropForeign(['payment_id']);
        });

        Schema::table('customer_notifications', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['refund_id']);
        });

          Schema::table('ekyc_face_verifications', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['ekyc_id']);
        });

        Schema::table('customer_refund_items', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['refund_id']);
        });

        Schema::table('customer_refunds', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['payment_id']);
        });

        Schema::table('customer_payment_orders', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['payment_id']);
        });

         Schema::table('owner_product', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['subcategory_id']);
        });

        Schema::table('owner_package', function (Blueprint $table) {
            $table->dropForeign(['owner_product_id']);
        });

        Schema::table('owner_notifications', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['refund_id']);
            $table->dropForeign(['customer_id']);
        });

        Schema::table('owner_ekyc', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('owner_business_hour', function (Blueprint $table) {
            $table->dropForeign(['owner_setting_id']);
        });

        Schema::table('owner', function (Blueprint $table) {
            $table->dropForeign(['register_id']);
        });

          Schema::table('subcategory', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('refund_images', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('pharmacy_documents', function (Blueprint $table) {
            $table->dropForeign(['ekyc_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('owner_setting', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });
    }
};
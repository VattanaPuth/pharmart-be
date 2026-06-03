<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order', function (Blueprint $table) {
            $table->id();

            $table->string('order_number', 50)->nullable();

            $table->foreignId('customer_id');
            $table->foreignId('owner_id');

            $table->enum('status', [
                'pending',
                'confirmed',
                'ready',
                'delivering',
                'completed',
                'cancelled',
                'refunded',
                'declined'
            ])->default('pending');

            $table->json('status_history')->nullable();

            $table->enum('fulfillment_method', ['pickup', 'delivery']);
            $table->enum('payment_method', ['online', 'pay_at_shop']);
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->datetime('confirmed_at')->nullable();
            $table->datetime('ready_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('cancelled_at')->nullable();

            $table->text('delivery_address')->nullable();

            $table->timestamp('customer_completed_at')->nullable();
            $table->timestamp('pharmacy_completed_at')->nullable();

            $table->foreignId('payment_id')->nullable();
            $table->foreignId('checkout_session_id')->nullable();

            $table->text('decline_reason')->nullable();

            $table->timestamps();

   
            // Optional FK
            // $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order');
    }
};
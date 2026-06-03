<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_checkout_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id');

            $table->json('items');

            $table->decimal('subtotal', 10, 2)->default(0);

            $table->enum('fulfillment_method', [
                'pickup',
                'delivery'
            ])->nullable();

            $table->enum('payment_method', [
                'online',
                'pay_at_shop'
            ])->nullable();

            $table->string('delivery_address')->nullable();

            $table->enum('status', [
                'active',
                'completed',
                'expired'
            ])->default('active');

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
       

            // Optional foreign key
            // $table->foreign('customer_id')
            //       ->references('id')
            //       ->on('customer')
            //       ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_checkout_sessions');
    }
};
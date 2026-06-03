<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id');
            $table->foreignId('order_id');

            $table->decimal('amount', 12, 2);

            $table->timestamp('created_at')->useCurrent();

             // UNIQUE
    $table->unique(
        ['payment_id', 'order_id'],
        'customer_payment_orders_payment_id_order_id_unique'
    );

    // INDEX

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_orders');
    }
};
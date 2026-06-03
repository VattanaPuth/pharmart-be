<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_refund_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('refund_id');
            $table->foreignId('order_item_id');
            $table->foreignId('product_id');

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_refund_amount', 12, 2);

            $table->timestamp('created_at')->useCurrent();
             // UNIQUE
    $table->unique(
        ['refund_id', 'order_item_id'],
        'customer_refund_items_refund_id_order_item_id_unique'
    );

    // INDEXES
  
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_refund_items');
    }
};
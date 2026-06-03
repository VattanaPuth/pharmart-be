<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id');
            $table->foreignId('order_item_id');
            $table->foreignId('product_id');

            $table->string('item_name');
            $table->text('item_description')->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);

            $table->timestamp('created_at')->useCurrent();
            // UNIQUE
    $table->unique(
        ['invoice_id', 'order_item_id'],
        'customer_invoice_items_invoice_id_order_item_id_unique'
    );

 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_items');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id');
            $table->foreignId('product_id');
            $table->string('product_sku', 100)->nullable();

            $table->foreignId('owner_id');

            $table->string('product_name');
            $table->string('product_image')->nullable();

            $table->json('product_snapshot')->nullable();

            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);

            $table->timestamp('created_at')->useCurrent();

            $table->bigInteger('package_id')->nullable();
            $table->string('package_name')->nullable();


            // Optional FK
            // $table->foreign('order_id')->references('id')->on('customer_order')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_items');
    }
};
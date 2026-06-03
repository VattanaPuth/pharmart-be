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
        Schema::create('customer_cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id');
            $table->foreignId('product_id');
            $table->foreignId('owner_id');

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);

            $table->bigInteger('package_id')->nullable();

            $table->timestamps();

            // UNIQUE KEY
    $table->unique(['cart_id', 'product_id', 'package_id'], 'cart_product_package_unique');


            // Optional foreign keys
            // $table->foreign('cart_id')
            //       ->references('id')
            //       ->on('customer_cart')
            //       ->onDelete('cascade');

            // $table->foreign('product_id')
            //       ->references('id')
            //       ->on('product')
            //       ->onDelete('cascade');

            // $table->foreign('owner_id')
            //       ->references('id')
            //       ->on('owner')
            //       ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_cart_items');
    }
};
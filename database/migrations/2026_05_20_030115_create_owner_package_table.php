<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_package', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_product_id');

            $table->string('package_name', 50);

            $table->string('contains')->nullable();

            $table->decimal('price', 8, 2)->default(0);

            $table->bigInteger('stock_quantity')->default(0);

            $table->boolean('is_default')->default(false);

            $table->integer('low_stock_threshold')->default(10);

            $table->timestamps();

            // optional FK
            // $table->foreign('owner_product_id')
            //       ->references('id')
            //       ->on('owner_product')
            //       ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_package');
    }
};
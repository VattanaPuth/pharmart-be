<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id');

            $table->string('product_name');
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable();
            $table->string('form')->nullable();

            $table->date('expiry_date')->nullable();

            $table->foreignId('category_id');
            $table->foreignId('subcategory_id')->nullable();

            $table->string('main_image')->nullable();
            $table->text('description')->nullable();

            $table->boolean('status')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->integer('featured_rank')->nullable();
            $table->dateTime('featured_from')->nullable();
            $table->dateTime('featured_till')->nullable();

            $table->timestamps();

            // INDEXES
 

            // optional FK
            // $table->foreign('owner_id')->references('id')->on('owner')->cascadeOnDelete();
            // $table->foreign('category_id')->references('id')->on('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_product');
    }
};
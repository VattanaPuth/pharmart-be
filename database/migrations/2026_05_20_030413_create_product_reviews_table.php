<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id');
            $table->foreignId('order_id');
            $table->foreignId('product_id');

            $table->unsignedTinyInteger('rating');

            $table->text('review')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // UNIQUE
    $table->unique(
        ['order_id', 'product_id'],
        'unique_order_product_review'
    );



            // Optional validation (MySQL 8+)
            // DB::statement("ALTER TABLE product_reviews ADD CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
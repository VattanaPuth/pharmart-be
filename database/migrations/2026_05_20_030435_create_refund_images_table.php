<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('refund_id');

            $table->string('image_path');

            $table->foreignId('uploaded_by_id')->nullable();

            $table->enum('uploaded_by_type', ['customer', 'pharmacy']);

            $table->timestamps();


            // optional FK
            // $table->foreign('refund_id')->references('id')->on('customer_refunds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_images');
    }
};
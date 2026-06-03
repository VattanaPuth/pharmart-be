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
        Schema::create('customer_cart', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id');

            $table->enum('status', [
                'active',
                'checked_out',
                'paid',
                'abandoned'
            ])->default('active');

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
        Schema::dropIfExists('customer_cart');
    }
};
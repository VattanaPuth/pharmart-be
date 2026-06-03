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
        Schema::create('customer_information', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id');

            $table->string('customer_name');

            $table->string('phone_number', 50);

            $table->string('email')->nullable();

            $table->timestamps();
             $table->unique('customer_id', 'customer_information_customer_id_unique');

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
        Schema::dropIfExists('customer_information');
    }
};
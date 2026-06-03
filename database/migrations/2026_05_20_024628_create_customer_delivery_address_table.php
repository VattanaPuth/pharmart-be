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
        Schema::create('customer_delivery_address', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id');

            $table->string('label', 100);

            $table->string('recipient_name');

            $table->string('phone_number', 50);

            $table->text('full_address');

            $table->string('city');

            $table->string('google_map_link')->nullable();

            $table->boolean('is_default')->default(false);

            $table->decimal('latitude', 10, 8)->nullable();

            $table->decimal('longitude', 11, 8)->nullable();

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
        Schema::dropIfExists('customer_delivery_address');
    }
};
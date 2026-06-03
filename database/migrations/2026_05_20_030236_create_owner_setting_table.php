<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_setting', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id');

            $table->string('pharmacy_name')->nullable();
            $table->string('owner_name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            $table->string('gps_location')->nullable();

            $table->string('phone_number')->nullable();
            $table->string('displayable_email')->nullable();

            $table->string('logo')->nullable();

            $table->boolean('notification_enabled')->default(true);

            $table->unsignedInteger('low_stock_alert')->nullable();

            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'suspended'
            ])->default('pending');

            $table->timestamps();
            $table->unique('owner_id', 'owner_setting_owner_id_unique');

            // optional FK
            // $table->foreign('owner_id')->references('id')->on('owner')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_setting');
    }
};
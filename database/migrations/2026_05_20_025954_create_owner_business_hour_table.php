<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_business_hour', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_setting_id');

            $table->enum('day_of_week', [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday'
            ]);

            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();

            $table->boolean('is_open')->default(false);

            $table->timestamps();
            $table->unique(
        ['owner_setting_id', 'day_of_week'],
        'owner_business_hour_owner_setting_id_day_of_week_unique'
    );

            // optional FK
            // $table->foreign('owner_setting_id')
            //       ->references('id')
            //       ->on('owner_setting')
            //       ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_business_hour');
    }
};
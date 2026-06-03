<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner', function (Blueprint $table) {
            $table->id();

            $table->foreignId('register_id')->nullable();
            $table->string('phone')->nullable();

            $table->timestamps();
          
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner');
    }
};
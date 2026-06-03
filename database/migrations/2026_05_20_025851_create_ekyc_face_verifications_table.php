<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekyc_face_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id');
            $table->foreignId('ekyc_id');

            $table->decimal('score', 5, 2);
            $table->decimal('threshold', 5, 2);

            $table->boolean('passed')->default(false);

            $table->timestamps();

 
            // optional FK
            // $table->foreign('owner_id')->references('id')->on('owner')->cascadeOnDelete();
            // $table->foreign('ekyc_id')->references('id')->on('owner_ekyc')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekyc_face_verifications');
    }
};
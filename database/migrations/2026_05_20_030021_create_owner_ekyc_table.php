<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_ekyc', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id');

            $table->string('pharmacy_name')->nullable();
            $table->text('full_address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'pending_review',
                'approved',
                'rejected',
                'suspended'
            ])->default('draft');

            $table->text('review_message')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->enum('id_type', [
                'national_id',
                'passport',
                'driver_license'
            ])->nullable();

            $table->string('id_number', 100)->nullable();
            $table->date('date_of_birth')->nullable();

            $table->string('owner_name');

            $table->string('selfie_url')->nullable();

            $table->timestamps();

        
            // optional FK
            // $table->foreign('owner_id')->references('id')->on('owner')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_ekyc');
    }
};
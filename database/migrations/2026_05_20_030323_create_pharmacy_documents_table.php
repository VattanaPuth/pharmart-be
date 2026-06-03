<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id');
            $table->foreignId('ekyc_id')->nullable();

            $table->string('document_type');

            $table->text('file_url');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->text('review_message')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        

            // optional FK
            // $table->foreign('owner_id')->references('id')->on('owner')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_documents');
    }
};
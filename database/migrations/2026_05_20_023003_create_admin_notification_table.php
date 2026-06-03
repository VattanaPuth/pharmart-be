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
        Schema::create('admin_notification', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_id');
            $table->foreignId('owner_id')->nullable();
            $table->foreignId('ekyc_id')->nullable();

            $table->string('type', 100);
            $table->string('title');
            $table->text('message');

            $table->json('data')->nullable();

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

   

            // Optional foreign keys
            // $table->foreign('admin_id')->references('id')->on('admin')->onDelete('cascade');
            // $table->foreign('owner_id')->references('id')->on('owner')->onDelete('set null');
            // $table->foreign('ekyc_id')->references('id')->on('ekyc')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notification');
    }
};
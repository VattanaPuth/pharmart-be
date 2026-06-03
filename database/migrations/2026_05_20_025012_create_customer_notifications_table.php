<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id');
            $table->foreignId('order_id')->nullable();
            $table->foreignId('refund_id')->nullable();
            $table->foreignId('owner_id')->nullable();
            $table->foreignId('product_id')->nullable();

            $table->string('type', 100);
            $table->string('title');
            $table->text('message');

            $table->string('target_role', 50)->default('customer');

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

 

            // optional FK constraints
            // $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notifications');
    }
};
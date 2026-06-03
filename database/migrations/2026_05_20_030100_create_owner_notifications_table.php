<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id');
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('refund_id')->nullable();
            $table->foreignId('product_id')->nullable();

            $table->string('type', 100);
            $table->string('title');
            $table->text('message');

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->json('data')->nullable();

            $table->json('channels')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


            // optional FK
            // $table->foreign('owner_id')->references('id')->on('owner')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_notifications');
    }
};
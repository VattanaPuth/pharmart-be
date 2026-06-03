<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id');
            $table->foreignId('payment_id')->nullable();
            $table->foreignId('customer_id');
            $table->foreignId('owner_id');

            $table->string('refund_number');

            $table->string('reason');
            $table->text('note')->nullable();

            $table->enum('status', [
                'requested',
                'approved',
                'returning',
                'verified',
                'refunded',
                'canceled'
            ])->default('requested');

            $table->enum('refund_type', ['full', 'partial'])->default('partial');

            $table->decimal('refund_amount', 12, 2);

            $table->foreignId('requested_by');
            $table->foreignId('reviewed_by')->nullable();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->text('inspection_note')->nullable();

            $table->string('stripe_refund_id')->nullable();

            $table->timestamps();

            // Optional FKs
            // $table->foreign('order_id')->references('id')->on('customer_order')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_refunds');
    }
};
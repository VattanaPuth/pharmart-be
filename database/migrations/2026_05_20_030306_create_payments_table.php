<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id');

            $table->string('payment_provider');
            $table->string('transaction_id')->nullable();
            $table->string('stripe_charge_id')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('usd');

            $table->enum('status', [
                'pending',
                'success',
                'failed'
            ])->default('pending');

            $table->timestamp('paid_at')->nullable();

            $table->foreignId('checkout_session_id')->nullable();

            $table->timestamps();

             $table->unique(
        ['payment_provider', 'transaction_id'],
        'payments_payment_provider_transaction_id_unique'
    );

 

            // optional FK
            // $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
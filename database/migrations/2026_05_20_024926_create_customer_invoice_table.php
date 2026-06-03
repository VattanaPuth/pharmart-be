<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoice', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number', 50);

            $table->foreignId('order_id');
            $table->foreignId('payment_id');
            $table->foreignId('customer_id');
            $table->foreignId('owner_id');

            $table->string('order_number', 50);
            $table->string('payment_ref', 100)->nullable();

            $table->string('bill_to_name');
            $table->string('bill_to_email')->nullable();
            $table->text('bill_to_address')->nullable();

            $table->string('from_name');
            $table->string('from_tax_id', 100)->nullable();
            $table->text('from_address')->nullable();

            $table->enum('delivered_method', ['pickup', 'delivery']);

            $table->date('invoice_date');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->string('currency', 10)->default('usd');

            $table->text('notes')->nullable();

            $table->timestamp('issued_at')->useCurrent();

            $table->timestamps();

             // UNIQUE
    $table->unique('order_id', 'customer_invoice_order_id_unique');
    $table->unique('invoice_number', 'customer_invoice_invoice_number_unique');


            // optional FK examples
            // $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoice');
    }
};
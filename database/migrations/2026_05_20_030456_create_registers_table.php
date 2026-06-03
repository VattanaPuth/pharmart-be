<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->nullable();

            $table->timestamp('phone_verified_at')->nullable();

            $table->string('oauth_provider')->nullable();
            $table->string('oauth_provider_id')->nullable();

            $table->string('status')->default('ACTIVE');

            $table->boolean('onboarding_completed')->default(false);

            $table->timestamps();

            // UNIQUE CONSTRAINTS
    $table->unique(
        ['oauth_provider', 'oauth_provider_id'],
        'registers_oauth_provider_oauth_provider_id_unique'
    );

    $table->unique('phone', 'registers_phone_unique');
    $table->unique('email', 'registers_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
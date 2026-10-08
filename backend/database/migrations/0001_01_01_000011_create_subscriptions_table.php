<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 50);              // starter | pro | enterprise
            $table->string('status', 20);            // active | expired | suspended
            $table->timestamp('expires_at');
            $table->json('limits')->nullable();      // { max_users: 5, max_warehouses: 2 }
            $table->timestamps();

            $table->index('organisation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

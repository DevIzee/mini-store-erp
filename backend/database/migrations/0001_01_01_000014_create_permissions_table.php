<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permissions system-wide — seedées, jamais modifiées par un tenant.
        // Pas de organisation_id, pas de timestamps, pas de BelongsToTenant.
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();   // 'sales.create', 'stock.read'…
            $table->string('display_name', 200)->nullable();
            $table->string('permission_group', 50)->nullable(); // 'sales' | 'stock' | 'catalogue'… pour UI

            $table->index('permission_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);             // "Exercice 2025"
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status', 10)->default('open'); // open | closed
            $table->timestamps();

            $table->unique(['organisation_id', 'name']);
            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};

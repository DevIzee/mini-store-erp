<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accès exclusif via DocumentNumberingService::next() avec lockForUpdate().
        // Jamais lu/écrit directement depuis un controller ou un model Eloquent.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50); // 'sale' | 'proforma' | 'delivery_note' | 'purchase_order'
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_value')->default(0);

            $table->primary(['organisation_id', 'document_type', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_status', 50)->nullable();   // SARL, SUARL, SA…
            $table->string('ifu', 50)->nullable();            // Identifiant Fiscal Unique
            $table->string('rccm', 100)->nullable();          // Registre du Commerce
            $table->text('address')->nullable();
            $table->string('po_box', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('logo', 500)->nullable();          // chemin storage

            // Localisation
            $table->string('currency_code', 3)->default('XOF');
            $table->string('timezone', 100)->default('Africa/Abidjan');
            $table->string('language', 10)->default('fr');

            // Exercice fiscal
            $table->date('fiscal_year_start')->default('2025-01-01');
            $table->string('fiscal_year_mode', 10)->default('simple'); // simple | managed

            // Numérotation des documents
            $table->string('numbering_strategy', 20)->default('yearly_reset'); // yearly_reset | continuous
            $table->string('doc_prefix_sale', 10)->default('FAC');
            $table->string('doc_prefix_proforma', 10)->default('PRO');
            $table->string('doc_prefix_delivery', 10)->default('BL');
            $table->string('doc_prefix_purchase', 10)->default('BC');
            $table->unsignedTinyInteger('sequence_padding')->default(6);

            // Taxes & stock
            $table->decimal('default_tax_rate', 5, 2)->nullable();
            $table->string('low_stock_threshold_mode', 10)->default('global'); // global | per_item
            $table->decimal('low_stock_threshold', 15, 4)->nullable();

            // Fonctionnalités
            $table->boolean('barcode_scanner_enabled')->default(true);
            $table->boolean('thermal_print_logo')->default(true);
            $table->boolean('import_export_enabled')->default(true);

            // Impression
            $table->json('document_header')->nullable(); // logo, adresse, identité
            $table->json('document_footer')->nullable(); // mentions légales, RIB, slogan
            $table->string('template_name', 50)->default('default');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};

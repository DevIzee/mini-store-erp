<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);             // 'Operator', 'Lead_Operator', 'Manager', 'Admin'
            $table->boolean('is_system')->default(false); // TRUE = rôle par défaut seedé, non supprimable
            $table->unsignedTinyInteger('hierarchy_level'); // 1=Operator 2=Lead_Operator 3=Manager 4=Admin
            $table->timestamps();

            $table->unique(['organisation_id', 'name']);
            $table->index(['organisation_id', 'hierarchy_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

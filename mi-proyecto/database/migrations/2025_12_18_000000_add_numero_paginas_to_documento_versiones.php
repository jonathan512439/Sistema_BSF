<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documento_versiones', function (Blueprint $table) {
            // Agregar campo dedicado para número de páginas
            // Esto evita tener que parsear el campo version_motivo con regex
            $table->integer('numero_paginas')->nullable()->after('archivo_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_versiones', function (Blueprint $table) {
            $table->dropColumn('numero_paginas');
        });
    }
};

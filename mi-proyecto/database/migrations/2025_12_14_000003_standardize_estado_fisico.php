<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Paso 1: Normalizar valores existentes a minúsculas
        DB::table('documento_ubicacion')
            ->whereIn('estado_fisico', ['Bueno', 'BUENO'])
            ->update(['estado_fisico' => 'bueno']);

        DB::table('documento_ubicacion')
            ->where('estado_fisico', 'buen estado')
            ->update(['estado_fisico' => 'bueno']);

        DB::table('documento_ubicacion')
            ->whereIn('estado_fisico', ['Regular', 'REGULAR'])
            ->update(['estado_fisico' => 'regular']);

        DB::table('documento_ubicacion')
            ->whereIn('estado_fisico', ['Critico', 'CRITICO', 'crítico'])
            ->update(['estado_fisico' => 'critico']);

        DB::table('documento_ubicacion')
            ->where('estado_fisico', 'en_archivo')
            ->update(['estado_fisico' => 'bueno']);

        // Paso 2: Cambiar columna a ENUM
        DB::statement("
            ALTER TABLE documento_ubicacion 
            MODIFY COLUMN estado_fisico 
            ENUM('excelente', 'bueno', 'regular', 'malo', 'critico') 
            NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver a VARCHAR
        DB::statement("
            ALTER TABLE documento_ubicacion 
            MODIFY COLUMN estado_fisico 
            VARCHAR(30) NOT NULL
        ");
    }
};

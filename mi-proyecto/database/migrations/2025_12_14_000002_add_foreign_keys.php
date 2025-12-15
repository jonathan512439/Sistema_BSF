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
        // 1. legal_holds -> documentos
        Schema::table('legal_holds', function (Blueprint $table) {
            $table->foreign('documento_id', 'fk_legal_holds_documento')
                ->references('id')
                ->on('documentos')
                ->onDelete('restrict');
        });

        // 2. legal_holds -> users
        Schema::table('legal_holds', function (Blueprint $table) {
            $table->foreign('activado_por', 'fk_legal_holds_user')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        // 3. documento_ubicacion -> documentos
        Schema::table('documento_ubicacion', function (Blueprint $table) {
            $table->foreign('documento_id', 'fk_doc_ubicacion_documento')
                ->references('id')
                ->on('documentos')
                ->onDelete('restrict');
        });

        // 4. documento_ubicacion -> ubicaciones_fisicas
        Schema::table('documento_ubicacion', function (Blueprint $table) {
            $table->foreign('ubicacion_fisica_id', 'fk_doc_ubicacion_ubicacion')
                ->references('id')
                ->on('ubicaciones_fisicas')
                ->onDelete('restrict');
        });

        // 5. documento_ubicacion -> users (responsable)
        Schema::table('documento_ubicacion', function (Blueprint $table) {
            $table->foreign('responsable_id', 'fk_doc_ubicacion_responsable')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        // 6. impresiones_log -> documentos
        Schema::table('impresiones_log', function (Blueprint $table) {
            $table->foreign('documento_id', 'fk_impresiones_documento')
                ->references('id')
                ->on('documentos')
                ->onDelete('restrict');
        });

        // 7. impresiones_log -> users
        Schema::table('impresiones_log', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_impresiones_user')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_holds', function (Blueprint $table) {
            $table->dropForeign('fk_legal_holds_documento');
            $table->dropForeign('fk_legal_holds_user');
        });

        Schema::table('documento_ubicacion', function (Blueprint $table) {
            $table->dropForeign('fk_doc_ubicacion_documento');
            $table->dropForeign('fk_doc_ubicacion_ubicacion');
            $table->dropForeign('fk_doc_ubicacion_responsable');
        });

        Schema::table('impresiones_log', function (Blueprint $table) {
            $table->dropForeign('fk_impresiones_documento');
            $table->dropForeign('fk_impresiones_user');
        });
    }
};

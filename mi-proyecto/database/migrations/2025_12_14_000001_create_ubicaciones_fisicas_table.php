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
        Schema::create('ubicaciones_fisicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->enum('tipo', ['archivo', 'deposito', 'caja', 'estante', 'otro']);
            $table->string('codigo', 50)->unique()->nullable();
            $table->unsignedBigInteger('ubicacion_padre_id')->nullable();
            $table->text('descripcion')->nullable();
            $table->integer('capacidad_max')->nullable();
            $table->boolean('activo')->default(true);

            // Soft delete fields
            $table->dateTime('deleted_at', 3)->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();

            $table->dateTime('created_at', 3)->useCurrent();
            $table->dateTime('updated_at', 3)->useCurrent()->useCurrentOnUpdate();

            // Foreign key para jerarquía
            $table->foreign('ubicacion_padre_id')
                ->references('id')
                ->on('ubicaciones_fisicas')
                ->onDelete('restrict');

            // Indexes
            $table->index('tipo');
            $table->index('activo');
            $table->index(['ubicacion_padre_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubicaciones_fisicas');
    }
};

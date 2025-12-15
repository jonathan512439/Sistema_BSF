<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índice 1: legal_holds - búsqueda por documento y si está activo
        DB::statement('
            CREATE INDEX idx_legal_holds_documento 
            ON legal_holds(documento_id, levantado_en)
        ');

        // Índice 2: documento_ubicacion - búsqueda por ubicación física
        DB::statement('
            CREATE INDEX idx_doc_ubicacion_fisica 
            ON documento_ubicacion(ubicacion_fisica_id, fecha_retiro)
        ');

        // Índice 3: impresiones_log - búsqueda por usuario
        DB::statement('
            CREATE INDEX idx_impresiones_user 
            ON impresiones_log(user_id, created_at)
        ');

        // Índice 4: accesos_documento - búsqueda por tipo de acción
        DB::statement('
            CREATE INDEX idx_accesos_accion 
            ON accesos_documento(accion, created_at)
        ');

        // Índice 5: documentos - búsqueda por estado y tipo
        DB::statement('
            CREATE INDEX idx_documentos_estado_tipo 
            ON documentos(estado, tipo_documento_id, deleted_at)
        ');

        // Índice 6: documentos_campos - búsqueda por campo específico
        DB::statement('
            CREATE INDEX idx_documentos_campos_campo 
            ON documentos_campos(campo, documento_id, deleted_at)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX idx_legal_holds_documento ON legal_holds');
        DB::statement('DROP INDEX idx_doc_ubicacion_fisica ON documento_ubicacion');
        DB::statement('DROP INDEX idx_impresiones_user ON impresiones_log');
        DB::statement('DROP INDEX idx_accesos_accion ON accesos_documento');
        DB::statement('DROP INDEX idx_documentos_estado_tipo ON documentos');
        DB::statement('DROP INDEX idx_documentos_campos_campo ON documentos_campos');
    }
};

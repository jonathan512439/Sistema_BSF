<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function ledger($documentoId)
    {
        // Usamos la conexión 'audit' (bsf_audit) configurada en config/database.php
        $rows = DB::connection('audit')
            ->table('ledger')
            ->where('objeto_tipo', 'documento')
            ->where('objeto_id', (int) $documentoId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($rows);
    }

    public function accessLog($documentoId)
    {
        $rows = DB::connection('mysql')
            ->table('accesos_documento')
            ->select('id', 'user_id', 'accion', 'motivo_id', 'created_at')
            ->where('documento_id', (int) $documentoId)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($rows);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\LegalHold;
use App\Services\AuditLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LegalHoldController extends Controller
{
    protected $audit;

    public function __construct(AuditLedgerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Activar una Retención Legal sobre un documento.
     */
    public function store(Request $request, int $documentoId)
    {
        // Solo administradores o archivistas deberían poder hacer esto
        // Aquí asumimos un middleware o policy, pero validamos rol básico
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['superadmin', 'archivist'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        $documento = Documento::findOrFail($documentoId);

        // Verificar si ya tiene un hold activo
        $existingHold = LegalHold::where('documento_id', $documentoId)
            ->whereNull('levantado_en')
            ->first();

        if ($existingHold) {
            return response()->json(['error' => 'El documento ya tiene una Retención Legal activa.'], 409);
        }

        DB::transaction(function () use ($documento, $request, $user) {
            $hold = LegalHold::create([
                'documento_id' => $documento->id,
                'motivo' => $request->input('motivo'),
                'activado_por' => $user->id,
                'activado_en' => now(),
            ]);

            // Auditoría
            $this->audit->append(
                'LEGAL_HOLD_ACTIVATED',
                $user->id,
                'documento',
                $documento->id,
                ['hold_id' => $hold->id, 'motivo' => $hold->motivo],
                $request->ip(),
                $request->userAgent()
            );
        });

        return response()->json(['message' => 'Retención Legal activada correctamente.']);
    }

    /**
     * Levantar (desactivar) una Retención Legal.
     */
    public function destroy(Request $request, int $documentoId)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['superadmin', 'archivist'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $documento = Documento::findOrFail($documentoId);

        $activeHold = LegalHold::where('documento_id', $documentoId)
            ->whereNull('levantado_en')
            ->first();

        if (!$activeHold) {
            return response()->json(['error' => 'No hay Retención Legal activa para este documento.'], 404);
        }

        DB::transaction(function () use ($documento, $activeHold, $user, $request) {
            $activeHold->levantado_en = now();
            $activeHold->save();

            // Auditoría
            $this->audit->append(
                'LEGAL_HOLD_LIFTED',
                $user->id,
                'documento',
                $documento->id,
                ['hold_id' => $activeHold->id],
                $request->ip(),
                $request->userAgent()
            );
        });

        return response()->json(['message' => 'Retención Legal levantada correctamente.']);
    }
}

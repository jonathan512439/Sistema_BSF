<?php

namespace App\Observers;

use App\Models\Documento;
use App\Models\LegalHold;
use App\Services\AuditLedgerService;
use Illuminate\Support\Facades\Auth;

class DocumentoObserver
{
    protected $audit;

    public function __construct(AuditLedgerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Handle the Documento "deleting" event.
     * Used to prevent deletion if Legal Hold exists.
     */
    public function deleting(Documento $documento)
    {
        // Verificar si hay Retención Legal activa
        $activeHold = LegalHold::where('documento_id', $documento->id)
            ->whereNull('levantado_en')
            ->exists();

        if ($activeHold) {
            // Abortar la eliminación
            // En Laravel, retornar false en deleting cancela la operación
            // O lanzar una excepción para ser más explícitos
            throw new \Exception("No se puede eliminar el documento: Tiene una Retención Legal activa.");
        }
    }

    /**
     * Handle the Documento "deleted" event.
     * Used to log the soft delete to the Audit Ledger.
     */
    public function deleted(Documento $documento)
    {
        // Si es un soft delete (deleted_at se acaba de setear)
        if ($documento->isDirty('deleted_at')) {
            $user = Auth::user();
            $userId = $user ? $user->id : null;
            
            // Intentar obtener el motivo si se pasó (esto es tricky con Eloquent estándar, 
            // asumiremos un motivo genérico o intentaremos leerlo de request si es posible,
            // pero por ahora 'Soft Delete via App' es suficiente para cerrar la brecha).
            $reason = request()->input('delete_reason', 'Eliminado desde la aplicación');

            $payload = [
                'table' => 'documentos',
                'pk' => 'id',
                'id' => $documento->id,
                'reason' => $reason,
                'deleted_at' => $documento->deleted_at
            ];

            $this->audit->append(
                'SOFT_DELETE',
                $userId,
                'row', // Tipo de objeto genérico para filas, o 'documento'
                $documento->id,
                $payload,
                request()->ip(),
                request()->userAgent()
            );
        }
    }
}

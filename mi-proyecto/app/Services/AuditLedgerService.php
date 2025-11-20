<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditLedgerService
{
    /**
     * Registra un evento en bsf_audit.ledger mediante el SP sp_ledger_append.
     *
     * @param string      $evento       Nombre del evento lógico (ej. 'documento.upload')
     * @param int|null    $actorId      ID del usuario (puede ser null si no hay)
     * @param string      $objetoTipo   Tipo de objeto (ej. 'documento')
     * @param int|null    $objetoId     ID del objeto
     * @param array       $payload      Datos adicionales (se serializan a JSON)
     * @param string|null $ip           IP en texto (IPv4/IPv6)
     * @param string|null $userAgent    User-Agent recortado
     */
    public function append(
        string $evento,
        ?int $actorId,
        string $objetoTipo,
        ?int $objetoId,
        array $payload = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        // Si no hay conexión 'audit' o el SP no existe, que lance y lo atrapamos afuera
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        DB::connection('audit')->statement(
            "CALL sp_ledger_append(?, ?, ?, ?, ?, ?, ?)",
            [
                $evento,
                $actorId,
                $objetoTipo,
                $objetoId,
                $json,
                $ip,
                $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ]
        );
    }
}

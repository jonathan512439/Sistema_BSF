<?php

namespace App\Services;

use App\Models\LedgerAnchor;
use App\Models\AnchoringConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlockchainAnchorService
{
    /**
     * Crear un nuevo ancla usando el stored procedure
     */
    public function createAnchor(int $fromId, int $toId): array
    {
        try {
            // Llamar al stored procedure sp_crear_ancla
            $result = DB::connection('bsf_audit')->select(
                'CALL sp_crear_ancla(?, ?, @ancla_id, @hash_raiz)',
                [$fromId, $toId]
            );

            // Obtener los valores de salida
            $output = DB::connection('bsf_audit')->select(
                'SELECT @ancla_id as ancla_id, @hash_raiz as hash_raiz'
            )[0];

            $ancla = LedgerAnchor::find($output->ancla_id);

            Log::info('Ancla blockchain creada', [
                'ancla_id' => $output->ancla_id,
                'rango' => "{$fromId}-{$toId}",
                'hash_raiz' => $output->hash_raiz,
            ]);

            return [
                'success' => true,
                'ancla_id' => $output->ancla_id,
                'hash_raiz' => $output->hash_raiz,
                'ancla' => $ancla,
            ];

        } catch (\Exception $e) {
            Log::error('Error al crear ancla blockchain', [
                'error' => $e->getMessage(),
                'rango' => "{$fromId}-{$toId}",
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Firmar un ancla con RSA
     */
    public function signAnchor(int $anchorId, ?string $keyPath = null): bool
    {
        try {
            $ancla = LedgerAnchor::findOrFail($anchorId);

            // Ya está firmada
            if ($ancla->estaFirmada()) {
                return true;
            }

            // Obtener ruta de clave privada
            $keyPath = $keyPath ?? AnchoringConfig::get('signing_key_path');

            if (!$keyPath || !file_exists($keyPath)) {
                throw new \Exception('Clave privada no encontrada');
            }

            // Leer clave privada
            $privateKey = openssl_pkey_get_private(file_get_contents($keyPath));

            if (!$privateKey) {
                throw new \Exception('No se pudo leer la clave privada');
            }

            // Firmar el hash
            $signature = '';
            $success = openssl_sign(
                $ancla->hash_raiz,
                $signature,
                $privateKey,
                OPENSSL_ALGO_SHA256
            );

            openssl_free_key($privateKey);

            if (!$success) {
                throw new \Exception('Error al generar la firma');
            }

            // Actualizar ancla con firma
            $ancla->firmado_por = 'Sistema BSF';
            $ancla->firma_algoritmo = 'RSA-SHA256';
            $ancla->firma_bin = $signature;
            $ancla->save();

            Log::info('Ancla firmada digitalmente', [
                'ancla_id' => $anchorId,
                'algoritmo' => 'RSA-SHA256',
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error al firmar ancla', [
                'ancla_id' => $anchorId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verificar firma de un ancla
     */
    public function verifyAnchorSignature(int $anchorId, ?string $publicKeyPath = null): bool
    {
        try {
            $ancla = LedgerAnchor::findOrFail($anchorId);

            if (!$ancla->estaFirmada()) {
                return false;
            }

            // Obtener ruta de clave pública
            $publicKeyPath = $publicKeyPath ?? str_replace(
                'private',
                'public',
                AnchoringConfig::get('signing_key_path', '')
            );

            if (!$publicKeyPath || !file_exists($publicKeyPath)) {
                throw new \Exception('Clave pública no encontrada');
            }

            // Leer clave pública
            $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));

            if (!$publicKey) {
                throw new \Exception('No se pudo leer la clave pública');
            }

            // Verificar firma
            $result = openssl_verify(
                $ancla->hash_raiz,
                $ancla->firma_bin,
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            openssl_free_key($publicKey);

            return $result === 1;

        } catch (\Exception $e) {
            Log::error('Error al verificar firma de ancla', [
                'ancla_id' => $anchorId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Obtener próximo rango para anclar
     */
    public function getNextAnchorRange(): ?array
    {
        $lastAnchorId = AnchoringConfig::getNumber('last_anchor_id', 0);
        $blockSize = AnchoringConfig::getNumber('anchor_block_size', 1000);

        // Obtener último ID del ledger
        $maxLedgerId = DB::connection('bsf_audit')
            ->table('ledger')
            ->max('id');

        if (!$maxLedgerId) {
            return null;
        }

        $fromId = $lastAnchorId + 1;
        $toId = min($fromId + $blockSize - 1, $maxLedgerId);

        // Verificar si hay suficientes registros
        $available = $toId - $fromId + 1;

        if ($available < $blockSize && $toId < $maxLedgerId) {
            // Esperar a tener bloque completo
            return null;
        }

        return [
            'from_id' => $fromId,
            'to_id' => $toId,
            'count' => $available,
        ];
    }

    /**
     * Verificar integridad de la cadena de anclas
     */
    public function verifyAnchorChain(): array
    {
        $anclas = LedgerAnchor::orderBy('id')->get();
        $results = [];
        $allValid = true;

        foreach ($anclas as $ancla) {
            $valid = true;
            $issues = [];

            // Verificar firma si existe
            if ($ancla->estaFirmada()) {
                if (!$this->verifyAnchorSignature($ancla->id)) {
                    $valid = false;
                    $issues[] = 'Firma inválida';
                }
            }

            // Verificar hash recalculando
            $recalculatedHash = $this->recalculateAnchorHash($ancla->desde_id, $ancla->hasta_id);

            if ($recalculatedHash !== $ancla->hash_raiz) {
                $valid = false;
                $issues[] = 'Hash no coincide con ledger';
            }

            $results[] = [
                'ancla_id' => $ancla->id,
                'rango' => $ancla->rango,
                'valid' => $valid,
                'firmada' => $ancla->estaFirmada(),
                'publicada' => $ancla->estaPublicada(),
                'issues' => $issues,
            ];

            if (!$valid) {
                $allValid = false;
            }
        }

        return [
            'total_anclas' => count($results),
            'all_valid' => $allValid,
            'anclas' => $results,
        ];
    }

    /**
     * Recalcular hash de un ancla para verificación
     */
    protected function recalculateAnchorHash(int $fromId, int $toId): string
    {
        $hashes = DB::connection('bsf_audit')
            ->table('ledger')
            ->whereBetween('id', [$fromId, $toId])
            ->orderBy('id')
            ->pluck('hash_self');

        $concatenated = $hashes->implode('');

        return hash('sha256', $concatenated);
    }
}

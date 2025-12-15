<?php

namespace App\Console\Commands;

use App\Services\BlockchainAnchorService;
use App\Models\AnchoringConfig;
use Illuminate\Console\Command;

class LedgerAnchorRotate extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ledger:anchor-rotate {--force : Forzar creación de ancla}';

    /**
     * The console command description.
     */
    protected $description = 'Crear ancla de blockchain para registros del ledger';

    /**
     * Execute the console command.
     */
    public function handle(BlockchainAnchorService $anchorService)
    {
        $this->info('🔗 Iniciando rotación de ancla blockchain...');

        // Verificar si está habilitado
        $enabled = AnchoringConfig::getBoolean('auto_anchor_enabled', false);

        if (!$enabled && !$this->option('force')) {
            $this->warn('⚠️  Anclaje automático deshabilitado en configuración');
            $this->info('    Usar --force para forzar creación de ancla');
            return 0;
        }

        // Obtener próximo rango
        $range = $anchorService->getNextAnchorRange();

        if (!$range) {
            $this->info('ℹ️  No hay suficientes registros para crear una nueva ancla');
            $this->info('    Esperando más entradas en el ledger...');
            return 0;
        }

        $this->info("📊 Rango detectado: {$range['from_id']} - {$range['to_id']} ({$range['count']} registros)");

        // Crear ancla
        $this->info('⏳ Creando ancla...');
        $result = $anchorService->createAnchor($range['from_id'], $range['to_id']);

        if (!$result['success']) {
            $this->error('❌ Error al crear ancla: ' . $result['error']);
            return 1;
        }

        $this->info("✅ Ancla creada exitosamente");
        $this->info("   ID: {$result['ancla_id']}");
        $this->info("   Hash: " . substr($result['hash_raiz'], 0, 16) . '...');

        // Firmar si está habilitado
        $signingEnabled = AnchoringConfig::getBoolean('signing_enabled', false);

        if ($signingEnabled) {
            $this->info('🔐 Firmando ancla...');

            $signed = $anchorService->signAnchor($result['ancla_id']);

            if ($signed) {
                $this->info('✅ Ancla firmada digitalmente');
            } else {
                $this->warn('⚠️  No se pudo firmar el ancla');
            }
        }

        // TODO: Publicar en blockchain si está habilitado
        $blockchainEnabled = AnchoringConfig::getBoolean('blockchain_publish_enabled', false);

        if ($blockchainEnabled) {
            $this->info('🌐 Publicación en blockchain deshabilitada temporalmente');
            // $anchorService->publishToBlockchain($result['ancla_id']);
        }

        $this->info('🎉 Proceso completado exitosamente');

        return 0;
    }
}

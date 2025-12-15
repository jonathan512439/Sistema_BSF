<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blockchain Anchoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración del sistema de anclaje blockchain para el ledger de
    | auditoría. Los valores se pueden override por variables de entorno.
    |
    */

    // Habilitar/deshabilitar anclaje automático
    'enabled' => env('ANCHORING_ENABLED', true),

    // Tamaño del bloque (número de registros del ledger por ancla)
    'block_size' => env('ANCHORING_BLOCK_SIZE', 1000),

    // Intervalo de rotación en horas
    'interval_hours' => env('ANCHORING_INTERVAL_HOURS', 24),

    // Configuración de firma digital
    'signing' => [
        'enabled' => env('ANCHORING_SIGNING_ENABLED', false),
        'algorithm' => env('ANCHORING_ALGORITHM', 'RSA-SHA256'),
        'key_path' => env('ANCHORING_KEY_PATH', storage_path('keys/anchor_private.pem')),
        'public_key_path' => env('ANCHORING_PUBLIC_KEY_PATH', storage_path('keys/anchor_public.pem')),
    ],

    // Configuración de publicación en blockchain externo
    'blockchain' => [
        'enabled' => env('BLOCKCHAIN_PUBLISH_ENABLED', false),
        'network' => env('BLOCKCHAIN_NETWORK', 'ethereum-testnet'),
        'contract_address' => env('BLOCKCHAIN_CONTRACT', null),
        'rpc_url' => env('BLOCKCHAIN_RPC_URL', null),
    ],
];

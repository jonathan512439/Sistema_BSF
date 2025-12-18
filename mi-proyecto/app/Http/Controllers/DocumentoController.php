<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\DocumentoArchivo;
use App\Models\DocumentoCampo;
use App\Models\DocumentoVersion;
use App\Services\AuditLedgerService;
use App\Services\OcrClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentoController extends Controller
{
    protected AuditLedgerService $audit;
    protected OcrClient $ocr;

    public function __construct(AuditLedgerService $audit, OcrClient $ocr)
    {
        $this->audit = $audit;
        $this->ocr = $ocr;
    }

    /**
     * Resuelve el usuario actual (real o demo con X-Demo-User / ?user_id=).
     */
    private function resolveActor(Request $request): ?object
    {
        $actor = $request->user();
        if ($actor && isset($actor->id)) {
            return $actor;
        }

        $fromHeader = $request->header('X-Demo-User');
        $fromQuery = $request->query('user_id');
        $userId = $fromHeader ?: $fromQuery;

        if ($userId) {
            $userId = (int) $userId;
            try {
                $row = DB::connection('mysql')
                    ->table('users')
                    ->where('id', $userId)
                    ->first();
            } catch (\Throwable $e) {
                $row = null;
            }

            if ($row) {
                return $row;
            }

            // Fallback demo
            return (object) [
                'id' => $userId,
                'name' => 'Usuario demo ' . $userId,
                'email' => 'demo' . $userId . '@demo.local',
            ];
        }

        return null;
    }

    // ------- Diagnóstico rápido -------
    public function health()
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
            DB::connection('mysql')->select('SELECT COUNT(*) AS c FROM documentos');
            DB::connection('mysql')->select('SELECT COUNT(*) AS c FROM almacenes');
            return response()->json(['ok' => true, 'db' => 'up']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'HEALTH_FAIL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function wmContext(Request $request)
    {
        $usr = $request->user();
        return response()->json([
            'user' => $usr?->name ?? 'guest',
            'email' => $usr?->email ?? '-',
            'ip' => $request->ip(),
            'ts' => now()->toDateTimeString(),
        ]);
    }

    // ------- Listado de documentos (sin eliminados) - CON FILTROS AVANZADOS -------
    public function index(Request $request)
    {
        try {
            // Filtros básicos
            $q = trim((string) $request->query('q', ''));

            // Filtros avanzados en cascada
            $seccionId = $request->query('seccion_id');
            $subseccionId = $request->query('subseccion_id');
            $tipoDocId = $request->query('tipo_documento_id');
            $gestionId = $request->query('gestion_id');
            $ocrText = trim((string) $request->query('ocr_text', ''));
            $isConfidential = $request->query('is_confidential'); // null, '0', '1'
            $fechaDesde = $request->query('fecha_desde');
            $fechaHasta = $request->query('fecha_hasta');

            // Paginación
            $perPage = (int) $request->query('per_page', 20);
            $perPage = min(max($perPage, 10), 50); // Entre 10 y 50

            $builder = Documento::query()
                ->select([
                    'id',
                    'titulo',
                    'estado',
                    'sha256',
                    'ocr_confidence',
                    'tipo_documento_id',
                    'seccion_id',
                    'subseccion_id',
                    'gestion_id',
                    'fecha_documento',
                    'descripcion',
                    'custodia_hash',
                    'is_confidential',
                    'created_at',
                ])
                ->whereNull('deleted_at');

            // RBAC: Si el usuario es lector, solo mostrar documentos NO confidenciales
            if ($request->user() && $request->user()->isReader()) {
                $builder->where('is_confidential', false);
            }

            // Filtro por sección
            if ($seccionId !== null && $seccionId !== '') {
                $builder->where('seccion_id', (int) $seccionId);
            }

            // Filtro por subsección
            if ($subseccionId !== null && $subseccionId !== '') {
                $builder->where('subseccion_id', (int) $subseccionId);
            }

            // Filtro por tipo de documento
            if ($tipoDocId !== null && $tipoDocId !== '') {
                $builder->where('tipo_documento_id', (int) $tipoDocId);
            }

            // Filtro por gestión
            if ($gestionId !== null && $gestionId !== '') {
                $builder->where('gestion_id', (int) $gestionId);
            }

            // Filtro por confidencialidad (solo para archivistas/superadmins)
            if ($isConfidential !== null && $isConfidential !== '') {
                $builder->where('is_confidential', (bool) $isConfidential);
            }

            // Filtro por rango de fechas
            if ($fechaDesde) {
                $builder->where('fecha_documento', '>=', $fechaDesde);
            }
            if ($fechaHasta) {
                $builder->where('fecha_documento', '<=', $fechaHasta);
            }

            // Búsqueda por texto general (título, descripción, Y TEXTO OCR)
            if ($q !== '') {
                $like = '%' . $q . '%';

                $builder->where(function ($w) use ($like) {
                    // Búsqueda en título y descripción
                    $w->where('titulo', 'like', $like)
                        ->orWhere('descripcion', 'like', $like);

                    // NUEVO: También buscar en texto OCR extraído
                    // Coincidencias en documentos_campos (valor_ocr / valor_final)
                    $w->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))
                            ->from('documentos_campos as dc')
                            ->whereColumn('dc.documento_id', 'documentos.id')
                            ->where(function ($inner) use ($like) {
                                $inner->where('dc.valor_ocr', 'like', $like)
                                    ->orWhere('dc.valor_final', 'like', $like);
                            });
                    });

                    // Coincidencias en ocr_campos (texto completo extraído)
                    $w->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))
                            ->from('ocr_campos as oc')
                            ->whereColumn('oc.documento_id', 'documentos.id')
                            ->where('oc.valor', 'like', $like);
                    });
                });
            }

            // Búsqueda específica en OCR (MANTENIDA para compatibilidad)
            // Si se proporciona ocr_text específicamente, se aplica ADEMÁS de 'q'
            if ($ocrText !== '') {
                $ocrLike = '%' . $ocrText . '%';

                $builder->where(function ($w) use ($ocrLike) {
                    // Coincidencias en documentos_campos (valor_ocr / valor_final)
                    $w->whereExists(function ($sub) use ($ocrLike) {
                        $sub->select(DB::raw(1))
                            ->from('documentos_campos as dc')
                            ->whereColumn('dc.documento_id', 'documentos.id')
                            ->where(function ($inner) use ($ocrLike) {
                                $inner->where('dc.valor_ocr', 'like', $ocrLike)
                                    ->orWhere('dc.valor_final', 'like', $ocrLike);
                            });
                    });

                    // Coincidencias en ocr_campos (texto completo)
                    $w->orWhereExists(function ($sub) use ($ocrLike) {
                        $sub->select(DB::raw(1))
                            ->from('ocr_campos as oc')
                            ->whereColumn('oc.documento_id', 'documentos.id')
                            ->where('oc.valor', 'like', $ocrLike);
                    });
                });
            }

            // Ordenamiento
            $sortBy = $request->query('sort_by', 'created_at');
            $sortDir = $request->query('sort_dir', 'desc');

            $allowedSort = ['id', 'created_at', 'fecha_documento', 'titulo', 'estado'];
            if (!in_array($sortBy, $allowedSort)) {
                $sortBy = 'created_at';
            }
            if (!in_array($sortDir, ['asc', 'desc'])) {
                $sortDir = 'desc';
            }

            $builder->orderBy($sortBy, $sortDir);

            // Paginación
            $docs = $builder->paginate($perPage);

            return response()->json($docs);
        } catch (\Throwable $e) {
            Log::error('LIST_FAIL: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'DB_LIST',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ------- Estadísticas para Dashboard -------
    public function getStatistics(Request $request)
    {
        try {
            $user = $request->user();
            $isReader = $user && $user->isReader();

            // Query base (respeta RBAC)
            $baseQuery = Documento::query()->whereNull('documentos.deleted_at');

            if ($isReader) {
                $baseQuery->where('documentos.is_confidential', false);
            }

            // Total de documentos (accesibles por el usuario)
            $total = (clone $baseQuery)->count();

            // Confidenciales vs No confidenciales (solo si NO es reader)
            $confidenciales = 0;
            $noConfidenciales = $total;

            if (!$isReader) {
                $confidenciales = (clone $baseQuery)->where('is_confidential', true)->count();
                $noConfidenciales = (clone $baseQuery)->where('is_confidential', false)->count();
            }

            // Por sección
            $porSeccion = (clone $baseQuery)
                ->select('secciones.nombre as seccion', DB::raw('COUNT(documentos.id) as total'))
                ->leftJoin('secciones', 'documentos.seccion_id', '=', 'secciones.id')
                ->groupBy('secciones.nombre')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            // Por tipo de documento
            $porTipo = (clone $baseQuery)
                ->select('tipos_documento.nombre as tipo', DB::raw('COUNT(documentos.id) as total'))
                ->leftJoin('tipos_documento', 'documentos.tipo_documento_id', '=', 'tipos_documento.id')
                ->groupBy('tipos_documento.nombre')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            // Por estado
            $porEstado = (clone $baseQuery)
                ->select('estado', DB::raw('COUNT(*) as total'))
                ->groupBy('estado')
                ->get();

            // Últimos 5 documentos subidos
            $ultimosSubidos = (clone $baseQuery)
                ->select('documentos.id', 'documentos.titulo', 'documentos.estado', 'documentos.created_at', 'documentos.is_confidential')
                ->orderBy('documentos.created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'ok' => true,
                'total_documentos' => $total,
                'confidenciales' => $confidenciales,
                'no_confidenciales' => $noConfidenciales,
                'por_seccion' => $porSeccion,
                'por_tipo' => $porTipo,
                'por_estado' => $porEstado,
                'ultimos_subidos' => $ultimosSubidos,
            ]);
        } catch (\Throwable $e) {
            Log::error('STATS_FAIL: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'STATS_ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ------- Thumbnail de documento (preview primera página) -------
    public function getThumbnail(Documento $documento, Request $request)
    {
        try {
            // RBAC: Verificar acceso a documentos confidenciales
            if ($request->user() && $request->user()->isReader()) {
                if ($documento->is_confidential) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'FORBIDDEN',
                        'message' => 'No tiene permisos para ver este documento.',
                    ], 403);
                }
            }

            // Obtener el archivo principal (versión 1)
            $archivo = $documento->archivos()->where('version', 1)->with('almacen')->first();

            if (!$archivo) {
                Log::warning("THUMBNAIL: No file found for documento {$documento->id}");
                return response()->json([
                    'ok' => false,
                    'error' => 'NO_FILE',
                    'message' => 'No se encontró archivo para este documento',
                ], 404);
            }

            if (!$archivo->almacen) {
                Log::error("THUMBNAIL: No almacen found for archivo {$archivo->id}");
                return response()->json([
                    'ok' => false,
                    'error' => 'NO_ALMACEN',
                    'message' => 'No se encontró almacén para este archivo',
                ], 404);
            }

            // Verificar si Imagick está disponible
            if (!extension_loaded('imagick')) {
                Log::warning("THUMBNAIL: Imagick extension not loaded for documento {$documento->id}");
                return response()->json([
                    'ok' => false,
                    'error' => 'NO_IMAGICK',
                    'message' => 'Imagick no está instalado. Instálalo para generar thumbnails.',
                ], 404);
            }

            // Generar thumbnail con Imagick
            $cacheKey = "thumb_{$documento->id}_v{$archivo->version}";
            $thumbnailDir = storage_path('app/public/thumbnails');
            $cachePath = $thumbnailDir . DIRECTORY_SEPARATOR . $cacheKey . '.jpg';

            // Si ya existe el thumbnail cacheado, devolverlo
            if (file_exists($cachePath)) {
                return response()->file($cachePath, [
                    'Content-Type' => 'image/jpeg',
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }

            // Construir ruta completa del PDF
            $basePath = rtrim($archivo->almacen->base_path, '/\\');
            $relativePath = ltrim($archivo->ruta_relativa, '/\\');
            $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;

            // Normalizar separadores para Windows
            $fullPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);

            Log::info("THUMBNAIL: Generating for documento {$documento->id}", [
                'base_path' => $basePath,
                'relative_path' => $relativePath,
                'full_path' => $fullPath,
            ]);

            // Verificar que el archivo PDF existe
            if (!file_exists($fullPath)) {
                Log::error("THUMBNAIL: File not found at {$fullPath}");
                return response()->json([
                    'ok' => false,
                    'error' => 'FILE_NOT_FOUND',
                    'message' => 'El archivo PDF no existe en el disco',
                ], 404);
            }

            // Crear directorio de thumbnails si no existe
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
                Log::info("THUMBNAIL: Created directory {$thumbnailDir}");
            }

            // Generar thumbnail con Imagick
            try {
                Log::info("THUMBNAIL: Starting Imagick for {$fullPath}");

                $imagick = new \Imagick();
                $imagick->setResolution(150, 150);  // DPI para lectura

                Log::info("THUMBNAIL: Reading PDF page [0]");
                $imagick->readImage($fullPath . '[0]'); // Leer solo la primera página

                Log::info("THUMBNAIL: Setting format and quality");
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);

                Log::info("THUMBNAIL: Creating thumbnail");
                $imagick->thumbnailImage(280, 400, true);  // 280px ancho, mantener aspect ratio

                Log::info("THUMBNAIL: Writing to {$cachePath}");
                $imagick->writeImage($cachePath);
                $imagick->clear();
                $imagick->destroy();

                Log::info("THUMBNAIL: Generated successfully at {$cachePath}");

                return response()->file($cachePath, [
                    'Content-Type' => 'image/jpeg',
                    'Cache-Control' => 'public, max-age=31536000',
                ]);

            } catch (\ImagickException $e) {
                Log::error("THUMBNAIL: Imagick generation failed", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'pdf_path' => $fullPath,
                    'cache_path' => $cachePath
                ]);

                // Retornar 404 para que el frontend use placeholder
                return response()->json([
                    'ok' => false,
                    'error' => 'IMAGICK_FAIL',
                    'message' => 'Error al generar thumbnail. Verifica que Ghostscript esté instalado.',
                    'details' => $e->getMessage(),
                ], 404);
            }

        } catch (\Throwable $e) {
            Log::error('THUMBNAIL_FAIL: ' . $e->getMessage(), [
                'doc_id' => $documento->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'THUMB_ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    // ------- Soft-delete (eliminar lógico) -------
    public function eliminar(Documento $documento, Request $request)
    {
        $actor = $this->resolveActor($request);

        if ($documento->deleted_at !== null) {
            return response()->json([
                'ok' => false,
                'error' => 'YA_ELIMINADO',
                'message' => 'El documento ya se encuentra marcado como eliminado.',
            ], 409);
        }

        // 1) Verificar legal_hold activo
        $tieneHold = false;
        try {
            $tieneHold = DB::connection('mysql')
                ->table('legal_holds')
                ->where('objeto_tipo', 'documento')
                ->where('objeto_id', $documento->id)
                ->where('activo', 1)
                ->exists();
        } catch (\Throwable $e) {
            $tieneHold = false;
        }

        if ($tieneHold) {
            return response()->json([
                'ok' => false,
                'error' => 'LEGAL_HOLD',
                'message' => 'Este documento tiene un legal hold activo y no puede eliminarse.',
            ], 409);
        }

        // 2) Motivo opcional
        $motivo = $request->input('motivo', 'Eliminado desde la demo BSF.');

        // 3) Soft-delete
        $documento->deleted_at = now();
        $documento->deleted_by = $actor?->id;
        $documento->delete_reason = $motivo;
        $documento->save();

        // 4) Auditoría externa
        try {
            $this->audit->append(
                evento: 'documento.eliminar',
                actorId: $actor?->id,
                objetoTipo: 'documento',
                objetoId: $documento->id,
                payload: [
                    'motivo' => $motivo,
                    'estado_prev' => $documento->estado,
                ],
                ip: $request->ip(),
                userAgent: (string) $request->userAgent(),
            );
        } catch (\Throwable $e) {
            // opcional
        }

        return response()->json([
            'ok' => true,
            'id' => $documento->id,
            'estado' => 'eliminado',
        ]);
    }

    // ------- Movimiento de ubicación física -------
    public function moverUbicacion(Documento $documento, Request $request)
    {
        $actor = $this->resolveActor($request);

        $data = $request->validate([
            'ubicacion_fisica_id' => 'required|integer|exists:ubicaciones_fisicas,id',
            'motivo' => 'nullable|string|max:255',
            'codigo_fisico' => 'nullable|string|max:100',
            'estado_fisico' => 'nullable|string|max:100',
        ]);

        $motivo = $data['motivo'] ?? 'Movimiento de ubicación desde la demo BSF.';

        DB::connection('mysql')->transaction(function () use ($documento, $actor, $data, $motivo, $request) {
            // Cerrar ubicación anterior (poner fecha_retiro)
            DB::table('documento_ubicacion')
                ->where('documento_id', $documento->id)
                ->whereNull('fecha_retiro')  // Campo correcto: fecha_retiro (NO hasta)
                ->update([
                    'fecha_retiro' => now(),
                    'updated_at' => now(),
                ]);

            // Crear nueva ubicación
            DB::table('documento_ubicacion')->insert([
                'documento_id' => $documento->id,
                'ubicacion_fisica_id' => $data['ubicacion_fisica_id'],
                'motivo' => $motivo,
                'codigo_fisico' => $data['codigo_fisico'] ?? null,
                'estado_fisico' => $data['estado_fisico'] ?? null,
                'responsable_id' => $actor?->id,
                'fecha_asignacion' => now(),  // Campo correcto: fecha_asignacion (NO desde)
                'fecha_retiro' => null,   // Se llenará cuando se mueva de nuevo
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $this->audit->append(
                    evento: 'documento.mover_ubicacion',
                    actorId: $actor?->id,
                    objetoTipo: 'documento',
                    objetoId: $documento->id,
                    payload: [
                        'nueva_ubicacion_id' => $data['ubicacion_fisica_id'],
                        'motivo' => $motivo,
                        'codigo_fisico' => $data['codigo_fisico'] ?? null,
                        'estado_fisico' => $data['estado_fisico'] ?? null,
                    ],
                    ip: $request->ip(),
                    userAgent: (string) $request->userAgent(),
                );
            } catch (\Throwable $e) {
                // opcional
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Ubicación física actualizada correctamente.',
        ]);
    }

    // ------- Listar documentos eliminados -------
    public function eliminados(Request $request)
    {
        try {
            $actor = $this->resolveActor($request);

            // Solo archivistas y admins pueden ver eliminados
            // En un sistema real, verificarías permisos aquí

            $documentos = Documento::onlyTrashed()
                ->with([
                    'tipoDocumento:id,nombre',
                    'seccion:id,nombre'
                ])
                ->select([
                    'id',
                    'titulo',
                    'tipo_documento_id',
                    'seccion_id',
                    'estado',
                    'deleted_at',
                    'deleted_by',
                    'delete_reason',
                    'created_at'
                ])
                ->orderBy('deleted_at', 'desc')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'titulo' => $doc->titulo,
                        'tipo_documento_id' => $doc->tipo_documento_id,
                        'tipo_documento_nombre' => $doc->tipoDocumento?->nombre,
                        'seccion_id' => $doc->seccion_id,
                        'seccion_nombre' => $doc->seccion?->nombre,
                        'estado' => $doc->estado,
                        'deleted_at' => $doc->deleted_at,
                        'deleted_by' => $doc->deleted_by,
                        'deleted_by_name' => $this->getUserName($doc->deleted_by),
                        'delete_reason' => $doc->delete_reason,
                        'created_at' => $doc->created_at,
                    ];
                });

            return response()->json([
                'ok' => true,
                'documentos' => $documentos,
                'total' => $documentos->count()
            ]);

        } catch (\Throwable $e) {
            Log::error('Error al listar eliminados: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Error al cargar documentos eliminados'
            ], 500);
        }
    }

    private function getUserName($userId)
    {
        if (!$userId)
            return 'Sistema';
        $user = \App\Models\User::find($userId);
        return $user ? $user->name : "Usuario #{$userId}";
    }

    // ------- Restaurar desde soft-delete -------
    public function restaurar(Documento $documento, Request $request)
    {
        $actor = $this->resolveActor($request);

        if ($documento->deleted_at === null) {
            return response()->json([
                'ok' => false,
                'error' => 'NO_ELIMINADO',
                'message' => 'El documento no está marcado como eliminado.',
            ], 409);
        }

        $documento->deleted_at = null;
        $documento->deleted_by = null;
        $documento->delete_reason = null;
        $documento->save();

        try {
            $this->audit->append(
                evento: 'documento.restaurar',
                actorId: $actor?->id,
                objetoTipo: 'documento',
                objetoId: $documento->id,
                payload: [
                    'mensaje' => 'Documento restaurado desde soft-delete.',
                ],
                ip: $request->ip(),
                userAgent: (string) $request->userAgent(),
            );
        } catch (\Throwable $e) {
            // opcional
        }

        return response()->json([
            'ok' => true,
            'id' => $documento->id,
            'estado' => $documento->estado,
        ]);
    }

    // ------- Upload de documento + OCR real en caliente -------
    public function upload(Request $request)
    {
        try {
            $request->validate(
                [
                    'file' => 'required|file|mimes:pdf|max:51200',
                    'titulo' => 'nullable|string|max:255',
                    'tipo_documento_id' => 'nullable|integer|exists:tipos_documento,id',
                    'seccion_id' => 'nullable|integer|exists:secciones,id',
                    'subseccion_id' => 'nullable|integer|exists:subsecciones,id',
                    'gestion_id' => 'nullable|integer|exists:gestiones,id',
                    'fecha_documento' => 'nullable|date',
                    'descripcion' => 'nullable|string|max:1000',
                ],
                [
                    'file.required' => 'Debe seleccionar un archivo PDF.',
                    'file.mimes' => 'El archivo debe ser un PDF.',
                    'file.max' => 'El archivo supera el tamaño máximo permitido.',
                ]
            );

            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $sha256 = hash('sha256', $content);

            // Buscar almacén activo en la base de datos
            // Si no existe, crear uno automáticamente
            $almacenId = DB::connection('mysql')
                ->table('almacenes')
                ->where('activo', 1)
                ->orderBy('id')
                ->value('id');

            if (!$almacenId) {
                // Crear almacén local si no existe
                // IMPORTANTE: BSF_FILES_BASE debe estar en formato Windows si estás en Windows
                // Ejemplo: D:/ruta/completa/storage/app
                $almacenId = DB::connection('mysql')->table('almacenes')->insertGetId([
                    'nombre' => 'LocalFS',
                    'tipo' => 'local',
                    'base_path' => env('BSF_FILES_BASE', storage_path('app')),
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Crear registro de documento en la base de datos con metadatos
            // Estado inicial: 'capturado' (antes de validar OCR)
            $doc = Documento::create([
                'titulo' => $request->input('titulo'),
                'sha256' => $sha256,  // Hash para verificar integridad
                'estado' => 'capturado',
                'integridad_estado' => 'pendiente',
                'tipo_documento_id' => $request->input('tipo_documento_id'),
                'seccion_id' => $request->input('seccion_id'),
                'subseccion_id' => $request->input('subseccion_id'),
                'gestion_id' => $request->input('gestion_id'),
                'fecha_documento' => $request->input('fecha_documento'),
                'descripcion' => $request->input('descripcion'),
                'is_confidential' => $request->boolean('is_confidential'), // Guardar flag de confidencialidad
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Guardar archivo PDF FÍSICAMENTE en el disco
            // La ruta se estructura como: YYYY/MM/DD/ID-CODIGO.pdf
            $relDir = date('Y/m/d/');  // Ejemplo: 2025/11/22/
            Storage::disk('local')->makeDirectory($relDir);

            // Generar nombre aleatorio para evitar conflictos
            $relPath = $relDir . $doc->id . '-' . Str::random(8) . '.pdf';

            // Guardar el contenido del archivo en el disco
            // Storage::disk('local') apunta a BSF_FILES_BASE o storage/app
            Storage::disk('local')->put($relPath, $content);

            // Registrar metadata del archivo en la tabla documentos_archivos
            DocumentoArchivo::create([
                'documento_id' => $doc->id,
                'almacen_id' => $almacenId,
                'ruta_relativa' => $relPath,  // Relativa a BSF_FILES_BASE
                'version' => 1,
                'bytes' => $file->getSize(),
                'mime' => 'application/pdf',
                'sha256' => $sha256,
                'created_at' => now(),
                'updated_at' => now(),
            ])

            ;

            // CREAR VERSIÓN INICIAL AUTOMÁTICAMENTE
            // CRÍTICO: Todo documento nuevo debe tener una versión 1 en documento_versiones
            try {
                $versionService = app(\App\Services\DocumentVersionService::class);
                $absolutePath = Storage::disk('local')->path($relPath);

                // Obtener el DocumentoArchivo recién creado
                $archivo = DocumentoArchivo::where('documento_id', $doc->id)
                    ->where('version', 1)
                    ->firstOrFail();

                $versionService->crearVersionInicial(
                    documento: $doc,
                    archivo: $archivo,
                    rutaAbsoluta: $absolutePath,
                    userId: $request->user()?->id
                );

                \Log::info("[UPLOAD] Versión inicial V1 creada para documento {$doc->id}");

            } catch (\Throwable $e) {
                // Si falla la creación de versión, revertir todo el upload
                \Log::error("[UPLOAD] Error crítico al crear versión inicial: " . $e->getMessage(), [
                    'documento_id' => $doc->id,
                    'trace' => $e->getTraceAsString()
                ]);

                // Limpiar: eliminar archivo físico y registros de BD
                try {
                    Storage::disk('local')->delete($relPath);
                    DocumentoArchivo::where('documento_id', $doc->id)->delete();
                    $doc->delete();
                } catch (\Throwable $cleanupError) {
                    \Log::error("[UPLOAD] Error en limpieza después de fallo: " . $cleanupError->getMessage());
                }

                return response()->json([
                    'ok' => false,
                    'error' => 'VERSION_CREATE_FAIL',
                    'message' => 'Error al crear versión inicial del documento. El upload fue revertido.',
                    'details' => $e->getMessage(),
                ], 500);
            }

            // PROCESAMIENTO OCR: Intenta extraer texto del PDF automáticamente
            // NOTA: Este proceso puede tardar varios minutos dependiendo del tamaño del PDF
            // El timeout está configurado en .env como OCR_TIMEOUT (default: 300 segundos)
            try {
                // Convertir ruta relativa a ruta absoluta en el disco
                // Ejemplo: storage/app/2025/11/22/123-AbCd.pdf
                $absolutePath = Storage::disk('local')->path($relPath);

                // Registrar en logs para debugging
                \Log::info("[UPLOAD] Iniciando OCR para documento {$doc->id}");
                \Log::debug("[UPLOAD] Ruta absoluta: {$absolutePath}");

                // Llamar al servicio OCR (puede tardar minutos)
                $this->runOcrJob($doc, $absolutePath);

                \Log::info("[UPLOAD] OCR completado para documento {$doc->id}");
            } catch (\Throwable $e) {
                // Si el OCR falla, el documento queda guardado pero sin texto extraído
                // Esto NO es un error fatal, el usuario puede reprocesar OCR después
                \Log::warning("[UPLOAD] OCR_ON_UPLOAD_FAIL para doc {$doc->id}: " . $e->getMessage());
            }

            // UBICACIÓN FÍSICA: Guardar si se proporcionó
            if ($request->filled('ubicacion_fisica_id')) {
                try {
                    DB::connection('mysql')->table('documento_ubicacion')->insert([
                        'documento_id' => $doc->id,
                        'ubicacion_fisica_id' => $request->input('ubicacion_fisica_id'),
                        'motivo' => $request->input('motivo_ubicacion', 'Asignación inicial al subir documento'),
                        'codigo_fisico' => $request->input('codigo_fisico'),
                        'estado_fisico' => $request->input('estado_fisico'),
                        'responsable_id' => $request->user()?->id,
                        'fecha_asignacion' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    \Log::info("[UPLOAD] Ubicación física asignada para documento {$doc->id}");
                } catch (\Throwable $e) {
                    \Log::warning("[UPLOAD] Error al asignar ubicación física: " . $e->getMessage());
                }
            }

            // Auditoría externa de upload
            try {
                $actor = $this->resolveActor($request);
                $ip = $request->ip();
                $ua = (string) $request->userAgent();

                $this->audit->append(
                    evento: 'documento.upload',
                    actorId: $actor?->id,
                    objetoTipo: 'documento',
                    objetoId: $doc->id,
                    payload: [
                        'sha256' => $sha256,
                        'bytes' => $file->getSize(),
                        'titulo' => $doc->titulo,
                        'estado' => $doc->estado,
                        'metadata' => [
                            'tipo_documento_id' => $doc->tipo_documento_id,
                            'seccion_id' => $doc->seccion_id,
                            'subseccion_id' => $doc->subseccion_id,
                            'gestion_id' => $doc->gestion_id,
                            'fecha_documento' => $doc->fecha_documento,
                        ],
                    ],
                    ip: $ip,
                    userAgent: $ua,
                );
            } catch (\Throwable $e) {
                // opcional
            }

            return response()->json(['ok' => true, 'documento_id' => $doc->id]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'ok' => false,
                'error' => 'VALIDATION',
                'messages' => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error('UPLOAD_FAIL: ' . $msg);
            if (stripos($msg, 'Duplicate') !== false && stripos($msg, 'sha256') !== false) {
                return response()->json([
                    'ok' => false,
                    'error' => 'DUPLICATE_FILE',
                    'message' => 'Este archivo ya fue cargado (sha256 repetido).',
                ], 409);
            }
            return response()->json([
                'ok' => false,
                'error' => 'UPLOAD_FAIL',
                'message' => $msg,
            ], 500);
        }
    }

    // ------- Actualizar campos del documento -------
    public function update(Documento $documento, Request $request)
    {
        try {
            // Validar campos
            $validated = $request->validate([
                'titulo' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string|max:1000',
                'tipo_documento_id' => 'nullable|integer|exists:tipos_documento,id',
                'seccion_id' => 'nullable|integer|exists:secciones,id',
                'subseccion_id' => 'nullable|integer|exists:subsecciones,id',
                'gestion_id' => 'nullable|integer|exists:gestiones,id',
                'fecha_documento' => 'nullable|date',
                'is_confidential' => 'nullable|boolean',
            ]);

            // Capturar cambios ANTES de actualizar para auditoría
            $cambios = [];
            foreach ($validated as $campo => $nuevoValor) {
                $valorAnterior = $documento->$campo;

                // Solo registrar si realmente cambió
                if ($valorAnterior != $nuevoValor) {
                    $cambios[$campo] = [
                        'antes' => $this->formatValueForAudit($campo, $valorAnterior, $documento),
                        'despues' => $this->formatValueForAudit($campo, $nuevoValor, $documento)
                    ];
                }
            }

            // Actualizar campos
            $documento->update($validated);

            // Auditoría con cambios detallados
            try {
                $actor = $this->resolveActor($request);
                $this->audit->append(
                    evento: 'documento.update',
                    actorId: $actor?->id,
                    objetoTipo: 'documento',
                    objetoId: $documento->id,
                    payload: [
                        'cambios' => $cambios,
                        'campos_modificados' => count($cambios),
                        'estado' => $documento->estado,
                    ],
                    ip: $request->ip(),
                    userAgent: (string) $request->userAgent(),
                );
            } catch (\Throwable $e) {
                // optional
            }

            return response()->json([
                'ok' => true,
                'documento' => $documento
            ]);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'ok' => false,
                'error' => 'VALIDATION',
                'messages' => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('UPDATE_FAIL: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'UPDATE_FAIL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ------- Detalle de documento (visor) -------
    public function show(Documento $documento, Request $request)
    {
        try {
            // RBAC: Verificar acceso a documentos confidenciales
            if ($request->user() && $request->user()->isReader()) {
                if ($documento->is_confidential) {
                    Log::warning('[DOC_ACCESS] Lector intentó acceder a documento confidencial', [
                        'user_id' => $request->user()->id,
                        'documento_id' => $documento->id,
                    ]);

                    return response()->json([
                        'ok' => false,
                        'error' => 'FORBIDDEN',
                        'message' => 'No tiene permisos para ver documentos confidenciales.',
                    ], 403);
                }
            }

            // ---------- Archivo actual con relación Eloquent ----------
            $archivo = $documento->archivos()->where('version', 1)->with('almacen')->first();

            $archivoData = null;
            $almacenData = null;

            if ($archivo) {
                $archivoData = [
                    'version' => $archivo->version,
                    'ruta_relativa' => $archivo->ruta_relativa,
                    'sha256' => $archivo->sha256,
                    'bytes' => $archivo->bytes,
                    'mime' => $archivo->mime,
                    'stream_url' => url('/api/stream/' . $documento->id . '/view'),
                ];

                if ($archivo->almacen) {
                    $almacenData = [
                        'id' => $archivo->almacen->id,
                        'nombre' => $archivo->almacen->nombre,
                        'tipo' => $archivo->almacen->tipo,
                        'base_path' => $archivo->almacen->base_path,
                    ];
                }
            }

            // ---------- Ubicación “simple” (vista) ----------
            $ubicacionVista = DB::connection('mysql')
                ->table('v_documento_ubicacion_actual')
                ->where('documento_id', $documento->id)
                ->first();

            // ---------- Accesos recientes ----------
            $accesos = [];
            try {
                $accesos = DB::connection('mysql')
                    ->table('accesos_documento as a')
                    ->leftJoin('motivos_acceso as m', 'a.motivo_id', '=', 'm.id')
                    ->where('a.documento_id', $documento->id)
                    ->orderByDesc('a.id')
                    ->limit(20)
                    ->get([
                        'a.id',
                        'a.user_id',
                        'a.accion',
                        'a.motivo_id',
                        'm.descripcion as motivo',
                        'a.created_at',
                    ]);
            } catch (\Throwable $e) {
                $accesos = [];
            }

            // ---------- Ubicación física actual + historial ----------
            $ubicacionActual = null;
            $ubicacionHistorial = [];

            try {
                $ubicacionesQuery = DB::connection('mysql')
                    ->table('documento_ubicacion as du')
                    ->leftJoin('ubicaciones_fisicas as uf', 'du.ubicacion_fisica_id', '=', 'uf.id')
                    ->where('du.documento_id', $documento->id)
                    ->whereNull('du.deleted_at')  // Excluir eliminadas lógicamente
                    ->orderByDesc('du.fecha_asignacion');

                $ubicacionHistorial = $ubicacionesQuery->get([
                    'du.id',
                    'du.fecha_asignacion as desde',
                    'du.fecha_retiro as hasta',
                    'du.motivo',
                    'du.codigo_fisico',
                    'du.estado_fisico',
                    'du.responsable_id',
                    'uf.id as ubicacion_id',
                    'uf.codigo',
                    'uf.descripcion',
                    'uf.nombre',  // También incluir nombre si existe
                ])->toArray();

                // La ubicación actual es la que NO tiene fecha_retiro
                $ubicacionActual = collect($ubicacionHistorial)->firstWhere('hasta', null);
            } catch (\Throwable $e) {
                Log::error('Error loading ubicacion: ' . $e->getMessage());
                $ubicacionActual = null;
                $ubicacionHistorial = [];
            }

            // ---------- Impresiones recientes ----------
            $impresiones = [];
            try {
                $impresiones = DB::connection('mysql')
                    ->table('impresiones_log')
                    ->where('documento_id', $documento->id)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get(['id', 'user_id', 'hash_copia', 'motivo', 'created_at']);
            } catch (\Throwable $e) {
                $impresiones = [];
            }

            // ---------- NUEVO: Campos OCR (tabla ocr_campos) ----------
            $camposOcr = [];
            $ocrFullText = null;
            try {
                // Texto completo (campo especial _full_text)
                $ocrFullText = DB::connection('mysql')
                    ->table('ocr_campos')
                    ->where('documento_id', $documento->id)
                    ->where('campo', '_full_text')
                    ->orderByDesc('id')
                    ->value('valor');

                // Campos estructurados (titulo, fecha, gestion, oficial, etc.)
                $camposOcr = DB::connection('mysql')
                    ->table('ocr_campos')
                    ->where('documento_id', $documento->id)
                    ->where('campo', '<>', '_full_text')
                    ->orderBy('campo')
                    ->get([
                        'campo',
                        'valor',
                        'confidence',
                    ]);
            } catch (\Throwable $e) {
                $camposOcr = [];
                $ocrFullText = null;
            }

            // ---------- NUEVO: Campos validados / OCR en documentos_campos ----------
            $camposValidados = [];
            try {
                $camposValidados = DocumentoCampo::where('documento_id', $documento->id)
                    ->orderBy('campo')
                    ->get([
                        'campo',
                        'valor_ocr',
                        'valor_final',
                        'origen',
                        'confidence',
                    ]);
            } catch (\Throwable $e) {
                $camposValidados = [];
            }

            $usr = $request->user();

            return response()->json([
                'documento' => $documento,
                'archivo' => $archivoData,
                'almacen' => $almacenData,

                'ubicacion' => $ubicacionActual,
                'ubicacion_historial' => $ubicacionHistorial,
                'accesos' => $accesos,
                'impresiones' => $impresiones,

                // NUEVO: info OCR
                'campos_ocr' => $camposOcr,
                'campos_validados' => $camposValidados,
                'ocr_full_text' => $ocrFullText,

                'watermark' => [
                    'user' => $usr?->name ?? 'guest',
                    'email' => $usr?->email ?? '-',
                    'ip' => $request->ip(),
                    'ts' => now()->toDateTimeString(),
                    'custodia_hash' => $documento->custodia_hash,
                ],
                'legal_hold' => $documento->legalHold,
                'ok' => true,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'SHOW_FAIL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint para procesar OCR de un archivo temporal ANTES de subir el documento.
     * Usado para auto-rellenar el formulario de subida.
     * POST /api/ocr/preview
     */
    public function ocrPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:51200', // Máximo 50MB
        ]);

        try {
            $file = $request->file('file');

            // Guardar temporalmente el archivo
            $tempPath = $file->store('temp');
            $absolutePath = Storage::disk('local')->path($tempPath);

            Log::info('[OCR_PREVIEW] Procesando archivo temporal', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'temp_path' => $tempPath,
            ]);

            // Llamar al servicio OCR directamente (sin crear documento)
            $data = $this->ocr->procesarDocumento($absolutePath, null);

            // Limpiar archivo temporal inmediatamente
            Storage::disk('local')->delete($tempPath);

            $confMedia = $data['confidence_media'] ?? $data['confidence'] ?? 0;
            $fields = $data['fields'] ?? [];
            $fullText = $data['full_text'] ?? ($data['texto'] ?? '');

            Log::info('[OCR_PREVIEW] OCR completado exitosamente', [
                'confidence' => $confMedia,
                'fields_count' => count($fields),
                'text_length' => strlen($fullText),
            ]);

            return response()->json([
                'ok' => true,
                'campos' => $fields,
                'full_text' => $fullText,
                'confidence' => $confMedia,
            ]);

        } catch (\Throwable $e) {
            // Asegurarse de limpiar el archivo temporal en caso de error
            if (isset($tempPath)) {
                try {
                    Storage::disk('local')->delete($tempPath);
                } catch (\Throwable $cleanupError) {
                    Log::warning('[OCR_PREVIEW] No se pudo limpiar archivo temporal', [
                        'temp_path' => $tempPath,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }

            Log::error('[OCR_PREVIEW] Error al procesar OCR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'OCR_PREVIEW_FAIL',
                'message' => 'Error al procesar OCR: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ------- Streaming (view / download / print) -------
    public function stream(Documento $documento, Request $request, ?string $accion = 'view')
    {
        $accion = $accion ?? $request->route('accion') ?? 'view';

        // RBAC: Verificar acceso a documentos confidenciales
        if ($request->user() && $request->user()->isReader()) {
            if ($documento->is_confidential) {
                Log::warning('[DOC_STREAM] Lector intentó acceder a documento confidencial', [
                    'user_id' => $request->user()->id,
                    'documento_id' => $documento->id,
                    'accion' => $accion,
                ]);

                return response()->json([
                    'message' => 'No tiene permisos para ver documentos confidenciales.',
                ], 403);
            }
        }

        // Obtener la versión actual del documento
        $versionActual = DocumentoVersion::where('documento_id', $documento->id)
            ->where('es_version_actual', true)
            ->first();

        if (!$versionActual || !$versionActual->archivo_path) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        if (!Storage::disk('local')->exists($versionActual->archivo_path)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        // Registro de impresión controlada
        if ($accion === 'print') {
            try {
                $user = $request->user();
                $userId = $user->id ?? null;

                if (!$userId) {
                    $fromHeader = $request->header('X-Demo-User');
                    $fromQuery = $request->query('user_id');
                    $userId = $fromHeader ?: $fromQuery;
                    if ($userId !== null) {
                        $userId = (int) $userId;
                    }
                }

                $prevHash = DB::connection('mysql')
                    ->table('impresiones_log')
                    ->where('documento_id', $documento->id)
                    ->orderByDesc('id')
                    ->value('hash_copia');

                if (!$prevHash) {
                    $prevHash = str_repeat('0', 64);
                }

                $now = now()->format('Y-m-d H:i:s.u');
                $base = implode('|', [
                    $prevHash,
                    $documento->id,
                    $userId ?? '-',
                    $now,
                ]);

                $hashCopia = strtoupper(hash('sha256', $base));

                DB::connection('mysql')->table('impresiones_log')->insert([
                    'documento_id' => $documento->id,
                    'user_id' => $userId,
                    'hash_copia' => $hashCopia,
                    'motivo' => null,
                    'created_at' => now(),
                ]);

                try {
                    $actor = $this->resolveActor($request);
                    $ip = $request->ip();
                    $ua = (string) $request->userAgent();

                    $this->audit->append(
                        evento: 'documento.imprimir',
                        actorId: $actor?->id,
                        objetoTipo: 'documento',
                        objetoId: $documento->id,
                        payload: [
                            'hash_copia' => $hashCopia,
                        ],
                        ip: $ip,
                        userAgent: $ua,
                    );
                } catch (\Throwable $e) {
                    // opcional
                }
            } catch (\Throwable $e) {
                // opcional
            }
        }

        $absolute = Storage::disk('local')->path($versionActual->archivo_path);
        $filename = $versionActual->archivo_nombre ?: ('documento-' . $documento->id . '.pdf');

        if ($accion === 'download') {
            return response()->download($absolute, $filename, ['Content-Type' => 'application/pdf']);
        }

        return response()->file($absolute, ['Content-Type' => 'application/pdf']);
    }

    // ------- Validación humana de campos clave -------
    public function validar(Documento $documento, Request $request)
    {
        // 1. Validar que el documento esté en estado correcto
        $estadosPermitidos = ['capturado', 'pendiente'];
        if (!in_array($documento->estado, $estadosPermitidos)) {
            return response()->json([
                'ok' => false,
                'error' => 'ESTADO_INVALIDO',
                'message' => "Solo se pueden validar documentos en estado capturado o pendiente. Estado actual: {$documento->estado}"
            ], 422);
        }

        // 2. Intentar obtener valores desde documentos_campos si no vienen en request
        $datosCampos = DocumentoCampo::where('documento_id', $documento->id)
            ->whereIn('campo', ['titulo', 'oficial', 'fecha', 'gestion'])
            ->get()
            ->keyBy('campo');

        // 3. Merge con request (request tiene prioridad)
        $datos = [
            'titulo' => $request->input('titulo') ?: ($datosCampos->get('titulo')?->valor_final ?? $datosCampos->get('titulo')?->valor_ocr ?? null),
            'oficial' => $request->input('oficial') ?: ($datosCampos->get('oficial')?->valor_final ?? $datosCampos->get('oficial')?->valor_ocr ?? null),
            'fecha' => $request->input('fecha') ?: ($datosCampos->get('fecha')?->valor_final ?? $datosCampos->get('fecha')?->valor_ocr ?? null),
            'gestion' => $request->input('gestion') ?: ($datosCampos->get('gestion')?->valor_final ?? $datosCampos->get('gestion')?->valor_ocr ?? null),
        ];

        // 4. Validar datos completos
        $validator = \Validator::make($datos, [
            'titulo' => 'required|string|min:3|max:200',
            'oficial' => 'required|string|min:3|max:120',
            'fecha' => 'required|date_format:Y-m-d',
            'gestion' => 'required|digits:4',
        ], [
            'titulo.required' => 'El título es obligatorio.',
            'titulo.min' => 'El título debe tener al menos :min caracteres.',
            'oficial.required' => 'El nombre del oficial es obligatorio.',
            'fecha.required' => 'La fecha del documento es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
            'gestion.required' => 'La gestión es obligatoria.',
            'gestion.digits' => 'La gestión debe tener exactamente :digits dígitos (ej. 2025).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'VALIDATION',
                'message' => 'Datos incompletos o inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 5. Ejecutar en transacción
        try {
            DB::transaction(function () use ($documento, $datos, $request) {
                $actor = $this->resolveActor($request);

                // Guardar/actualizar campos validados
                foreach ($datos as $campo => $valor) {
                    DocumentoCampo::updateOrCreate(
                        [
                            'documento_id' => $documento->id,
                            'campo' => $campo,
                        ],
                        [
                            'valor_final' => $valor,
                            'origen' => 'humano',
                            'updated_at' => now(),
                        ]
                    );
                }

                // Actualizar documento
                $documento->update([
                    'estado' => 'validado',
                    'validado_por' => $actor?->id,
                    'validado_en' => now(),
                    'updated_at' => now(),
                ]);

                // Auditoría
                try {
                    $this->audit->append(
                        evento: 'documento.validated',
                        actorId: $actor?->id,
                        objetoTipo: 'documento',
                        objetoId: $documento->id,
                        payload: [
                            'campos' => $datos,
                            'estado_anterior' => $documento->getOriginal('estado'),
                            'estado_nuevo' => 'validado',
                        ],
                        ip: $request->ip(),
                        userAgent: (string) $request->userAgent(),
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Audit log failed: ' . $e->getMessage());
                }
            });

            // Refresh para obtener datos actualizados
            $documento->refresh();

            return response()->json([
                'ok' => true,
                'message' => 'Documento validado exitosamente',
                'estado' => $documento->estado,
                'documento' => [
                    'id' => $documento->id,
                    'estado' => $documento->estado,
                    'validado_por' => $documento->validado_por,
                    'validado_en' => $documento->validado_en,
                ],
            ]);

        } catch (\Throwable $e) {
            \Log::error('Validation failed: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'VALIDATION_FAIL',
                'message' => 'Error al validar documento: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ------- Sellado de custodia -------
    public function sellar(Documento $documento, Request $request)
    {
        $actor = $this->resolveActor($request);

        if (!$actor || !$actor->id) {
            return response()->json([
                'ok' => false,
                'error' => 'NO_ACTOR',
                'message' => 'No hay usuario resuelto para sellar este documento.',
            ], 401);
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $ipHex = strtoupper(bin2hex(inet_pton($ip)));

        try {
            DB::connection('mysql')->statement(
                "CALL bsf_core.sp_documento_seal_custodia(?, ?, UNHEX(?), ?)",
                [
                    $documento->id,
                    (int) $actor->id,
                    $ipHex,
                    substr((string) $request->userAgent(), 0, 255),
                ]
            );

            $documento->refresh();

            try {
                $this->audit->append(
                    evento: 'documento.sealed',
                    actorId: (int) $actor->id,
                    objetoTipo: 'documento',
                    objetoId: $documento->id,
                    payload: [
                        'estado' => $documento->estado,
                        'custodia_hash' => $documento->custodia_hash,
                    ],
                    ip: $ip,
                    userAgent: (string) $request->userAgent(),
                );
            } catch (\Throwable $e) {
                // opcional
            }

            return response()->json([
                'ok' => true,
                'estado' => $documento->estado,
                'custodia_hash' => $documento->custodia_hash,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'SEAL_FAIL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ============================
    //   OCR  (TESSERACT)
    // ============================
    /**
     * Ejecuta un job de OCR para un documento.
     * Usa las tablas reales: ocr_jobs y ocr_campos.
     */
    private function runOcrJob(Documento $documento, string $absolutePath): array
    {
        // CRÍTICO: Aumentar el límite de tiempo de ejecución de PHP
        // Esto permite que el script PHP continúe ejecutándose durante el proceso OCR
        // que puede tardar varios minutos en documentos grandes
        @set_time_limit(300);  // 5 minutos en segundos
        \Log::info("[OCR_JOB] Tiempo de ejecución PHP aumentado a 300 segundos");
        // 1) Crear registro en ocr_jobs
        $jobId = DB::connection('mysql')->table('ocr_jobs')->insertGetId([
            'documento_id' => $documento->id,
            'estado' => 'en_proceso',
            'reintentos' => 0,
            'engine' => 'tesseract',
            'version' => '1.0',
            'conf_media' => null,
            'log_path' => null,
            'scheduled_at' => now(),
            'started_at' => now(),
            'finished_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // 2) Llamar al microservicio Python
            $data = $this->ocr->procesarDocumento($absolutePath, $documento->id);

            $confMedia = $data['confidence_media'] ?? $data['confidence'] ?? null;
            $fields = $data['fields'] ?? [];
            $fullText = $data['full_text'] ?? ($data['texto'] ?? '');

            // LOGGING: Ver qué devolvió el servicio OCR
            \Log::info("[OCR_JOB] Respuesta OCR para documento {$documento->id}", [
                'confidence_media' => $confMedia,
                'total_fields' => count($fields),
                'fields_keys' => array_keys($fields),
                'full_text_length' => strlen($fullText)
            ]);

            // 3) Guardar texto completo en ocr_campos (campo especial)
            if (!empty($fullText)) {
                DB::connection('mysql')->table('ocr_campos')->insert([
                    'documento_id' => $documento->id,
                    'campo' => '_full_text',
                    'valor' => $fullText,
                    'confidence' => $confMedia,
                    'bbox' => null,
                    'created_at' => now(),
                ]);
                \Log::info("[OCR_JOB] Guardado texto completo: " . strlen($fullText) . " caracteres");
            } else {
                \Log::warning("[OCR_JOB] No hay texto completo para guardar");
            }

            // 4) Guardar campos clave en ocr_campos + documentos_campos
            $camposGuardados = 0;
            foreach ($fields as $campo => $valor) {
                if ($valor === null || $valor === '') {
                    \Log::debug("[OCR_JOB] Campo '{$campo}' vacío, se omite");
                    continue;
                }

                DB::connection('mysql')->table('ocr_campos')->insert([
                    'documento_id' => $documento->id,
                    'campo' => $campo,
                    'valor' => $valor,
                    'confidence' => $confMedia,
                    'bbox' => null,
                    'created_at' => now(),
                ]);

                DocumentoCampo::updateOrCreate(
                    [
                        'documento_id' => $documento->id,
                        'campo' => $campo,
                    ],
                    [
                        'valor_ocr' => $valor,
                        'confidence' => $confMedia,
                        'origen' => 'ocr',
                        'updated_at' => now(),
                    ]
                );

                $camposGuardados++;
                \Log::debug("[OCR_JOB] Campo guardado: {$campo} = {$valor}");
            }

            \Log::info("[OCR_JOB] Total de campos guardados: {$camposGuardados}");

            // 5) Marcar job como exitoso
            DB::connection('mysql')->table('ocr_jobs')
                ->where('id', $jobId)
                ->update([
                    'estado' => 'exitoso',
                    'conf_media' => $confMedia,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            // 6) Actualizar documento SIN violar las reglas de estados de custodia
            $documento->refresh();
            $estadoOriginal = $documento->estado;

            // Estados en los que SÍ podemos dejarlo en procesado_ocr
            $estadosPermitidosParaOcr = [
                'capturado',
                'pendiente_ocr',
                'procesado_ocr',
                'validado', // opcional: si quieres permitir volver a "procesado_ocr" antes de sellar
            ];

            $camposUpdate = [
                'ocr_confidence' => $confMedia,
                'ocr_version' => 'tesseract-1.0',
                'updated_at' => now(),
            ];

            // Decidir el nuevo estado tras ejecutar OCR
            if (in_array($estadoOriginal, $estadosPermitidosParaOcr, true)) {
                // Si el documento ya está en custodio, mantener ese estado
                // Solo actualizar confidence y version sin degradar estado
                if ($estadoOriginal === 'custodio') {
                    // NO cambiamos el estado, solo actualizamos métricas de OCR
                } else {
                    $camposUpdate['estado'] = 'procesado_ocr';
                }
            }

            try {
                $documento->update($camposUpdate);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();

                // Caso típico: trigger / SP lanza 45000 "No se puede degradar estado desde custodio"
                if (str_contains($msg, 'No se puede degradar estado desde custodio')) {
                    // Reintentamos sin tocar el campo estado: solo actualizamos métricas de OCR.
                    $documento->update([
                        'ocr_confidence' => $confMedia,
                        'ocr_version' => 'tesseract-1.0',
                        'updated_at' => now(),
                    ]);
                } else {
                    throw $e;
                }
            }

            return [
                'ok' => true,
                'job_id' => $jobId,
                'confidence_media' => $confMedia,
                'fields' => $fields,
            ];
        } catch (\Throwable $e) {
            DB::connection('mysql')->table('ocr_jobs')
                ->where('id', $jobId)
                ->update([
                    'estado' => 'fallido',
                    'log_path' => substr($e->getMessage(), 0, 500),
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            throw $e;
        }
    }


    /**
     * Endpoint para reprocesar OCR manualmente desde el front:
     * POST /api/documentos/{documento}/ocr
     */
    public function procesarOcr(Documento $documento, Request $request)
    {
        // Buscar el archivo más reciente
        $archivo = DocumentoArchivo::where('documento_id', $documento->id)
            ->orderByDesc('version')
            ->first();

        if (!$archivo) {
            return response()->json([
                'ok' => false,
                'error' => 'NO_FILE',
                'message' => 'El documento no tiene archivo asociado.',
            ], 422);
        }

        $absolutePath = Storage::disk('local')->path($archivo->ruta_relativa);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'ok' => false,
                'error' => 'FILE_MISSING',
                'message' => 'El archivo físico no existe en el disco.',
            ], 500);
        }

        try {
            $result = $this->runOcrJob($documento, $absolutePath);

            // Auditoría externa del reprocesado OCR
            try {
                $actor = $this->resolveActor($request);

                $this->audit->append(
                    evento: 'documento.ocr_reprocesar',
                    actorId: $actor?->id,
                    objetoTipo: 'documento',
                    objetoId: $documento->id,
                    payload: [
                        'ocr_job_id' => $result['job_id'] ?? null,
                        'confidence_media' => $result['confidence_media'] ?? null,
                        'fields' => $result['fields'] ?? [],
                    ],
                    ip: $request->ip(),
                    userAgent: (string) $request->userAgent(),
                );
            } catch (\Throwable $e) {
                // opcional
            }

            return response()->json([
                'ok' => true,
                'documento_id' => $documento->id,
                'ocr_job_id' => $result['job_id'] ?? null,
                'confidence_media' => $result['confidence_media'] ?? null,
                'fields' => $result['fields'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'OCR_FAIL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Formatea un valor para mostrar en auditoría de manera legible
     */
    private function formatValueForAudit($campo, $valor, $documento = null)
    {
        // Si es null, retornar string legible
        if ($valor === null) {
            return '(vacío)';
        }

        // Formatear booleanos
        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        // Formatear IDs de relaciones con nombres legibles
        try {
            switch ($campo) {
                case 'seccion_id':
                    if ($valor && $nombre = DB::connection('mysql')->table('secciones')->where('id', $valor)->value('nombre')) {
                        return "$nombre (ID: $valor)";
                    }
                    break;

                case 'subseccion_id':
                    if ($valor && $nombre = DB::connection('mysql')->table('subsecciones')->where('id', $valor)->value('nombre')) {
                        return "$nombre (ID: $valor)";
                    }
                    break;

                case 'tipo_documento_id':
                    if ($valor && $nombre = DB::connection('mysql')->table('tipos_documento')->where('id', $valor)->value('nombre')) {
                        return "$nombre (ID: $valor)";
                    }
                    break;

                case 'gestion_id':
                    if ($valor && $nombre = DB::connection('mysql')->table('gestiones')->where('id', $valor)->value('nombre')) {
                        return "$nombre (ID: $valor)";
                    }
                    break;

                case 'is_confidential':
                    return $valor ? 'Confidencial' : 'Público';
            }
        } catch (\Throwable $e) {
            // Si falla la consulta, retornar el valor original
        }

        // Retornar valor como string si no es un caso especial
        return (string) $valor;
    }

    // ------- Soft Delete de Documento -------
    public function destroy(Documento $documento, Request $request)
    {
        // 1. Verificar estado permitido
        $estadosPermitidos = ['capturado', 'pendiente'];
        if (!in_array($documento->estado, $estadosPermitidos)) {
            return response()->json([
                'ok' => false,
                'error' => 'ESTADO_INVALIDO',
                'message' => "No se puede eliminar un documento en estado '{$documento->estado}'. Solo se permiten documentos en estado 'capturado' o 'pendiente'."
            ], 422);
        }

        // 2. Validar razón de eliminación
        $validated = $request->validate([
            'razon' => 'required|string|min:10|max:255'
        ], [
            'razon.required' => 'Debe proporcionar una razón para eliminar el documento',
            'razon.min' => 'La razón debe tener al menos :min caracteres',
            'razon.max' => 'La razón no puede exceder :max caracteres'
        ]);

        try {
            DB::transaction(function () use ($documento, $validated, $request) {
                $actor = $this->resolveActor($request);

                // Marcar documento como eliminado (soft delete)
                $documento->deleted_at = now();
                $documento->deleted_by = $actor?->id;
                $documento->delete_reason = $validated['razon'];
                $documento->save();

                // También marcar archivos asociados como eliminados
                $documento->archivos()->update([
                    'deleted_at' => now(),
                    'deleted_by' => $actor?->id,
                    'delete_reason' => $validated['razon']
                ]);

                // Auditoría
                try {
                    $this->audit->append(
                        evento: 'documento.delete',
                        actorId: $actor?->id,
                        objetoTipo: 'documento',
                        objetoId: $documento->id,
                        payload: [
                            'estado' => $documento->estado,
                            'razon' => $validated['razon'],
                            'titulo' => $documento->titulo,
                            'tipo' => $documento->tipo_documento_id
                        ],
                        ip: $request->ip(),
                        userAgent: (string) $request->userAgent()
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Audit log failed: ' . $e->getMessage());
                }
            });

            return response()->json([
                'ok' => true,
                'message' => 'Documento eliminado correctamente'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'VALIDATION',
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Error al eliminar documento: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'DELETE_FAIL',
                'message' => 'Error al eliminar el documento: ' . $e->getMessage()
            ], 500);
        }
    }
}

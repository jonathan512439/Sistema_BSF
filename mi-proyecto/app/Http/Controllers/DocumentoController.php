<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\DocumentoArchivo;
use App\Models\DocumentoCampo;
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
        $this->ocr   = $ocr;
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
        $fromQuery  = $request->query('user_id');
        $userId     = $fromHeader ?: $fromQuery;

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
                'id'    => $userId,
                'name'  => 'Usuario demo ' . $userId,
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
                'ok'      => false,
                'error'   => 'HEALTH_FAIL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function wmContext(Request $request)
    {
        $usr = $request->user();
        return response()->json([
            'user'  => $usr?->name ?? 'guest',
            'email' => $usr?->email ?? '-',
            'ip'    => $request->ip(),
            'ts'    => now()->toDateTimeString(),
        ]);
    }

    // ------- Listado de documentos (sin eliminados) -------
    public function index(Request $request)
{
    try {
        $q = trim((string) $request->query('q', ''));

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
                'deleted_at',
            ])
            ->whereNull('deleted_at');

        if ($q !== '') {
            $like = '%' . $q . '%';

            $builder->where(function ($w) use ($like) {
                // 1) Coincidencias en la propia tabla documentos
                $w->where('titulo', 'like', $like)
                  ->orWhere('descripcion', 'like', $like);

                // 2) Coincidencias en documentos_campos (valor_ocr / valor_final)
                $w->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('documentos_campos as dc')
                        ->whereColumn('dc.documento_id', 'documentos.id')
                        ->where(function ($inner) use ($like) {
                            $inner->where('dc.valor_ocr', 'like', $like)
                                  ->orWhere('dc.valor_final', 'like', $like);
                        });
                });

                // 3) Coincidencias en ocr_campos (texto completo u otros campos)
                $w->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('ocr_campos as oc')
                        ->whereColumn('oc.documento_id', 'documentos.id')
                        ->where('oc.valor', 'like', $like);
                });
            });
        }

        $docs = $builder
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return response()->json($docs);
    } catch (\Throwable $e) {
        Log::error('LIST_FAIL: ' . $e->getMessage());
        return response()->json([
            'ok'      => false,
            'error'   => 'DB_LIST',
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
                'ok'      => false,
                'error'   => 'YA_ELIMINADO',
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
                'ok'      => false,
                'error'   => 'LEGAL_HOLD',
                'message' => 'Este documento tiene un legal hold activo y no puede eliminarse.',
            ], 409);
        }

        // 2) Motivo opcional
        $motivo = $request->input('motivo', 'Eliminado desde la demo BSF.');

        // 3) Soft-delete
        $documento->deleted_at    = now();
        $documento->deleted_by    = $actor?->id;
        $documento->delete_reason = $motivo;
        $documento->save();

        // 4) Auditoría externa
        try {
            $this->audit->append(
                evento:     'documento.eliminar',
                actorId:    $actor?->id,
                objetoTipo: 'documento',
                objetoId:   $documento->id,
                payload: [
                    'motivo'      => $motivo,
                    'estado_prev' => $documento->estado,
                ],
                ip:        $request->ip(),
                userAgent: (string) $request->userAgent(),
            );
        } catch (\Throwable $e) {
            // opcional
        }

        return response()->json([
            'ok'     => true,
            'id'     => $documento->id,
            'estado' => 'eliminado',
        ]);
    }

    // ------- Movimiento de ubicación física -------
    public function moverUbicacion(Documento $documento, Request $request)
    {
        $actor = $this->resolveActor($request);

        $data = $request->validate([
            'ubicacion_id'   => 'required|integer|exists:ubicaciones_fisicas,id',
            'motivo'         => 'nullable|string|max:255',
            'codigo_fisico'  => 'nullable|string|max:100',
            'estado_fisico'  => 'nullable|string|max:100',
        ]);

        $motivo = $data['motivo'] ?? 'Movimiento de ubicación desde la demo BSF.';

        DB::connection('mysql')->transaction(function () use ($documento, $actor, $data, $motivo, $request) {
            DB::table('documento_ubicacion')
                ->where('documento_id', $documento->id)
                ->whereNull('hasta')
                ->update([
                    'hasta'      => now(),
                    'updated_at' => now(),
                ]);

            DB::table('documento_ubicacion')->insert([
                'documento_id'   => $documento->id,
                'ubicacion_id'   => $data['ubicacion_id'],
                'motivo'         => $motivo,
                'desde'          => now(),
                'hasta'          => null,
                'codigo_fisico'  => $data['codigo_fisico'] ?? null,
                'estado_fisico'  => $data['estado_fisico'] ?? null,
                'registrado_por' => $actor?->id,
                'registrado_en'  => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            try {
                $this->audit->append(
                    evento:     'documento.mover_ubicacion',
                    actorId:    $actor?->id,
                    objetoTipo: 'documento',
                    objetoId:   $documento->id,
                    payload: [
                        'nueva_ubicacion_id' => $data['ubicacion_id'],
                        'motivo'             => $motivo,
                        'codigo_fisico'      => $data['codigo_fisico'] ?? null,
                        'estado_fisico'      => $data['estado_fisico'] ?? null,
                    ],
                    ip:        $request->ip(),
                    userAgent: (string) $request->userAgent(),
                );
            } catch (\Throwable $e) {
                // opcional
            }
        });

        return response()->json([
            'ok'      => true,
            'message' => 'Ubicación física actualizada correctamente.',
        ]);
    }

    // ------- Restaurar desde soft-delete -------
    public function restaurar(Documento $documento, Request $request)
    {
        $actor = $this->resolveActor($request);

        if ($documento->deleted_at === null) {
            return response()->json([
                'ok'      => false,
                'error'   => 'NO_ELIMINADO',
                'message' => 'El documento no está marcado como eliminado.',
            ], 409);
        }

        $documento->deleted_at    = null;
        $documento->deleted_by    = null;
        $documento->delete_reason = null;
        $documento->save();

        try {
            $this->audit->append(
                evento:     'documento.restaurar',
                actorId:    $actor?->id,
                objetoTipo: 'documento',
                objetoId:   $documento->id,
                payload: [
                    'mensaje' => 'Documento restaurado desde soft-delete.',
                ],
                ip:        $request->ip(),
                userAgent: (string) $request->userAgent(),
            );
        } catch (\Throwable $e) {
            // opcional
        }

        return response()->json([
            'ok'     => true,
            'id'     => $documento->id,
            'estado' => $documento->estado,
        ]);
    }

    // ------- Upload de documento + OCR real en caliente -------
    public function upload(Request $request)
    {
        try {
            $request->validate(
                [
                    'file'              => 'required|file|mimes:pdf|max:51200',
                    'titulo'            => 'nullable|string|max:255',
                    'tipo_documento_id' => 'nullable|integer|exists:tipos_documento,id',
                    'seccion_id'        => 'nullable|integer|exists:secciones,id',
                    'subseccion_id'     => 'nullable|integer|exists:subsecciones,id',
                    'gestion_id'        => 'nullable|integer|exists:gestiones,id',
                    'fecha_documento'   => 'nullable|date',
                    'descripcion'       => 'nullable|string|max:1000',
                ],
                [
                    'file.required' => 'Debe seleccionar un archivo PDF.',
                    'file.mimes'    => 'El archivo debe ser un PDF.',
                    'file.max'      => 'El archivo supera el tamaño máximo permitido.',
                ]
            );

            $file    = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $sha256  = hash('sha256', $content);

            // Almacén lógico
            $almacenId = DB::connection('mysql')
                ->table('almacenes')
                ->where('activo', 1)
                ->orderBy('id')
                ->value('id');

            if (!$almacenId) {
                $almacenId = DB::connection('mysql')->table('almacenes')->insertGetId([
                    'nombre'     => 'LocalFS',
                    'tipo'       => 'local',
                    'base_path'  => env('BSF_FILES_BASE', 'C:/bsf/files'),
                    'activo'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Documento con metadatos archivísticos
            $doc = Documento::create([
                'titulo'            => $request->input('titulo'),
                'sha256'            => $sha256,
                'estado'            => 'capturado',
                'integridad_estado' => 'pendiente',
                'tipo_documento_id' => $request->input('tipo_documento_id'),
                'seccion_id'        => $request->input('seccion_id'),
                'subseccion_id'     => $request->input('subseccion_id'),
                'gestion_id'        => $request->input('gestion_id'),
                'fecha_documento'   => $request->input('fecha_documento'),
                'descripcion'       => $request->input('descripcion'),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Guardar archivo físico
            $relDir = date('Y/m/d/');
            Storage::disk('local')->makeDirectory($relDir);
            $relPath = $relDir . $doc->id . '-' . Str::random(8) . '.pdf';
            Storage::disk('local')->put($relPath, $content);

            // Registro de archivo
            DocumentoArchivo::create([
                'documento_id'  => $doc->id,
                'almacen_id'    => $almacenId,
                'ruta_relativa' => $relPath,
                'version'       => 1,
                'bytes'         => $file->getSize(),
                'mime'          => 'application/pdf',
                'sha256'        => $sha256,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Lanzar OCR real (bloqueante para la demo)
            try {
                $absolutePath = Storage::disk('local')->path($relPath);
                $this->runOcrJob($doc, $absolutePath);
            } catch (\Throwable $e) {
                Log::warning('OCR_ON_UPLOAD_FAIL: ' . $e->getMessage());
            }

            // Auditoría externa de upload
            try {
                $actor = $this->resolveActor($request);
                $ip    = $request->ip();
                $ua    = (string) $request->userAgent();

                $this->audit->append(
                    evento:     'documento.upload',
                    actorId:    $actor?->id,
                    objetoTipo: 'documento',
                    objetoId:   $doc->id,
                    payload: [
                        'sha256'   => $sha256,
                        'bytes'    => $file->getSize(),
                        'titulo'   => $doc->titulo,
                        'estado'   => $doc->estado,
                        'metadata' => [
                            'tipo_documento_id' => $doc->tipo_documento_id,
                            'seccion_id'        => $doc->seccion_id,
                            'subseccion_id'     => $doc->subseccion_id,
                            'gestion_id'        => $doc->gestion_id,
                            'fecha_documento'   => $doc->fecha_documento,
                        ],
                    ],
                    ip:        $ip,
                    userAgent: $ua,
                );
            } catch (\Throwable $e) {
                // opcional
            }

            return response()->json(['ok' => true, 'documento_id' => $doc->id]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'ok'       => false,
                'error'    => 'VALIDATION',
                'messages' => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error('UPLOAD_FAIL: ' . $msg);
            if (stripos($msg, 'Duplicate') !== false && stripos($msg, 'sha256') !== false) {
                return response()->json([
                    'ok'      => false,
                    'error'   => 'DUPLICATE_FILE',
                    'message' => 'Este archivo ya fue cargado (sha256 repetido).',
                ], 409);
            }
            return response()->json([
                'ok'      => false,
                'error'   => 'UPLOAD_FAIL',
                'message' => $msg,
            ], 500);
        }
    }

    // ------- Detalle de documento (visor) -------
    public function show(Documento $documento, Request $request)
{
    try {
        // ---------- Archivo actual (vista v_documento_archivo_actual) ----------
        $archivoVista = DB::connection('mysql')
            ->table('v_documento_archivo_actual')
            ->where('documento_id', $documento->id)
            ->first();

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
        $ubicacionActual    = null;
        $ubicacionHistorial = [];

        try {
            $ubicacionesQuery = DB::connection('mysql')
                ->table('documento_ubicacion as du')
                ->join('ubicaciones_fisicas as uf', 'uf.id', '=', 'du.ubicacion_id')
                ->where('du.documento_id', $documento->id)
                ->orderByDesc('du.desde');

            $ubicacionHistorial = $ubicacionesQuery->get([
                'du.id',
                'du.desde',
                'du.hasta',
                'du.motivo',
                'du.codigo_fisico',
                'du.estado_fisico',
                'du.registrado_por',
                'uf.id as ubicacion_id',
                'uf.codigo',
                'uf.descripcion',
            ])->toArray();

            $ubicacionActual = $ubicacionHistorial[0] ?? null;
        } catch (\Throwable $e) {
            $ubicacionActual    = null;
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
            $camposOcr   = [];
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
            'archivo'   => $archivoVista ? [
                'version'       => $archivoVista->version,
                'ruta_relativa' => $archivoVista->ruta_relativa,
                'sha256'        => $archivoVista->sha256,
                'bytes'         => $archivoVista->bytes,
                'stream_url'    => url('/api/stream/' . $documento->id . '/view'),
            ] : null,

            'ubicacion'           => $ubicacionActual ?: $ubicacionVista,
            'ubicacion_historial' => $ubicacionHistorial,
            'accesos'             => $accesos,
            'impresiones'         => $impresiones,

            // NUEVO: info OCR
            'campos_ocr'       => $camposOcr,
            'campos_validados' => $camposValidados,
            'ocr_full_text'    => $ocrFullText,

            'watermark' => [
                'user'          => $usr?->name ?? 'guest',
                'email'         => $usr?->email ?? '-',
                'ip'            => $request->ip(),
                'ts'            => now()->toDateTimeString(),
                'custodia_hash' => $documento->custodia_hash,
            ],
            'ok' => true,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok'      => false,
            'error'   => 'SHOW_FAIL',
            'message' => $e->getMessage(),
        ], 500);
    }
}


    // ------- Streaming (view / download / print) -------
    public function stream(Documento $documento, Request $request, ?string $accion = 'view')
    {
        $accion = $accion ?? $request->route('accion') ?? 'view';

        $archivo = DocumentoArchivo::where('documento_id', $documento->id)
            ->orderByDesc('version')
            ->first();

        if (!$archivo || !$archivo->ruta_relativa) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }
        if (!Storage::disk('local')->exists($archivo->ruta_relativa)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        // Registro de impresión controlada
        if ($accion === 'print') {
            try {
                $user   = $request->user();
                $userId = $user->id ?? null;

                if (!$userId) {
                    $fromHeader = $request->header('X-Demo-User');
                    $fromQuery  = $request->query('user_id');
                    $userId     = $fromHeader ?: $fromQuery;
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

                $now  = now()->format('Y-m-d H:i:s.u');
                $base = implode('|', [
                    $prevHash,
                    $documento->id,
                    $userId ?? '-',
                    $now,
                ]);

                $hashCopia = strtoupper(hash('sha256', $base));

                DB::connection('mysql')->table('impresiones_log')->insert([
                    'documento_id' => $documento->id,
                    'user_id'      => $userId,
                    'hash_copia'   => $hashCopia,
                    'motivo'       => null,
                    'created_at'   => now(),
                ]);

                try {
                    $actor = $this->resolveActor($request);
                    $ip    = $request->ip();
                    $ua    = (string) $request->userAgent();

                    $this->audit->append(
                        evento:     'documento.imprimir',
                        actorId:    $actor?->id,
                        objetoTipo: 'documento',
                        objetoId:   $documento->id,
                        payload: [
                            'hash_copia' => $hashCopia,
                        ],
                        ip:        $ip,
                        userAgent: $ua,
                    );
                } catch (\Throwable $e) {
                    // opcional
                }
            } catch (\Throwable $e) {
                // opcional
            }
        }

        $absolute = Storage::disk('local')->path($archivo->ruta_relativa);
        $filename = basename($absolute) ?: ('documento-' . $documento->id . '.pdf');

        if ($accion === 'download') {
            return response()->download($absolute, $filename, ['Content-Type' => 'application/pdf']);
        }

        return response()->file($absolute, ['Content-Type' => 'application/pdf']);
    }

    // ------- Validación humana de campos clave -------
    public function validar(Documento $documento, Request $request)
    {
        $request->validate(
            [
                'titulo'  => 'required|string|min:3|max:200',
                'oficial' => 'required|string|min:3|max:120',
                'fecha'   => 'required|date_format:Y-m-d',
                'gestion' => 'required|digits:4',
            ],
            [
                'titulo.required'   => 'El título es obligatorio.',
                'titulo.min'        => 'El título debe tener al menos :min caracteres.',
                'oficial.required'  => 'El nombre del oficial es obligatorio.',
                'fecha.required'    => 'La fecha del documento es obligatoria.',
                'fecha.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
                'gestion.required'  => 'La gestión es obligatoria.',
                'gestion.digits'    => 'La gestión debe tener exactamente :digits dígitos (ej. 2025).',
            ]
        );

        foreach (['titulo', 'oficial', 'fecha', 'gestion'] as $campo) {
            DocumentoCampo::updateOrCreate(
                [
                    'documento_id' => $documento->id,
                    'campo'        => $campo,
                ],
                [
                    'valor_final' => $request->get($campo),
                    'origen'      => 'humano',
                    'updated_at'  => now(),
                ]
            );
        }

        $actor = $this->resolveActor($request);

        $documento->update([
            'estado'       => 'validado',
            'validado_por' => $actor?->id,
            'validado_en'  => now(),
            'updated_at'   => now(),
        ]);

        try {
            $ip = $request->ip();
            $ua = (string) $request->userAgent();

            $this->audit->append(
                evento:     'documento.validar',
                actorId:    $actor?->id,
                objetoTipo: 'documento',
                objetoId:   $documento->id,
                payload: [
                    'campos' => [
                        'titulo'  => $request->get('titulo'),
                        'oficial' => $request->get('oficial'),
                        'fecha'   => $request->get('fecha'),
                        'gestion' => $request->get('gestion'),
                    ],
                    'estado' => $documento->estado,
                ],
                ip:        $ip,
                userAgent: $ua,
            );
        } catch (\Throwable $e) {
            // opcional
        }

        return response()->json([
            'ok'     => true,
            'estado' => $documento->estado,
        ]);
    }

    // ------- Sellado de custodia -------
    public function sellar(Documento $documento, Request $request)
    {
        $actor = $this->resolveActor($request);

        if (!$actor || !$actor->id) {
            return response()->json([
                'ok'      => false,
                'error'   => 'NO_ACTOR',
                'message' => 'No hay usuario resuelto para sellar este documento.',
            ], 401);
        }

        $ip    = $request->ip() ?? '127.0.0.1';
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
                    evento:     'documento.sellar',
                    actorId:    (int) $actor->id,
                    objetoTipo: 'documento',
                    objetoId:   $documento->id,
                    payload: [
                        'estado'        => $documento->estado,
                        'custodia_hash' => $documento->custodia_hash,
                    ],
                    ip:        $ip,
                    userAgent: (string) $request->userAgent(),
                );
            } catch (\Throwable $e) {
                // opcional
            }

            return response()->json([
                'ok'            => true,
                'estado'        => $documento->estado,
                'custodia_hash' => $documento->custodia_hash,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'error'   => 'SEAL_FAIL',
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
    // 1) Crear registro en ocr_jobs
    $jobId = DB::connection('mysql')->table('ocr_jobs')->insertGetId([
        'documento_id' => $documento->id,
        'estado'       => 'en_proceso',
        'reintentos'   => 0,
        'engine'       => 'tesseract',
        'version'      => '1.0',
        'conf_media'   => null,
        'log_path'     => null,
        'scheduled_at' => now(),
        'started_at'   => now(),
        'finished_at'  => null,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    try {
        // 2) Llamar al microservicio Python
        $data = $this->ocr->procesarDocumento($absolutePath, $documento->id);

        $confMedia = $data['confidence_media'] ?? $data['confidence'] ?? null;
        $fields    = $data['fields']           ?? [];
        $fullText  = $data['full_text']        ?? ($data['texto'] ?? '');

        // 3) Guardar texto completo en ocr_campos (campo especial)
        DB::connection('mysql')->table('ocr_campos')->insert([
            'documento_id' => $documento->id,
            'campo'        => '_full_text',
            'valor'        => $fullText,
            'confidence'   => $confMedia,
            'bbox'         => null,
            'created_at'   => now(),
        ]);

        // 4) Guardar campos clave en ocr_campos + documentos_campos
        foreach ($fields as $campo => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            DB::connection('mysql')->table('ocr_campos')->insert([
                'documento_id' => $documento->id,
                'campo'        => $campo,
                'valor'        => $valor,
                'confidence'   => $confMedia,
                'bbox'         => null,
                'created_at'   => now(),
            ]);

            DocumentoCampo::updateOrCreate(
                [
                    'documento_id' => $documento->id,
                    'campo'        => $campo,
                ],
                [
                    'valor_ocr'  => $valor,
                    'confidence' => $confMedia,
                    'origen'     => 'ocr',
                    'updated_at' => now(),
                ]
            );
        }

        // 5) Marcar job como exitoso
        DB::connection('mysql')->table('ocr_jobs')
            ->where('id', $jobId)
            ->update([
                'estado'      => 'exitoso',
                'conf_media'  => $confMedia,
                'finished_at' => now(),
                'updated_at'  => now(),
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
            'ocr_version'    => 'tesseract-1.0',
            'updated_at'     => now(),
        ];

        if (in_array($estadoOriginal, $estadosPermitidosParaOcr, true)) {
            $camposUpdate['estado'] = 'procesado_ocr';
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
                    'ocr_version'    => 'tesseract-1.0',
                    'updated_at'     => now(),
                ]);
            } else {
                throw $e;
            }
        }

        return [
            'ok'               => true,
            'job_id'           => $jobId,
            'confidence_media' => $confMedia,
            'fields'           => $fields,
        ];
    } catch (\Throwable $e) {
        DB::connection('mysql')->table('ocr_jobs')
            ->where('id', $jobId)
            ->update([
                'estado'      => 'fallido',
                'log_path'    => substr($e->getMessage(), 0, 500),
                'finished_at' => now(),
                'updated_at'  => now(),
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
                'ok'      => false,
                'error'   => 'NO_FILE',
                'message' => 'El documento no tiene archivo asociado.',
            ], 422);
        }

        $absolutePath = Storage::disk('local')->path($archivo->ruta_relativa);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'FILE_MISSING',
                'message' => 'El archivo físico no existe en el disco.',
            ], 500);
        }

        try {
            $result = $this->runOcrJob($documento, $absolutePath);

            // Auditoría externa del reprocesado OCR
            try {
                $actor = $this->resolveActor($request);

                $this->audit->append(
                    evento:     'documento.ocr_reprocesar',
                    actorId:    $actor?->id,
                    objetoTipo: 'documento',
                    objetoId:   $documento->id,
                    payload: [
                        'ocr_job_id'        => $result['job_id'] ?? null,
                        'confidence_media'  => $result['confidence_media'] ?? null,
                        'fields'            => $result['fields'] ?? [],
                    ],
                    ip:        $request->ip(),
                    userAgent: (string) $request->userAgent(),
                );
            } catch (\Throwable $e) {
                // opcional
            }

            return response()->json([
                'ok'               => true,
                'documento_id'     => $documento->id,
                'ocr_job_id'       => $result['job_id'] ?? null,
                'confidence_media' => $result['confidence_media'] ?? null,
                'fields'           => $result['fields'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'error'   => 'OCR_FAIL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


}

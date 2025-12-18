<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PdfService;
use App\Services\EventTranslator;

class ReportController extends Controller
{
    /**
     * Generate a report based on type and filters
     * 
     * @param Request $request
     * @return mixed
     */
    public function generate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:by-status,by-user,confidential,by-section,audit-summary',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'required|in:pdf,excel,csv'
        ]);

        $type = $request->input('type');
        $format = $request->input('format');
        $start = $request->input('start_date');
        $end = $request->input('end_date');

        try {
            switch ($type) {
                case 'by-status':
                    $data = $this->reportByStatus($start, $end);
                    break;
                case 'by-user':
                    $data = $this->reportByUser($start, $end);
                    break;
                case 'confidential':
                    $data = $this->reportConfidential();
                    break;
                case 'by-section':
                    $data = $this->reportBySection($start, $end);
                    break;
                case 'audit-summary':
                    $data = $this->reportAuditSummary($start, $end);
                    break;
                case 'audit-detailed':
                    $data = $this->reportAuditDetailed($start, $end);
                    break;
                case 'user-activity-detailed':
                    $data = $this->reportUserActivityDetailed($start, $end);
                    break;
                case 'document-access':
                    $data = $this->reportDocumentAccess($start, $end);
                    break;
                default:
                    return response()->json(['error' => 'Invalid report type'], 400);
            }

            // Export based on format
            switch ($format) {
                case 'csv':
                    return $this->exportCSV($data, $type);
                case 'excel':
                    return $this->exportCSV($data, $type); // Using CSV for now, Excel requires library
                case 'pdf':
                    return $this->exportPDF($data, $type);
                default:
                    return response()->json($data);
            }

        } catch (\Throwable $e) {
            Log::error('REPORT_GENERATE_ERROR: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'REPORT_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report: Documents by Status (Detallado)
     */
    private function reportByStatus($start = null, $end = null)
    {
        $query = DB::connection('mysql')
            ->table('documentos as d')
            ->leftJoin('tipos_documento as td', 'td.id', '=', 'd.tipo_documento_id')
            ->leftJoin('secciones as s', 's.id', '=', 'd.seccion_id')
            ->leftJoin('v_documento_ubicacion_actual as u', 'u.documento_id', '=', 'd.id')
            ->select([
                'd.id',
                'd.titulo',
                'd.estado',
                'td.nombre as tipo',
                's.nombre as seccion',
                'u.ubicacion_codigo as ubicacion',
                'd.created_at'
            ])
            ->whereNull('d.deleted_at')
            ->orderBy('d.estado')
            ->orderBy('d.created_at', 'desc');

        if ($start) {
            $query->where('d.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('d.created_at', '<=', $end . ' 23:59:59');
        }

        $rawData = $query->get();

        // Convertir a arrays y formatear
        $data = [];
        $estadoCounts = [];

        foreach ($rawData as $row) {
            $estado = ucfirst($row->estado);

            // Contar por estado
            if (!isset($estadoCounts[$estado])) {
                $estadoCounts[$estado] = 0;
            }
            $estadoCounts[$estado]++;

            $data[] = [
                'id' => $row->id,
                'titulo' => $row->titulo,
                'tipo' => $row->tipo ?? 'Sin tipo',
                'estado' => $estado,
                'seccion' => $row->seccion ?? 'Sin sección',
                'ubicacion' => $row->ubicacion ?? 'Sin ubicación',
                'fecha_creacion' => date('d/m/Y', strtotime($row->created_at))
            ];
        }

        return [
            'title' => 'Documentos por Estado - Detallado',
            'data' => $data,
            'summary' => array_merge(
                ['Total documentos' => count($data)],
                $estadoCounts
            ),
            'metadata' => [
                'start_date' => $start,
                'end_date' => $end
            ]
        ];
    }

    /**
     * Report: Activity by User
     */
    private function reportByUser($start = null, $end = null)
    {
        $query = DB::connection('audit')
            ->table('ledger as l')
            ->leftJoin('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
            ->select('u.name as usuario', DB::raw('count(*) as acciones'))
            ->whereNotNull('l.actor_id')
            ->groupBy('u.id', 'u.name')
            ->orderBy('acciones', 'desc');

        if ($start) {
            $query->where('l.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('l.created_at', '<=', $end . ' 23:59:59');
        }

        return [
            'title' => 'Actividad por Usuario',
            'data' => $query->get()->toArray()
        ];
    }

    /**
     * Report: Confidential Documents (Detallado)
     */
    private function reportConfidential()
    {
        $rawData = DB::connection('mysql')
            ->table('documentos as d')
            ->leftJoin('tipos_documento as td', 'td.id', '=', 'd.tipo_documento_id')
            ->leftJoin('secciones as s', 's.id', '=', 'd.seccion_id')
            ->leftJoin('v_documento_ubicacion_actual as u', 'u.documento_id', '=', 'd.id')
            ->select([
                'd.id',
                'd.titulo',
                'd.estado',
                'td.nombre as tipo',
                's.nombre as seccion',
                'u.ubicacion_codigo as ubicacion',
                'd.created_at'
            ])
            ->where('d.is_confidential', 1)
            ->whereNull('d.deleted_at')
            ->orderBy('d.created_at', 'desc')
            ->get();

        // Convertir a arrays y formatear
        $data = [];
        foreach ($rawData as $row) {
            $data[] = [
                'id' => $row->id,
                'titulo' => $row->titulo,
                'tipo' => $row->tipo ?? 'Sin tipo',
                'seccion' => $row->seccion ?? 'Sin sección',
                'estado' => ucfirst($row->estado),
                'ubicacion' => $row->ubicacion ?? 'Sin ubicación',
                'fecha_creacion' => date('d/m/Y', strtotime($row->created_at))
            ];
        }

        return [
            'title' => 'Documentos Confidenciales',
            'data' => $data,
            'summary' => [
                'Total documentos' => count($data)
            ]
        ];
    }

    /**
     * Report: Documents by Section
     */
    private function reportBySection($start = null, $end = null)
    {
        $query = DB::connection('mysql')
            ->table('documentos as d')
            ->leftJoin('secciones as s', 's.id', '=', 'd.seccion_id')
            ->select(
                DB::raw('COALESCE(s.nombre, "Sin sección") as seccion'),
                DB::raw('count(*) as total')
            )
            ->whereNull('d.deleted_at')
            ->groupBy('s.id', 's.nombre');

        if ($start) {
            $query->where('d.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('d.created_at', '<=', $end . ' 23:59:59');
        }

        return [
            'title' => 'Documentos por Sección',
            'data' => $query->get()->toArray()
        ];
    }

    /**
     * Report: Audit Summary (Detallado)
     * Ahora incluye TODOS los detalles de auditoría
     */
    private function reportAuditSummary($start = null, $end = null)
    {
        // Obtener datos completos del ledger con joins
        $query = DB::connection('audit')
            ->table('ledger as l')
            ->leftJoin('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
            ->leftJoin('bsf_core.documentos as d', function ($join) {
                $join->on('l.objeto_id', '=', 'd.id')
                    ->where('l.objeto_tipo', '=', 'documento');
            })
            ->select([
                'l.created_at',
                'u.name as usuario',
                'l.evento',
                'l.objeto_tipo',
                'd.titulo as documento_titulo',
                'l.objeto_id',
                'l.ip',
                'l.user_agent'
            ])
            ->orderBy('l.created_at', 'desc')
            ->limit(500);

        if ($start) {
            $query->where('l.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('l.created_at', '<=', $end . ' 23:59:59');
        }

        $rawData = $query->get();

        // Procesar y formatear TODOS los datos
        $data = [];
        $eventoCounts = [];

        foreach ($rawData as $row) {
            // Convertir IP binaria a texto legible
            $ip = $row->ip;
            if ($ip && (strlen($ip) === 4 || strlen($ip) === 16)) {
                $ip = @inet_ntop($ip) ?: 'N/A';
            } else {
                $ip = $ip ?: 'N/A';
            }

            // Extraer navegador del user_agent
            $browser = 'N/A';
            if ($row->user_agent) {
                if (str_contains($row->user_agent, 'Edge'))
                    $browser = 'Edge';
                elseif (str_contains($row->user_agent, 'Chrome'))
                    $browser = 'Chrome';
                elseif (str_contains($row->user_agent, 'Firefox'))
                    $browser = 'Firefox';
                elseif (str_contains($row->user_agent, 'Safari'))
                    $browser = 'Safari';
            }

            // Determinar qué objeto fue afectado
            $objetoAfectado = 'N/A';
            if ($row->documento_titulo) {
                $objetoAfectado = $row->documento_titulo;
            } elseif ($row->objeto_tipo) {
                $objetoAfectado = ucfirst($row->objeto_tipo) . ' #' . $row->objeto_id;
            }

            // Traducir evento
            $eventoTraducido = EventTranslator::translate($row->evento);

            // Contar eventos para summary
            if (!isset($eventoCounts[$eventoTraducido])) {
                $eventoCounts[$eventoTraducido] = 0;
            }
            $eventoCounts[$eventoTraducido]++;

            $data[] = [
                'fecha' => date('d/m/Y H:i:s', strtotime($row->created_at)),
                'usuario' => $row->usuario ?? 'Sistema',
                'evento' => $eventoTraducido,
                'objeto_afectado' => $objetoAfectado,
                'ip' => $ip,
                'navegador' => $browser
            ];
        }

        // Ordenar eventos por frecuencia para el summary
        arsort($eventoCounts);
        $topEventos = array_slice($eventoCounts, 0, 5, true);

        return [
            'title' => 'Auditoría Completa del Sistema',
            'data' => $data,
            'summary' => [
                'Total de eventos' => count($data),
                'Usuarios únicos' => count(array_unique(array_column($data, 'usuario'))),
                'IPs únicas' => count(array_unique(array_column($data, 'ip'))),
                'Evento más frecuente' => array_key_first($topEventos) ?? 'N/A',
                'Período' => ($start && $end) ? "$start a $end" : 'Todo el historial'
            ],
            'metadata' => [
                'start_date' => $start,
                'end_date' => $end
            ]
        ];
    }

    /**
     * Export data as CSV
     */
    private function exportCSV($report, $type)
    {
        $filename = "reporte_{$type}_" . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($report) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Title
            fputcsv($file, [$report['title']]);
            fputcsv($file, []); // Empty line

            // Headers
            if (!empty($report['data'])) {
                $first = (array) $report['data'][0];
                fputcsv($file, array_keys($first));

                // Data
                foreach ($report['data'] as $row) {
                    fputcsv($file, (array) $row);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
    /**
     * Export data as PDF using PdfService
     */
    private function exportPDF($report, $type)
    {
        try {
            Log::info('PDF_GENERATION_START', ['type' => $type, 'data_count' => count($report['data'] ?? [])]);

            $pdf = new PdfService();
            // Configurar información del reporte
            $subtitle = '';
            if (!empty($report['metadata']['start_date']) || !empty($report['metadata']['end_date'])) {
                $periodo = [];
                if (!empty($report['metadata']['start_date'])) {
                    $periodo[] = 'Desde: ' . date('d/m/Y', strtotime($report['metadata']['start_date']));
                }
                if (!empty($report['metadata']['end_date'])) {
                    $periodo[] = 'Hasta: ' . date('d/m/Y', strtotime($report['metadata']['end_date']));
                }
                $subtitle = implode(' - ', $periodo);
            }
            $user = auth()->user();
            $generatedBy = $user ? $user->name : 'Sistema';
            $pdf->setReportInfo($report['title'], $subtitle, $generatedBy);
            $pdf->AddPage();
            // Resumen si existe
            if (!empty($report['summary'])) {
                $pdf->addSectionTitle('Resumen');
                $pdf->addSummaryBox('Estadísticas', $report['summary']);
            }
            // Tabla de datos
            if (!empty($report['data'])) {
                $pdf->addSectionTitle('Datos');
                // Obtener encabezados - traducir nombres de columnas
                $first = (array) $report['data'][0];
                $headers = array_map('ucfirst', array_keys($first));
                // Preparar datos
                $tableData = array_map(function ($row) {
                    return (array) $row;
                }, $report['data']);
                // Determinar anchos según tipo de reporte
                $widths = $this->getColumnWidths($type, $headers);
                $pdf->addTable($headers, $tableData, $widths);
            } else {
                $pdf->addParagraph('No hay datos disponibles para el período seleccionado.');
            }
            // Generar nombre de archivo
            $filename = 'reporte_' . $type . '_' . date('Y-m-d_His') . '.pdf';
            // Retornar PDF
            $pdfContent = $pdf->generate();

            Log::info('PDF_GENERATION_SUCCESS', ['type' => $type, 'size' => strlen($pdfContent)]);
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        } catch (\Throwable $e) {
            Log::error('PDF_GENERATION_ERROR', [
                'type' => $type,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            // Fallback: generar CSV si PDF falla
            Log::info('Fallback a CSV después de error en PDF');
            return $this->exportCSV($report, $type);
        }
    }


    /**
     * Report: Detailed Audit Log
     * Reporte comprehensivo con todos los detalles de auditoría
     */
    private function reportAuditDetailed($start = null, $end = null)
    {
        // Obtener eventos del ledger con joins para información completa
        $query = DB::connection('audit')
            ->table('ledger as l')
            ->leftJoin('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
            ->select([
                'l.created_at',
                'u.name as usuario',
                'l.evento',
                'l.objeto_tipo as objeto',
                'l.objeto_id',
                'l.ip',
                'l.user_agent'
            ])
            ->orderBy('l.created_at', 'desc')
            ->limit(500);

        if ($start) {
            $query->where('l.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('l.created_at', '<=', $end . ' 23:59:59');
        }

        $rawData = $query->get();

        // Procesar y traducir datos
        $data = [];
        foreach ($rawData as $row) {
            // Convertir IP binaria a texto
            $ip = $row->ip;
            if ($ip && (strlen($ip) === 4 || strlen($ip) === 16)) {
                $ip = @inet_ntop($ip) ?: 'N/A';
            }

            // Extraer navegador del user_agent
            $browser = 'N/A';
            if ($row->user_agent) {
                if (str_contains($row->user_agent, 'Edge'))
                    $browser = 'Edge';
                elseif (str_contains($row->user_agent, 'Chrome'))
                    $browser = 'Chrome';
                elseif (str_contains($row->user_agent, 'Firefox'))
                    $browser = 'Firefox';
                elseif (str_contains($row->user_agent, 'Safari'))
                    $browser = 'Safari';
            }

            $data[] = [
                'fecha' => date('d/m/Y H:i:s', strtotime($row->created_at)),
                'usuario' => $row->usuario ?? 'Sistema',
                'evento' => EventTranslator::translate($row->evento),
                'objeto' => $row->objeto ?? 'N/A',
                'ip' => $ip,
                'navegador' => $browser
            ];
        }

        return [
            'title' => 'Auditoría Detallada del Sistema',
            'data' => $data,
            'summary' => [
                'Total de eventos' => count($data),
                'Período' => ($start && $end) ? "$start a $end" : 'Todo el historial'
            ],
            'metadata' => [
                'start_date' => $start,
                'end_date' => $end
            ]
        ];
    }

    /**
     * Report: Detailed User Activity
     * Actividad detallada por usuario
     */
    private function reportUserActivityDetailed($start = null, $end = null)
    {
        $query = DB::connection('audit')
            ->table('ledger as l')
            ->join('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
            ->select([
                'u.id',
                'u.name as usuario',
                'u.email',
                'u.rol',
                DB::raw('COUNT(*) as total_acciones'),
                DB::raw('MAX(l.created_at) as ultima_actividad'),
                DB::raw('GROUP_CONCAT(DISTINCT l.ip SEPARATOR ", ") as direcciones_ip')
            ])
            ->groupBy('u.id', 'u.name', 'u.email', 'u.rol')
            ->orderBy('total_acciones', 'desc');

        if ($start) {
            $query->where('l.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('l.created_at', '<=', $end . ' 23:59:59');
        }

        $rawData = $query->get();

        // Procesar datos
        $data = [];
        foreach ($rawData as $row) {
            // Convertir IPs binarias
            $ips = 'N/A';
            if ($row->direcciones_ip) {
                $ipList = explode(', ', $row->direcciones_ip);
                $convertedIps = [];
                foreach ($ipList as $ip) {
                    if ($ip && (strlen($ip) === 4 || strlen($ip) === 16)) {
                        $convertedIps[] = @inet_ntop($ip) ?: $ip;
                    }
                }
                $ips = implode(', ', array_unique($convertedIps));
            }

            $data[] = [
                'usuario' => $row->usuario,
                'email' => $row->email,
                'rol' => ucfirst($row->rol),
                'total_acciones' => $row->total_acciones,
                'ultima_actividad' => date('d/m/Y H:i:s', strtotime($row->ultima_actividad)),
                'ips_utilizadas' => $ips
            ];
        }

        return [
            'title' => 'Actividad Detallada por Usuario',
            'data' => $data,
            'summary' => [
                'Total usuarios activos' => count($data),
                'Total acciones' => array_sum(array_column($data, 'total_acciones'))
            ],
            'metadata' => [
                'start_date' => $start,
                'end_date' => $end
            ]
        ];
    }

    /**
     * Report: Document Access Log
     * Registro de accesos a documentos con motivos y detalles
     */
    private function reportDocumentAccess($start = null, $end = null)
    {
        $query = DB::connection('mysql')
            ->table('accesos_documento as ad')
            ->join('users as u', 'ad.user_id', '=', 'u.id')
            ->leftJoin('documentos as d', 'ad.documento_id', '=', 'd.id')
            ->leftJoin('motivos_acceso as m', 'ad.motivo_id', '=', 'm.id')
            ->select([
                'ad.created_at',
                'u.name as usuario',
                'd.titulo as documento',
                'ad.accion',
                'm.descripcion as motivo',
                'ad.ip',
                DB::raw('CASE 
                    WHEN m.can_download = 1 THEN "Descarga"
                    WHEN m.can_print = 1 THEN "Impresión"
                    WHEN m.can_view = 1 THEN "Visualización"
                    ELSE "Ninguno"
                END as permiso_otorgado')
            ])
            ->orderBy('ad.created_at', 'desc')
            ->limit(500);

        if ($start) {
            $query->where('ad.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('ad.created_at', '<=', $end . ' 23:59:59');
        }

        $rawData = $query->get();

        // Procesar datos
        $data = [];
        foreach ($rawData as $row) {
            // Convertir IP binaria
            $ip = $row->ip;
            if ($ip && (strlen($ip) === 4 || strlen($ip) === 16)) {
                $ip = @inet_ntop($ip) ?: 'N/A';
            }

            $data[] = [
                'fecha' => date('d/m/Y H:i:s', strtotime($row->created_at)),
                'usuario' => $row->usuario,
                'documento' => $row->documento ?? 'Documento eliminado',
                'accion' => EventTranslator::translate('documento.' . $row->accion),
                'motivo' => $row->motivo ?? 'Sin motivo',
                'permiso' => $row->permiso_otorgado,
                'ip' => $ip
            ];
        }

        return [
            'title' => 'Registro de Accesos a Documentos',
            'data' => $data,
            'summary' => [
                'Total de accesos' => count($data),
                'Visualizaciones' => count(array_filter($data, fn($r) => str_contains($r['accion'], 'visualizado'))),
                'Descargas' => count(array_filter($data, fn($r) => str_contains($r['accion'], 'descargado'))),
                'Impresiones' => count(array_filter($data, fn($r) => str_contains($r['accion'], 'impreso')))
            ],
            'metadata' => [
                'start_date' => $start,
                'end_date' => $end
            ]
        ];
    }

    /**
     * Helper para determinar anchos de columnas según tipo de reporte
     */
    private function getColumnWidths($type, $headers)
    {
        $numCols = count($headers);
        $totalWidth = 190;

        // Anchos personalizados según tipo de reporte
        switch ($type) {
            case 'audit-comprehensive':
            case 'audit-detailed':
                return [30, 40, 45, 35, 40];

            case 'user-activity':
                return [70, 60, 60];

            case 'document-inventory':
                return [15, 50, 35, 30, 35, 25];

            case 'confidential':
                return [15, 45, 30, 30, 35, 35];

            default:
                $colWidth = $totalWidth / $numCols;
                return array_fill(0, $numCols, $colWidth);
        }
    }
    /**
     * Get available report types
     */
    public function types()
    {
        return response()->json([
            [
                'id' => 'by-status',
                'name' => 'Documentos por Estado',
                'description' => 'Distribución de documentos según su estado actual'
            ],
            [
                'id' => 'by-user',
                'name' => 'Actividad por Usuario',
                'description' => 'Resumen de acciones realizadas por cada usuario'
            ],
            [
                'id' => 'confidential',
                'name' => 'Documentos Confidenciales',
                'description' => 'Listado completo de documentos marcados como confidenciales'
            ],
            [
                'id' => 'by-section',
                'name' => 'Distribución por Sección',
                'description' => 'Cantidad de documentos por sección'
            ],
            [
                'id' => 'audit-summary',
                'name' => 'Auditoría Completa del Sistema',
                'description' => 'Registro detallado con fecha, usuario, evento, documento/objeto, IP y navegador'
            ],
            [
                'id' => 'audit-detailed',
                'name' => 'Auditoría Detallada',
                'description' => 'Registro completo de eventos con fecha, usuario, IP y navegador'
            ],
            [
                'id' => 'user-activity-detailed',
                'name' => 'Actividad Detallada de Usuarios',
                'description' => 'Actividad completa por usuario incluyendo IPs y estadísticas'
            ],
            [
                'id' => 'document-access',
                'name' => 'Accesos a Documentos',
                'description' => 'Registro detallado de accesos con motivos y permisos otorgados'
            ]
        ]);
    }
}

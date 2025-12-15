<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Report: Documents by Status
     */
    private function reportByStatus($start = null, $end = null)
    {
        $query = DB::connection('mysql')
            ->table('documentos')
            ->select('estado', DB::raw('count(*) as total'))
            ->whereNull('deleted_at')
            ->groupBy('estado');
        
        if ($start) {
            $query->where('created_at', '>=', $start);
        }
        if ($end) {
            $query->where('created_at', '<=', $end . ' 23:59:59');
        }
        
        return [
            'title' => 'Documentos por Estado',
            'data' => $query->get()->toArray()
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
     * Report: Confidential Documents
     */
    private function reportConfidential()
    {
        $data = DB::connection('mysql')
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
        
        return [
            'title' => 'Documentos Confidenciales',
            'data' => $data->toArray()
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
     * Report: Audit Summary
     */
    private function reportAuditSummary($start = null, $end = null)
    {
        $query = DB::connection('audit')
            ->table('ledger')
            ->select('evento', DB::raw('count(*) as total'))
            ->groupBy('evento')
            ->orderBy('total', 'desc');
        
        if ($start) {
            $query->where('created_at', '>=', $start);
        }
        if ($end) {
            $query->where('created_at', '<=', $end . ' 23:59:59');
        }
        
        return [
            'title' => 'Resumen de Auditoría',
            'data' => $query->get()->toArray()
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
        
        $callback = function() use ($report) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
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
     * Export data as PDF (basic implementation)
     */
    private function exportPDF($report, $type)
    {
        // For now, return a simple HTML that can be printed as PDF from browser
        // A full PDF generation would require a library like dompdf or snappy
        
        $html = '<html><head><meta charset="UTF-8">';
        $html .= '<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #556b2f; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #556b2f; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
        </style></head><body>';
        
        $html .= '<h1>' . htmlspecialchars($report['title']) . '</h1>';
        $html .= '<p>Generado: ' . date('d/m/Y H:i:s') . '</p>';
        
        if (!empty($report['data'])) {
            $html .= '<table>';
            
            // Headers
            $first = (array) $report['data'][0];
            $html .= '<tr>';
            foreach (array_keys($first) as $key) {
                $html .= '<th>' . htmlspecialchars(ucfirst($key)) . '</th>';
            }
            $html .= '</tr>';
            
            // Data
            foreach ($report['data'] as $row) {
                $html .= '<tr>';
                foreach ((array) $row as $value) {
                    $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                }
                $html .= '</tr>';
            }
            
            $html .= '</table>';
        } else {
            $html .= '<p>No hay datos disponibles para el reporte.</p>';
        }
        
        $html .= '</body></html>';
        
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="reporte_' . $type . '.html"'
        ]);
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
                'name' => 'Resumen de Auditoría',
                'description' => 'Consolidado de movimientos en el sistema'
            ]
        ]);
    }
}

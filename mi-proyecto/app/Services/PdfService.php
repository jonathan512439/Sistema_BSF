<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Servicio centralizado para generación de PDFs usando FPDF
 * Incluye manejo robusto de errores, logo, encabezados y formato profesional
 */
class PdfService extends \FPDF
{
    private $logoPath;
    private $reportTitle;
    private $reportSubtitle;
    private $generatedBy;
    private $hasLogo = false;

    // Colores del tema BSF
    private $primaryColor = [85, 107, 47]; // Verde oliva oscuro
    private $secondaryColor = [128, 128, 128]; // Gris
    private $headerBgColor = [85, 107, 47];
    private $headerTextColor = [255, 255, 255];

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);

        // Configurar logo
        $this->logoPath = public_path('assets/logo.png');
        if (!file_exists($this->logoPath)) {
            $this->logoPath = public_path('assets/logo.svg');
        }

        if (file_exists($this->logoPath)) {
            $this->hasLogo = true;
        } else {
            Log::warning('Logo no encontrado en public/assets/');
        }

        $this->SetAutoPageBreak(true, 25);
        $this->AliasNbPages();
    }

    /**
     * Configurar información del reporte
     */
    public function setReportInfo($title, $subtitle = '', $generatedBy = '')
    {
        $this->reportTitle = $title;
        $this->reportSubtitle = $subtitle;
        $this->generatedBy = $generatedBy;
    }

    /**
     * Encabezado personalizado (se llama automáticamente en cada página)
     */
    public function Header()
    {
        try {
            // Logo
            if ($this->hasLogo) {
                $extension = pathinfo($this->logoPath, PATHINFO_EXTENSION);

                // FPDF solo soporta JPG y PNG
                if (in_array(strtolower($extension), ['png', 'jpg', 'jpeg'])) {
                    $this->Image($this->logoPath, 10, 8, 15);
                }
            }

            // Título del sistema
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
            $this->Cell(0, 6, $this->convertUtf8('SISTEMA BSF - Gestión Documental'), 0, 1, 'C');

            // Título del reporte
            if ($this->reportTitle) {
                $this->SetFont('Arial', 'B', 14);
                $this->Cell(0, 6, $this->convertUtf8($this->reportTitle), 0, 1, 'C');
            }

            // Subtítulo
            if ($this->reportSubtitle) {
                $this->SetFont('Arial', '', 10);
                $this->SetTextColor(80, 80, 80);
                $this->Cell(0, 5, $this->convertUtf8($this->reportSubtitle), 0, 1, 'C');
            }

            // Línea separadora
            $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY() + 2, 200, $this->GetY() + 2);

            $this->Ln(5);

        } catch (\Throwable $e) {
            Log::error('Error en encabezado PDF: ' . $e->getMessage());
            // Continuar sin encabezado si hay error
        }
    }

    /**
     * Pie de página personalizado
     */
    public function Footer()
    {
        try {
            $this->SetY(-15);

            // Línea separadora
            $this->SetDrawColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY(), 200, $this->GetY());

            $this->Ln(2);

            // Información de generación
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(100, 100, 100);

            $leftText = '';
            if ($this->generatedBy) {
                $leftText = 'Generado por: ' . $this->generatedBy;
            }
            $leftText .= '  |  ' . date('d/m/Y H:i:s');

            $this->Cell(0, 5, $this->convertUtf8($leftText), 0, 0, 'L');

            // Número de página
            $pageText = 'Página ' . $this->PageNo() . ' de {nb}';
            $this->Cell(0, 5, $this->convertUtf8($pageText), 0, 0, 'R');

        } catch (\Throwable $e) {
            Log::error('Error en pie de página PDF: ' . $e->getMessage());
        }
    }

    /**
     * Añadir título de sección
     */
    public function addSectionTitle($title)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(0, 8, $this->convertUtf8($title), 0, 1, 'L');
        $this->Ln(2);
    }

    /**
     * Añadir párrafo de texto
     */
    public function addParagraph($text, $align = 'L')
    {
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(0, 5, $this->convertUtf8($text), 0, $align);
        $this->Ln(2);
    }

    /**
     * Añadir tabla con encabezados y datos
     * 
     * @param array $headers Array de encabezados
     * @param array $data Array de arrays con los datos
     * @param array $widths Array con anchos de columnas (opcional)
     */
    public function addTable($headers, $data, $widths = null)
    {
        try {
            if (empty($headers)) {
                return;
            }

            // Calcular anchos automáticamente si no se proporcionan
            if (!$widths) {
                $numCols = count($headers);
                $totalWidth = 190; // Ancho total disponible
                $colWidth = $totalWidth / $numCols;
                $widths = array_fill(0, $numCols, $colWidth);
            }

            // Encabezados de tabla
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor($this->headerBgColor[0], $this->headerBgColor[1], $this->headerBgColor[2]);
            $this->SetTextColor($this->headerTextColor[0], $this->headerTextColor[1], $this->headerTextColor[2]);
            $this->SetDrawColor(200, 200, 200);

            foreach ($headers as $i => $header) {
                $this->Cell($widths[$i], 7, $this->convertUtf8($header), 1, 0, 'C', true);
            }
            $this->Ln();

            // Datos de tabla
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(0, 0, 0);
            $fill = false;

            if (empty($data)) {
                // Mostrar mensaje si no hay datos
                $this->SetFillColor(245, 245, 245);
                $this->Cell(array_sum($widths), 7, $this->convertUtf8('No hay datos disponibles'), 1, 1, 'C', true);
            } else {
                foreach ($data as $row) {
                    // Verificar si necesitamos una nueva página
                    if ($this->GetY() > 250) {
                        $this->AddPage();
                        // Re-imprimir encabezados
                        $this->SetFont('Arial', 'B', 9);
                        $this->SetFillColor($this->headerBgColor[0], $this->headerBgColor[1], $this->headerBgColor[2]);
                        $this->SetTextColor($this->headerTextColor[0], $this->headerTextColor[1], $this->headerTextColor[2]);
                        foreach ($headers as $i => $header) {
                            $this->Cell($widths[$i], 7, $this->convertUtf8($header), 1, 0, 'C', true);
                        }
                        $this->Ln();
                        $this->SetFont('Arial', '', 8);
                        $this->SetTextColor(0, 0, 0);
                    }

                    $this->SetFillColor(245, 245, 245);
                    $rowArray = is_object($row) ? (array) $row : $row;
                    $values = array_values($rowArray);

                    foreach ($values as $i => $value) {
                        if ($i >= count($widths))
                            break;

                        // Manejar valores nulos o vacíos
                        $displayValue = $value ?? 'N/A';

                        // Truncar valores muy largos
                        if (is_string($displayValue) && strlen($displayValue) > 50) {
                            $displayValue = substr($displayValue, 0, 47) . '...';
                        }

                        $this->Cell($widths[$i], 6, $this->convertUtf8((string) $displayValue), 1, 0, 'L', $fill);
                    }
                    $this->Ln();
                    $fill = !$fill;
                }
            }

            $this->Ln(3);

        } catch (\Throwable $e) {
            Log::error('Error al generar tabla en PDF: ' . $e->getMessage());
            $this->addParagraph('Error al generar tabla de datos');
        }
    }

    /**
     * Añadir estadística/métrica destacada
     */
    public function addMetric($label, $value, $width = 90)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell($width, 6, $this->convertUtf8($label . ':'), 0, 0, 'L');

        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, $this->convertUtf8((string) $value), 0, 1, 'L');
    }

    /**
     * Añadir caja de resumen
     */
    public function addSummaryBox($title, $items)
    {
        // Fondo de caja
        $this->SetFillColor(240, 240, 240);
        $this->Rect($this->GetX(), $this->GetY(), 190, 5 + (count($items) * 6) + 3, 'F');

        // Título
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(0, 5, $this->convertUtf8($title), 0, 1, 'L');

        // Items
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);
        foreach ($items as $key => $value) {
            $this->Cell(90, 6, $this->convertUtf8('  • ' . $key . ':'), 0, 0, 'L');
            $this->Cell(0, 6, $this->convertUtf8((string) $value), 0, 1, 'L');
        }

        $this->Ln(5);
    }

    /**
     * Decodificar UTF-8 con manejo robusto
     */
    private function convertUtf8($text)
    {
        if (is_null($text)) {
            return '';
        }

        // FPDF no soporta UTF-8 nativamente, necesitamos convertir
        // Usar iconv si está disponible, sino usar utf8_decode estándar
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) $text);
        }

        return utf8_decode((string) $text);
    }

    /**
     * Generar y retornar el PDF como string
     */
    public function generate()
    {
        try {
            return $this->Output('S');
        } catch (\Throwable $e) {
            Log::error('Error al generar PDF: ' . $e->getMessage());
            throw new \Exception('Error al generar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Descargar el PDF directamente
     */
    public function download($filename)
    {
        try {
            return $this->Output('D', $filename);
        } catch (\Throwable $e) {
            Log::error('Error al descargar PDF: ' . $e->getMessage());
            throw new \Exception('Error al descargar PDF: ' . $e->getMessage());
        }
    }
}

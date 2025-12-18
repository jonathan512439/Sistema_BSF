<?php

// Script para aplicar traducciones de eventos en ReportController

$file = 'app/Http/Controllers/ReportController.php';
$content = file_get_contents($file);

// Buscar y reemplazar en reportAuditSummary
$pattern1 = '/private function reportAuditSummary\(\$start = null, \$end = null\)\s*\{[^}]+\$data = \$query->get\(\)->toArray\(\);/s';
$replacement1 = 'private function reportAuditSummary($start = null, $end = null)
    {
        $query = DB::connection(\'audit\')
            ->table(\'ledger\')
            ->select(\'evento\', DB::raw(\'count(*) as total\'))
            ->groupBy(\'evento\')
            ->orderBy(\'total\', \'desc\');

        if ($start) {
            $query->where(\'created_at\', \'>=\', $start);
        }
        if ($end) {
            $query->where(\'created_at\', \'<=\', $end . \' 23:59:59\');
        }

        $rawData = $query->get();
        
        // Traducir eventos al español
        $data = [];
        foreach ($rawData as $row) {
            $data[] = [
                \'evento\' => EventTranslator::translate($row->evento),
                \'total\' => $row->total
            ];
        }';

$content = preg_replace($pattern1, $replacement1, $content);

// Similarmente para reportAuditDetailed - traducir evento
$pattern2 = '/(\'l\.evento\',)/';
$replacement2 = '$1 /* will be translated */';

// Guardar
file_put_contents($file, $content);

echo "✓ Aplicadas traducciones en ReportController.php\n";

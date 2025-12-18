<?php

namespace App\Console\Commands;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Console\Command;

class VerifyDocument extends Command
{
    protected $signature = 'doc:verify {id}';
    protected $description = 'Verify document version data';

    public function handle()
    {
        $id = $this->argument('id');
        $doc = Documento::find($id);

        if (!$doc) {
            $this->error("Document #{$id} not found");
            return 1;
        }

        $this->info("=== Document #{$id} ===");
        $this->info("Title: {$doc->titulo}");
        $this->info("Version Actual: {$doc->version_actual}");
        $this->info("Total Versiones: {$doc->total_versiones}");

        $versions = DocumentoVersion::where('documento_id', $id)->get();
        $this->info("\n=== Versions ===");
        $this->info("Count: " . $versions->count());

        foreach ($versions as $v) {
            $current = $v->es_version_actual ? ' [CURRENT]' : '';
            $this->info("  V{$v->version_numero}: ID={$v->id}, Current={$v->es_version_actual}{$current}");
        }

        // Fix if needed
        if ($doc->version_actual === null || $doc->version_actual === 0) {
            $this->warn("\nDocument has no version_actual, attempting fix...");

            $v1 = $versions->where('version_numero', 1)->first();
            if ($v1) {
                $v1->es_version_actual = true;
                $v1->save();

                $doc->version_actual = 1;
                $doc->total_versiones = $versions->count();
                $doc->save();

                $this->info("✅ Fixed! Set V1 as current");
            } else {
                $this->error("❌ No V1 found, cannot fix automatically");
            }
        }

        return 0;
    }
}

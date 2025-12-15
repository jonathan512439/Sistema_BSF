<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
{
    use SoftDeletes;

    protected $table = 'documentos';
    protected $guarded = [];
    public $timestamps = false;

    // Relación con archivos
    public function archivos()
    {
        return $this->hasMany(DocumentoArchivo::class, 'documento_id');
    }

    // Relación con el archivo principal (versión 1)
    public function archivoPrincipal()
    {
        return $this->hasOne(DocumentoArchivo::class, 'documento_id')->where('version', 1);
    }

    // Relación con Retención Legal activa
    public function legalHold()
    {
        return $this->hasOne(LegalHold::class, 'documento_id')->whereNull('levantado_en');
    }

    // Relación con ubicación física actual
    public function ubicacionActual()
    {
        return $this->hasOne(DocumentoUbicacion::class, 'documento_id')
            ->whereNull('fecha_retiro')
            ->latest('fecha_asignacion');
    }

    // Relación con historial completo de ubicaciones
    public function historialUbicaciones()
    {
        return $this->hasMany(DocumentoUbicacion::class, 'documento_id')
            ->orderBy('fecha_asignacion', 'desc');
    }

}

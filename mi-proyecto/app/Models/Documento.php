<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
{
    use SoftDeletes;

    protected $table = 'documentos';
    protected $guarded = [];

    protected $dates = ['deleted_at'];
    public $timestamps = false;

    // Relación con archivos
    public function archivos()
    {
        return $this->hasMany(DocumentoArchivo::class, 'documento_id');
    }

    // Relación con tipo de documento
    public function tipoDocumento()
    {
        return $this->belongsTo(\App\Models\TipoDocumento::class, 'tipo_documento_id');
    }

    // Relación con sección
    public function seccion()
    {
        return $this->belongsTo(\App\Models\Seccion::class, 'seccion_id');
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

    /**
     * Todas las versiones de este documento
     */
    public function versiones()
    {
        return $this->hasMany(DocumentoVersion::class, 'documento_id')
            ->orderBy('version_numero', 'desc');
    }

    /**
     * Versión actualmente activa
     */
    public function versionActualCompleta()
    {
        return $this->hasOne(DocumentoVersion::class, 'documento_id')
            ->where('es_version_actual', true);
    }

}

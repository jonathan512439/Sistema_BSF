<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoVersion extends Model
{
    protected $table = 'documento_versiones';

    // No usa timestamps automáticos (solo creado_en manual)
    public $timestamps = false;

    protected $fillable = [
        'documento_id',
        'version_numero',
        'titulo',
        'descripcion',
        'tipo_documento_id',
        'seccion_id',
        'subseccion_id',
        'gestion_id',
        'estado',
        'is_confidential',
        'archivo_path',
        'archivo_nombre',
        'archivo_size_bytes',
        'archivo_mime_type',
        'archivo_hash',
        'version_tipo',
        'version_motivo',
        'es_version_actual',
        'creado_por',
        'creado_en',
        'ip_origen',
        'user_agent',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
        'es_version_actual' => 'boolean',
        'creado_en' => 'datetime',
        'archivo_size_bytes' => 'integer',
        'version_numero' => 'integer',
    ];

    protected $appends = ['creado_por_name', 'paginas'];

    /**
     * Relación con documento principal
     */
    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Usuario que creó esta versión
     */
    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Cambios detallados de esta versión
     */
    public function cambios()
    {
        return $this->hasMany(DocumentoVersionCambio::class, 'version_id');
    }

    /**
     * Tipo de documento (relación)
     */
    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    /**
     * Sección (relación)
     */
    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    /**
     * Accessor: Nombre del usuario que creó
     */
    public function getCreadoPorNameAttribute()
    {
        return $this->creadoPor?->name ?? 'Sistema';
    }

    /**
     * Accessor: Extraer número de páginas del motivo
     * (Se guarda en version_motivo como "| Páginas: N")
     */
    public function getPaginasAttribute()
    {
        if (!$this->version_motivo) {
            return null;
        }

        if (preg_match('/Páginas:\s*(\d+)/', $this->version_motivo, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Scope: Solo versiones actuales
     */
    public function scopeActual($query)
    {
        return $query->where('es_version_actual', true);
    }

    /**
     * Scope: Por documento
     */
    public function scopeDeDocumento($query, $documentoId)
    {
        return $query->where('documento_id', $documentoId);
    }

    /**
     * Scope: Ordenar por versión desc
     */
    public function scopeRecientes($query)
    {
        return $query->orderBy('version_numero', 'desc');
    }
}

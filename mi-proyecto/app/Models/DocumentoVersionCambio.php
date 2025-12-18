<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoVersionCambio extends Model
{
    protected $table = 'documento_version_cambios';

    // No usa timestamps
    public $timestamps = false;

    protected $fillable = [
        'version_id',
        'campo',
        'valor_anterior',
        'valor_nuevo',
    ];

    /**
     * Versión a la que pertenece este cambio
     */
    public function version()
    {
        return $this->belongsTo(DocumentoVersion::class, 'version_id');
    }

    /**
     * Scope: Cambios de una versión específica
     */
    public function scopeDeVersion($query, $versionId)
    {
        return $query->where('version_id', $versionId);
    }

    /**
     * Scope: Cambios de un campo específico
     */
    public function scopeDeCampo($query, $campo)
    {
        return $query->where('campo', $campo);
    }
}

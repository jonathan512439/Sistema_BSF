<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoUbicacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documento_ubicacion';

    protected $fillable = [
        'documento_id',
        'ubicacion_fisica_id',
        'motivo',
        'codigo_fisico',
        'estado_fisico',
        'responsable_id',
        'fecha_asignacion',
        'fecha_retiro',
    ];

    protected $casts = [
        'fecha_asignacion' => 'datetime',
        'fecha_retiro' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con el documento
     */
    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Relación con la ubicación física
     */
    public function ubicacionFisica()
    {
        return $this->belongsTo(UbicacionFisica::class, 'ubicacion_fisica_id');
    }

    /**
     * Relación con el responsable (usuario)
     */
    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Scope: solo ubicaciones activas (no retiradas)
     */
    public function scopeActivas($query)
    {
        return $query->whereNull('fecha_retiro');
    }

    /**
     * Scope: ubicaciones históricas (retiradas)
     */
    public function scopeHistorico($query)
    {
        return $query->whereNotNull('fecha_retiro');
    }

    /**
     * Scope: por estado físico
     */
    public function scopeEstadoFisico($query, string $estado)
    {
        return $query->where('estado_fisico', $estado);
    }

    /**
     * Verificar si está activa
     */
    public function estaActiva(): bool
    {
        return $this->fecha_retiro === null;
    }

    /**
     * Marcar como retirada
     */
    public function retirar(?string $motivo = null): bool
    {
        $this->fecha_retiro = now();
        if ($motivo) {
            $this->motivo = ($this->motivo ? $this->motivo . ' | ' : '') . "RETIRO: {$motivo}";
        }
        return $this->save();
    }
}

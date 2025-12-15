<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UbicacionFisica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ubicaciones_fisicas';

    protected $fillable = [
        'nombre',
        'tipo',
        'codigo',
        'ubicacion_padre_id',
        'descripcion',
        'capacidad_max',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'capacidad_max' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con ubicación padre (jerarquía)
     */
    public function padre()
    {
        return $this->belongsTo(UbicacionFisica::class, 'ubicacion_padre_id');
    }

    /**
     * Relación con ubicaciones hijas
     */
    public function hijos()
    {
        return $this->hasMany(UbicacionFisica::class, 'ubicacion_padre_id');
    }

    /**
     * Documentos en esta ubicación
     */
    public function documentos()
    {
        return $this->hasMany(DocumentoUbicacion::class, 'ubicacion_fisica_id')
            ->whereNull('fecha_retiro');
    }

    /**
     * Historial completo de documentos (incluyendo retirados)
     */
    public function historialDocumentos()
    {
        return $this->hasMany(DocumentoUbicacion::class, 'ubicacion_fisica_id');
    }

    /**
     * Scope: solo ubicaciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: solo ubicaciones de un tipo específico
     */
    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope: ubicaciones raíz (sin padre)
     */
    public function scopeRaiz($query)
    {
        return $query->whereNull('ubicacion_padre_id');
    }

    /**
     * Obtener la ruta completa jerárquica
     */
    public function getRutaCompletaAttribute(): string
    {
        $ruta = [$this->nombre];
        $padre = $this->padre;

        while ($padre) {
            array_unshift($ruta, $padre->nombre);
            $padre = $padre->padre;
        }

        return implode(' > ', $ruta);
    }

    /**
     * Verificar si tiene capacidad disponible
     */
    public function tieneCapacidad(): bool
    {
        if ($this->capacidad_max === null) {
            return true; // Sin límite
        }

        $documentosActuales = $this->documentos()->count();
        return $documentosActuales < $this->capacidad_max;
    }

    /**
     * Obtener documentos actuales vs capacidad
     */
    public function getOcupacionAttribute(): array
    {
        $actuales = $this->documentos()->count();
        $maxima = $this->capacidad_max ?? 0;

        return [
            'actual' => $actuales,
            'maxima' => $maxima,
            'porcentaje' => $maxima > 0 ? round(($actuales / $maxima) * 100, 2) : 0,
            'disponible' => $maxima > 0 ? $maxima - $actuales : null,
        ];
    }

    /**
     * Relación recursiva con hijos (para árbol completo)
     */
    public function hijosRecursivos()
    {
        return $this->hijos()->with('hijosRecursivos');
    }

    /**
     * Verificar si esta ubicación es hija de otra (para prevenir ciclos)
     */
    public function esHijoDe(int $ubicacionId): bool
    {
        $padre = $this->padre;

        while ($padre) {
            if ($padre->id == $ubicacionId) {
                return true;
            }
            $padre = $padre->padre;
        }

        return false;
    }
}


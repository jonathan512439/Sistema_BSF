<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerAnchor extends Model
{
    /**
     * Conexión a la base de datos de auditoría
     */
    protected $connection = 'bsf_audit';

    protected $table = 'ledger_anchor';

    /**
     * Sin timestamps automáticos (solo created_at manual)
     */
    public $timestamps = false;

    protected $fillable = [
        'desde_id',
        'hasta_id',
        'hash_raiz',
        'firmado_por',
        'firma_algoritmo',
        'firma_bin',
        'publicado_en',
    ];

    protected $casts = [
        'desde_id' => 'integer',
        'hasta_id' => 'integer',
        'created_at' => 'datetime',
        'publicado_en' => 'datetime',
    ];

    /**
     * Obtener registros del ledger que pertenecen a este ancla
     */
    public function ledgerEntries()
    {
        return $this->hasMany(Ledger::class, 'id', 'id')
            ->whereBetween('id', [$this->desde_id, $this->hasta_id]);
    }

    /**
     * Scope: anclas firmadas
     */
    public function scopeFirmadas($query)
    {
        return $query->whereNotNull('firmado_por');
    }

    /**
     * Scope: anclas sin firmar
     */
    public function scopePendientesFirma($query)
    {
        return $query->whereNull('firmado_por');
    }

    /**
     * Scope: anclas publicadas en blockchain
     */
    public function scopePublicadas($query)
    {
        return $query->whereNotNull('publicado_en');
    }

    /**
     * Verificar si está firmada
     */
    public function estaFirmada(): bool
    {
        return $this->firmado_por !== null;
    }

    /**
     * Verificar si está publicada en blockchain
     */
    public function estaPublicada(): bool
    {
        return $this->publicado_en !== null;
    }

    /**
     * Obtener cantidad de registros en este bloque
     */
    public function getCantidadRegistrosAttribute(): int
    {
        return $this->hasta_id - $this->desde_id + 1;
    }

    /**
     * Obtener representación del rango
     */
    public function getRangoAttribute(): string
    {
        return "{$this->desde_id}-{$this->hasta_id}";
    }
}

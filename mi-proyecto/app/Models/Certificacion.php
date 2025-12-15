<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificacion extends Model
{
    protected $table = 'certificaciones';

    protected $fillable = [
        'documento_id',
        'usuario_id',
        'numero_certificacion',
        'texto_introduccion',
        'nombre_personal',
        'ci',
        'lugar_expedicion',
        'fecha_ingreso',
        'designacion',
        'ultimo_destino',
        'fecha_emision',
        'elaborado_por',
        'cargo_elaborador',
        'nombre_comandante',
        'cargo_comandante',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
    ];

    /**
     * Documento asociado
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * Usuario que generó la certificación
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

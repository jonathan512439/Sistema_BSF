<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalHold extends Model
{
    protected $table = 'legal_holds';
    public $timestamps = false; // La tabla tiene created_at manual (activado_en)

    protected $fillable = [
        'documento_id',
        'motivo',
        'activado_por',
        'activado_en',
        'levantado_en'
    ];

    protected $casts = [
        'activado_en' => 'datetime',
        'levantado_en' => 'datetime',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    public function activador()
    {
        return $this->belongsTo(User::class, 'activado_por');
    }
}

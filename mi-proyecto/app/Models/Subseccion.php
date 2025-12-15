<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subseccion extends Model
{
    use SoftDeletes;

    protected $table = 'subsecciones';
    protected $guarded = [];
    public $timestamps = false;

    // Relación con sección padre
    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    // Relación con documentos
    public function documentos()
    {
        return $this->hasMany(Documento::class, 'subseccion_id');
    }
}

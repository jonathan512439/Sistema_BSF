<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seccion extends Model
{
    use SoftDeletes;

    protected $table = 'secciones';
    protected $guarded = [];
    public $timestamps = false;

    // Relación con documentos
    public function documentos()
    {
        return $this->hasMany(Documento::class, 'seccion_id');
    }

    // Relación con subsecciones
    public function subsecciones()
    {
        return $this->hasMany(Subseccion::class, 'seccion_id');
    }
}

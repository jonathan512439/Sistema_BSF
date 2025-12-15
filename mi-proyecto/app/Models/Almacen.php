<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'almacenes';
    protected $guarded = [];
    public $timestamps = false;
    
    // Relación con archivos
    public function archivos()
    {
        return $this->hasMany(DocumentoArchivo::class, 'almacen_id');
    }
}

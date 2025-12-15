<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoArchivo extends Model
{
    protected $table = 'documentos_archivos';
    protected $guarded = [];
    public $timestamps = false;
    
    // Relación con el almacén
    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }
    
    // Relación con el documento
    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }
}

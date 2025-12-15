<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoDocumento extends Model
{
    use SoftDeletes;

    protected $table = 'tipos_documento';
    protected $guarded = [];
    public $timestamps = false;

    // Relación con documentos
    public function documentos()
    {
        return $this->hasMany(Documento::class, 'tipo_documento_id');
    }
}

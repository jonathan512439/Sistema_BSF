<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function catalogs()
    {
        return response()->json([
            'tipos_documento' => DB::table('tipos_documento')->select('id','nombre','confidencialidad')->orderBy('id')->get(),
            'secciones'       => DB::table('secciones')->select('id','nombre')->orderBy('id')->get(),
            'subsecciones'    => DB::table('subsecciones')->select('id','seccion_id','nombre')->orderBy('seccion_id')->orderBy('id')->get(),
            'gestiones'       => DB::table('gestiones')->select('id','anio')->orderBy('anio','desc')->get(),
            'ubicaciones'     => DB::table('ubicaciones_fisicas')->select('id','codigo','descripcion')->orderBy('id')->get(),
            'almacenes'       => DB::table('almacenes')->select('id','nombre','tipo','base_path','activo')->orderBy('id')->get(),
            //  AJUSTADO: sin 'codigo' porque tu tabla no lo tiene
            'motivos_acceso'  => DB::table('motivos_acceso')
                ->select('id','descripcion','requiere_texto','can_view','can_print','can_download')
                ->where('activo',1)
                ->orderBy('id')
                ->get(),
        ]);
    }
}


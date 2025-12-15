<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UbicacionesFisicasSeeder extends Seeder
{
    /**
     * Seed the ubicaciones_fisicas table.
     * 
     * Basado en los IDs 1-7 encontrados en documento_ubicacion
     */
    public function run(): void
    {
        $ubicaciones = [
            [
                'id' => 1,
                'nombre' => 'Archivo Central',
                'tipo' => 'archivo',
                'codigo' => 'ARC-001',
                'ubicacion_padre_id' => null,
                'descripcion' => 'Archivo central principal de almacenamiento de documentos',
                'capacidad_max' => 1000,
                'activo' => true,
            ],
            [
                'id' => 2,
                'nombre' => 'Depósito Principal',
                'tipo' => 'deposito',
                'codigo' => 'DEP-001',
                'ubicacion_padre_id' => null,
                'descripcion' => 'Depósito principal para documentos de largo plazo',
                'capacidad_max' => 2000,
                'activo' => true,
            ],
            [
                'id' => 3,
                'nombre' => 'Archivo Sección Administrativa',
                'tipo' => 'archivo',
                'codigo' => 'ARC-ADM-001',
                'ubicacion_padre_id' => 1,
                'descripcion' => 'Sección de documentos administrativos dentro del archivo central',
                'capacidad_max' => 500,
                'activo' => true,
            ],
            [
                'id' => 4,
                'nombre' => 'Archivo Sección Legal',
                'tipo' => 'archivo',
                'codigo' => 'ARC-LEG-001',
                'ubicacion_padre_id' => 1,
                'descripcion' => 'Sección de documentos legales y sumarios',
                'capacidad_max' => 300,
                'activo' => true,
            ],
            [
                'id' => 5,
                'nombre' => 'Caja de Archivo 001',
                'tipo' => 'caja',
                'codigo' => 'CAJ-001',
                'ubicacion_padre_id' => 2,
                'descripcion' => 'Caja de archivo general No. 001',
                'capacidad_max' => 100,
                'activo' => true,
            ],
            [
                'id' => 6,
                'nombre' => 'Estante A-1',
                'tipo' => 'estante',
                'codigo' => 'EST-A1',
                'ubicacion_padre_id' => 2,
                'descripcion' => 'Estante A, nivel 1 del depósito principal',
                'capacidad_max' => 50,
                'activo' => true,
            ],
            [
                'id' => 7,
                'nombre' => 'Caja de Archivo 002',
                'tipo' => 'caja',
                'codigo' => 'CAJ-002',
                'ubicacion_padre_id' => 2,
                'descripcion' => 'Caja de archivo general No. 002',
                'capacidad_max' => 100,
                'activo' => true,
            ],
        ];

        foreach ($ubicaciones as $ubicacion) {
            DB::table('ubicaciones_fisicas')->insert([
                'id' => $ubicacion['id'],
                'nombre' => $ubicacion['nombre'],
                'tipo' => $ubicacion['tipo'],
                'codigo' => $ubicacion['codigo'],
                'ubicacion_padre_id' => $ubicacion['ubicacion_padre_id'],
                'descripcion' => $ubicacion['descripcion'],
                'capacidad_max' => $ubicacion['capacidad_max'],
                'activo' => $ubicacion['activo'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Se insertaron 7 ubicaciones físicas correctamente');
    }
}

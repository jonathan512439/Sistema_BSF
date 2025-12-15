<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnchoringConfig extends Model
{
    /**
     * Conexión a la base de datos de auditoría
     */
    protected $connection = 'bsf_audit';

    protected $table = 'anchoring_config';

    /**
     * Sin timestamps automáticos
     */
    public $timestamps = false;

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
        'updated_by',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
        'updated_by' => 'integer',
    ];

    /**
     * Obtener valor de configuración
     */
    public static function get(string $key, $default = null)
    {
        $config = self::where('clave', $key)->first();

        if (!$config) {
            return $default;
        }

        return self::castValue($config->valor, $config->tipo);
    }

    /**
     * Establecer valor de configuración
     */
    public static function set(string $key, $value, ?int $userId = null): bool
    {
        $config = self::where('clave', $key)->first();

        if (!$config) {
            return false;
        }

        $config->valor = (string) $value;
        $config->updated_by = $userId;

        return $config->save();
    }

    /**
     * Obtener valor como booleano
     */
    public static function getBoolean(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'si']);
    }

    /**
     * Obtener valor como número
     */
    public static function getNumber(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Obtener valor como JSON
     */
    public static function getJson(string $key, array $default = []): array
    {
        $value = self::get($key);

        if (!$value) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Castear valor según tipo
     */
    protected static function castValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return in_array(strtolower($value), ['true', '1', 'yes', 'si']);

            case 'number':
                return is_numeric($value) ? (int) $value : 0;

            case 'json':
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];

            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Obtener toda la configuración como array
     */
    public static function getAll(): array
    {
        return self::all()->mapWithKeys(function ($config) {
            return [$config->clave => self::castValue($config->valor, $config->tipo)];
        })->toArray();
    }
}

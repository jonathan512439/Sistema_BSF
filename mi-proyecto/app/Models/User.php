<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes; // reactivado para ocultar eliminados logicos
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes; // habilita deleted_at en consultas por defecto

    // Roles (en inglés para coincidir con valores en BD)
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ARCHIVIST  = 'archivist';  // Mantener en inglés - valor en BD
    public const ROLE_READER     = 'reader';     // Mantener en inglés - valor en BD

    // Estados
    public const STATUS_INVITED  = 'invited';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DISABLED = 'disabled';

    // Solo permitimos asignacion masiva de campos no sensibles para evitar escalamiento de roles/estado
    protected $fillable = [
        'name',
        'email',
        'username',  // AGREGADO: necesario para RBAC
        'must_change_password',
        'invitation_token',
        'invitation_expires_at',
        'deactivated_at',
    ];

    // Campos delicados que se deben setear de forma explicita y validada
    protected $guarded = [
        'password',
        'role',
        'status',
        'rep_alpha',
        'rep_beta',
        'rep_score',
        'deleted_by',
        'delete_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    protected $casts = [
        'email_verified_at'     => 'datetime',
        'invitation_expires_at' => 'datetime',
        'deactivated_at'        => 'datetime',
        'locked_until'          => 'datetime',
        'must_change_password'  => 'boolean',
        'deleted_at'            => 'datetime',
    ];

    public function logins()
    {
        return $this->hasMany(UserLogin::class);
    }

    public function actions()
    {
        return $this->hasMany(UserAction::class);
    }

    // Helpers de rol
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isArchivist(): bool
    {
        return $this->role === self::ROLE_ARCHIVIST;
    }

    public function isReader(): bool
    {
        return $this->role === self::ROLE_READER;
    }

    // Helpers de estado
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInvited(): bool
    {
        return $this->status === self::STATUS_INVITED;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }
}

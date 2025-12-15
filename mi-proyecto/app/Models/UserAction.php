<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class UserAction extends Model
{
    // Archivo renombrado con mayusculas correctas para que PSR-4 lo autoloade bien en servidores case-sensitive
    protected $table = 'user_actions';

    protected $fillable = [
        'user_id',
        'target_user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function log(?int $userId, ?int $targetUserId, string $action, ?string $description = null, ?Request $request = null): void
    {
        $ip  = $request?->ip();
        $ua  = $request?->userAgent();

        static::create([
            'user_id'        => $userId,
            'target_user_id' => $targetUserId,
            'action'         => $action,
            'description'    => $description,
            'ip_address'     => $ip ? substr($ip, 0, 45) : null,
            'user_agent'     => $ua ? substr($ua, 0, 500) : null,
        ]);
    }
}

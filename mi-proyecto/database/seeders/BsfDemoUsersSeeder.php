<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class BsfDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Evitar duplicados si corres el seeder varias veces
        $users = [
            [
                'name'  => 'SuperadminBSF',
                'email' => 'superadmin@bsf.local',
                'role'  => User::ROLE_SUPERADMIN,
            ],
            [
                'name'  => 'Encargado',
                'email' => 'encargado@bsf.local',
                'role'  => User::ROLE_ARCHIVIST,
            ],
            [
                'name'  => 'Lector',
                'email' => 'lector@bsf.local',
                'role'  => User::ROLE_READER,
            ],
        ];

        foreach ($users as $data) {
            $user = User::where('email', $data['email'])->first();
            if ($user) {
                continue;
            }

            $user = new User();
            $user->name                 = $data['name'];
            $user->email                = $data['email'];
            $user->role                 = $data['role'];
            $user->status               = User::STATUS_ACTIVE;
            $user->must_change_password = false;
            $user->password             = Hash::make('123456');
            $user->password_changed_at  = now();
            $user->save();
        }
    }
}

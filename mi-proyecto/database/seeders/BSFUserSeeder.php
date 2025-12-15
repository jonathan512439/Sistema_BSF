<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserAction;

class BSFUserSeeder extends Seeder
{
    public function run(): void
    {
        // SUPERADMIN
        $superadmin = User::create([
            'name'                 => 'SuperadminBSF',
            'email'                => 'superadmin@bsf.com',
            'password'             => Hash::make('123456'),
            'role'                 => User::ROLE_SUPERADMIN,
            'status'               => User::STATUS_ACTIVE,
            'must_change_password' => false,
            'invitation_token'     => null,
            'invitation_expires_at'=> null,
            'deactivated_at'       => null,
        ]);

        UserAction::log(
            $superadmin->id,
            $superadmin->id,
            'seed_user_created',
            'Usuario SuperAdmin inicial creado mediante seeder.'
        );

        // ENCARGADO
        $archivist = User::create([
            'name'                 => 'Encargado',
            'email'                => 'encargado@bsf.com',
            'password'             => Hash::make('123456'),
            'role'                 => User::ROLE_ARCHIVIST,
            'status'               => User::STATUS_ACTIVE,
            'must_change_password' => false,
            'invitation_token'     => null,
            'invitation_expires_at'=> null,
            'deactivated_at'       => null,
        ]);

        UserAction::log(
            $superadmin->id,
            $archivist->id,
            'seed_user_created',
            'Usuario Encargado inicial creado mediante seeder.'
        );

        // LECTOR
        $reader = User::create([
            'name'                 => 'Lector',
            'email'                => 'lector@bsf.com',
            'password'             => Hash::make('123456'),
            'role'                 => User::ROLE_READER,
            'status'               => User::STATUS_ACTIVE,
            'must_change_password' => false,
            'invitation_token'     => null,
            'invitation_expires_at'=> null,
            'deactivated_at'       => null,
        ]);

        UserAction::log(
            $superadmin->id,
            $reader->id,
            'seed_user_created',
            'Usuario Lector inicial creado mediante seeder.'
        );
    }
}

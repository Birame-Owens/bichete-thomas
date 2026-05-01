<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::query()->updateOrCreate(
            ['nom' => 'admin'],
            ['description' => 'Administrateur de la plateforme']
        );

        $geranteRole = Role::query()->updateOrCreate(
            ['nom' => 'gerante'],
            ['description' => 'Gerante du salon']
        );

        User::query()->updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@bichette-thomas.test'),
        ], [
            'role_id' => $adminRole->id,
            'name' => env('ADMIN_NAME', 'Administratrice'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ]);

        User::query()->updateOrCreate([
            'email' => env('GERANTE_EMAIL', 'gerante@bichette-thomas.test'),
        ], [
            'role_id' => $geranteRole->id,
            'name' => env('GERANTE_NAME', 'Gerante'),
            'password' => Hash::make(env('GERANTE_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('ADMIN_EMAIL', 'admin@afroponte.test');
        $password = env('ADMIN_PASSWORD');

        if (!$password) {
            $this->command->error('Définissez ADMIN_PASSWORD dans le fichier .env.');
            return;
        }

        // Relançable sans danger : met à jour le compte s'il existe déjà
        $admin = User::firstOrNew(['email' => $email]);
        $admin->forceFill([
            'name'        => 'Administrateur',
            'password'    => Hash::make($password),
            'role'        => 'admin',
            'status'      => 'active',
            'language_id' => 1,
        ])->save();

        $this->command->info("Compte administrateur prêt : $email");
    }
}
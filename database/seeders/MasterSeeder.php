<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        // Verifica se já existe um usuário master no sistema
        if (User::where('is_master', true)->exists()) {
            $this->command->info('Já existe um usuário Master no sistema. Nenhum novo master foi criado.');
            return;
        }

        User::create([
            'name' => 'Master Admin',
            'email' => 'master@rastro.com',
            'password' => Hash::make('master123'),
            'is_master' => true,
            'is_admin' => false,
            'empresa_id' => null,
            'cargo_id' => null,
        ]);

        $this->command->info('Usuário Master criado com sucesso!');
    }
}

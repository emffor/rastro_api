<?php

namespace Database\Seeders\System;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsuarioMasterSeeder extends Seeder
{
    public function run(): void
    {
        // Verifica se já existe um usuário master no sistema
        if (User::where('is_master', true)->exists()) {
            $this->command->info('Já existe um usuário Master no sistema. Nenhum novo master foi criado.');
            return;
        }

        try {
            // Garantir que as permissões existam
            $this->call(PermissaoSystemSeeder::class);
            
            User::create([
                'name' => 'Master Admin',
                'email' => 'master@madeiralegal.com',
                'password' => Hash::make('master123'),
                'is_master' => true,
                'is_admin' => false,
                'empresa_id' => null,
                'cargo_id' => null,
            ]);

            $this->command->info('Usuário Master criado com sucesso!');
        } catch (\Exception $e) {
            $this->command->error('Erro ao criar usuário Master: ' . $e->getMessage());
            Log::error('Erro ao criar usuário Master: ' . $e->getMessage());
        }
    }
}

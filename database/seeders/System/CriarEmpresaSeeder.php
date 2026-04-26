<?php

namespace Database\Seeders\System;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CriarEmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $cnpj = '78.472.555/0001-23';
        
        // Dados da Empresa
        $empresaDados = [
            'nome' => 'Madeireira Teste Ltda',
            'cnpj' => $cnpj,
            'tipo_empresa' => 'SERRARIA',
            'email' => 'emfeloan@gmail.com',
            'telefone' => '(00) 0000-0000',
            'ativo' => true,
            'endereco' => 'Rua João Cordeiro, 1000',
            'cidade' => 'Fortaleza',
            'estado' => 'CE',
            'cep' => '60810-321',
        ];

        // Dados do Admin
        $adminDados = [
            'name' => 'Admin Madeireira Teste',
            'email' => 'emfeloan@gmail.com',
            'password' => '123123',
        ];

        if (Empresa::where('cnpj', $cnpj)->exists()) {
            $this->command->info("Empresa com CNPJ {$cnpj} já existe no sistema.");
            return;
        }

        if (User::where('email', $adminDados['email'])->exists()) {
            $this->command->warn("Usuário com email {$adminDados['email']} já existe. Abortando criação para evitar inconsistências.");
            return;
        }

        try {
            /** @var Empresa $empresa */
            $empresa = DB::transaction(function () use ($empresaDados, $adminDados) {
                // 1. Criar Empresa
                $empresa = Empresa::create($empresaDados);
                $this->command->info("Empresa '{$empresa->nome}' criada com sucesso.");

                // 2. Criar Usuário Admin vinculado
                $admin = User::create([
                    'name' => $adminDados['name'],
                    'email' => $adminDados['email'],
                    'password' => Hash::make($adminDados['password']),
                    'empresa_id' => $empresa->id,
                    'is_admin' => true,
                    'ativo' => true,
                ]);

                $this->command->info("Usuário Admin '{$admin->name}' ({$admin->email}) criado com sucesso.");
                
                return $empresa;
            });

            // 3. Criar Cargos e Permissões
            $this->call(CriarCargosSeeder::class, false, ['empresa' => $empresa]);

            // 4. Criar Espécies
            $this->call(CriarEspeciesEmpresaSerrariaSeeder::class, false, ['empresa' => $empresa]);

            // 5. Criar Produtos Dimensionados
            $this->call(CriarProdutosDimensionadosSeeder::class, false, ['empresa' => $empresa]);
            
            
        } catch (\Exception $e) {
            $this->command->error('Erro ao criar empresa e admin: ' . $e->getMessage());
            Log::error('Erro ao criar empresa e admin no Seeder: ' . $e->getMessage());
        }
    }
}

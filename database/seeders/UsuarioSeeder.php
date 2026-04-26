<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Empresa;
use App\Models\Cargo;
use App\Helpers\StringHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = Empresa::all();
        $nomes = [
            'Joao Silva', 'Maria Santos', 'Pedro Oliveira', 'Ana Costa', 'Carlos Souza',
            'Fernanda Lima', 'Ricardo Alves', 'Juliana Pereira', 'Bruno Rodrigues', 'Camila Martins',
        ];

        foreach ($empresas as $index => $empresa) {
            // Gera domínio sem acentos
            $dominio = StringHelper::removerAcentos($empresa->nome);
            $dominio = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $dominio)) . '.com';

            // Criar Admin da empresa
            User::firstOrCreate(
                ['email' => "admin{$index}@{$dominio}"],
                [
                    'name' => "Admin " . explode(' ', $empresa->nome)[1],
                    'password' => Hash::make('123123'),
                    'empresa_id' => $empresa->id,
                    'is_admin' => true,
                    'ativo' => true,
                ]
            );

            // Criar 5 usuários com cargos
            $cargos = Cargo::where('empresa_id', $empresa->id)->get();

            for ($i = 0; $i < 4; $i++) {
                $cargo = $cargos->random();
                $nome = $nomes[array_rand($nomes)];
                $emailBase = StringHelper::removerAcentos($nome);
                $emailBase = strtolower(str_replace(' ', '.', $emailBase)) . ($index * 5 + $i);

                User::firstOrCreate(
                    ['email' => "{$emailBase}@teste.com"],
                    [
                        'name' => $nome,
                        'password' => Hash::make('123456'),
                        'empresa_id' => $empresa->id,
                        'cargo_id' => $cargo->id,
                        'is_admin' => false,
                        'ativo' => true,
                    ]
                );
            }
        }
    }
}

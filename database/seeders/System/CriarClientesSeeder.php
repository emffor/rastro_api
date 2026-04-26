<?php

namespace Database\Seeders\System;

use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class CriarClientesSeeder extends Seeder
{
    public function run($empresa = null): void
    {
        // Se foi injetado pelo container como uma instância vazia ou nulo
        if (!($empresa instanceof Empresa && $empresa->exists)) {
            $cnpj = '78.472.555/0001-23';
            $this->command->info("Buscando empresa padrão com CNPJ: '{$cnpj}'");
            $empresa = Empresa::where('cnpj', $cnpj)->first();

            if (!$empresa) {
                $this->command->error("Empresa com CNPJ '{$cnpj}' não encontrada. Verifique se o CriarEmpresaSeeder foi executado.");
                return;
            }
        }

        $faker = Faker::create('pt_BR');
        $countPF = 0;
        $countPJ = 0;

        // Criar 10 Clientes PF
        for ($i = 0; $i < 10; $i++) {
            Cliente::create([
                'empresa_id' => $empresa->id,
                'nome' => $faker->name,
                'tipo' => 'PF',
                'documento' => $faker->cpf,
                'email' => $faker->email,
                'telefone' => $faker->cellphoneNumber,
                'endereco' => [
                    'logradouro' => $faker->streetName . ', ' . $faker->buildingNumber,
                    'cidade' => $faker->city,
                    'estado' => $faker->stateAbbr,
                    'cep' => $faker->postcode,
                    'observacao' => 'Cliente PF gerado automaticamente',
                ],
            ]);
            $countPF++;
        }

        // Criar 10 Clientes PJ
        for ($i = 0; $i < 10; $i++) {
            Cliente::create([
                'empresa_id' => $empresa->id,
                'nome' => $faker->company . ' ' . $faker->companySuffix,
                'tipo' => 'PJ',
                'documento' => $faker->cnpj,
                'email' => $faker->companyEmail,
                'telefone' => $faker->landlineNumber,
                'endereco' => [
                    'logradouro' => $faker->streetName . ', ' . $faker->buildingNumber,
                    'cidade' => $faker->city,
                    'estado' => $faker->stateAbbr,
                    'cep' => $faker->postcode,
                    'observacao' => 'Cliente PJ gerado automaticamente',
                ],
            ]);
            $countPJ++;
        }

        $this->command->info("{$countPF} Clientes PF e {$countPJ} Clientes PJ criados para a empresa '{$empresa->nome}'!");
    }
}

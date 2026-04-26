<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = Empresa::all();

        $clientes = [
            ['nome' => 'João Silva', 'tipo' => 'PF', 'documento' => '123.456.789-00', 'email' => 'joao@email.com', 'telefone' => '(92) 99999-1111'],
            ['nome' => 'Construtora Amazônia LTDA', 'tipo' => 'PJ', 'documento' => '12.345.678/0001-90', 'email' => 'contato@construtoramazonia.com', 'telefone' => '(92) 3333-1111'],
            ['nome' => 'Pedro Oliveira', 'tipo' => 'PF', 'email' => 'pedro@email.com', 'telefone' => '(92) 99999-3333'],
        ];

        foreach ($empresas as $empresa) {
            foreach ($clientes as $cliente) {
                Cliente::firstOrCreate(
                    [
                        'empresa_id' => $empresa->id,
                        'nome' => $cliente['nome'],
                    ],
                    array_merge($cliente, ['empresa_id' => $empresa->id])
                );
            }
        }
    }
}

<?php

namespace Database\Seeders\System;

use App\Models\Empresa;
use App\Models\Lote;
use App\Models\Patio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CriarPatiosLotesSeeder extends Seeder
{
    public function run($empresa = null): void
    {
        // Evita injeção automática de Empresa "vazia" quando o seeder é executado diretamente.
        if (! ($empresa instanceof Empresa) || ! $empresa->exists || ! $empresa->id) {
            $cnpj = '78.472.555/0001-23';
            $this->command->info("Buscando empresa padrão com CNPJ: '{$cnpj}'");
            $empresa = Empresa::where('cnpj', $cnpj)->first();

            if (! $empresa) {
                $this->command->error("Empresa com CNPJ '{$cnpj}' não encontrada. Verifique se o CriarEmpresaSeeder foi executado.");

                return;
            }
        }

        // Criar nome do Patio aleatório
        $nomePatio = 'Pátio ' . Str::upper(Str::random(6));

        $patio = Patio::create([
            'empresa_id' => $empresa->id,
            'nome' => $nomePatio,
            'descricao' => 'Pátio gerado automaticamente pelo Seeder',
            'ativo' => true,
            'largura' => 1000.00,
            'altura' => 1000.00,
            'largura_metros' => 100.00,
            'comprimento_metros' => 100.00,
            'altura_metros' => 5.00,
        ]);

        $this->command->info("Pátio '{$patio->nome}' criado com sucesso para a empresa '{$empresa->nome}'.");

        // Criar 5 lotes para o pátio criado
        $lotesCriados = 0;

        for ($i = 1; $i <= 5; $i++) {
            $nomeLote = "Lote {$i}";
            $codigoLote = "L" . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . "-" . Str::upper(Str::random(4));

            Lote::create([
                'patio_id' => $patio->id,
                'codigo' => $codigoLote,
                'nome' => $nomeLote,
                'descricao' => "Lote {$i} pertencente ao {$patio->nome}",
                'status' => 'DISPONIVEL',
                'capacidade_volume' => 500.0000,
                'volume_ocupado' => 0.0000,
                'largura' => 200.00,
                'altura' => 200.00,
                'pos_x' => ($i - 1) * 210.00, // Espaçamento entre os lotes visualmente
                'pos_y' => 50.00,
                'largura_metros' => 5.00,
                'comprimento_metros' => 12.00,
                'altura_metros' => 3.00,            ]);

            $lotesCriados++;
        }

        $this->command->info("{$lotesCriados} lotes criados com sucesso para o '{$patio->nome}'.");
    }
}

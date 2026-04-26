<?php

namespace Database\Seeders\System;

use App\Models\Empresa;
use App\Services\TipoSerragemService;
use Illuminate\Database\Seeder;

class CriarTiposSerragemEmpresaSeeder extends Seeder
{
    public function run(?Empresa $empresa = null): void
    {
        if (!$empresa) {
            $empresas = Empresa::query()->orderBy('nome')->get();

            foreach ($empresas as $empresaExistente) {
                $this->run($empresaExistente);
            }

            return;
        }

        $total = app(TipoSerragemService::class)->criarPadroesParaEmpresa((string) $empresa->id);

        $this->command->info("{$total} novos tipos de serragem criados para a empresa '{$empresa->nome}'.");
    }
}

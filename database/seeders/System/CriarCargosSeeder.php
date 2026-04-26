<?php

namespace Database\Seeders\System;

use App\Models\Cargo;
use App\Models\Empresa;
use App\Models\Permissao;
use Illuminate\Database\Seeder;

class CriarCargosSeeder extends Seeder
{
    public function run(?Empresa $empresa = null): void
    {
        if (!$empresa) {
            $cnpj = '07.805.674/0003-74';
            
            $this->command->info("Buscando empresa padrão com CNPJ: '{$cnpj}'");
            
            $empresa = Empresa::where('cnpj', $cnpj)->first();
    
            if (!$empresa) {
                $this->command->error("Empresa com CNPJ '{$cnpj}' não encontrada. Verifique se o CriarEmpresaSeeder foi executado ou se há divergência de formatação.");
                
                // Debug: Listar todos os CNPJs para comparar
                $cnpjs = Empresa::pluck('cnpj')->toArray();
                $this->command->warn("CNPJs encontrados no banco: " . implode(', ', $cnpjs));
                
                return;
            }
        }

        $cargosConfig = [
            'Administrador' => ['*'],
            'Gerente' => ['patio.*', 'dofs.*', 'especies.*', 'usuarios.*', 'cargos.*'],
            'Operador' => ['patio.ver', 'patio.editar', 'dofs.ver', 'dofs.criar', 'especies.ver'],
            'Estoquista' => ['patio.*', 'dofs.ver', 'especies.ver'],
        ];

        foreach ($cargosConfig as $nome => $permissoesPattern) {
            $cargo = Cargo::firstOrCreate([
                'empresa_id' => $empresa->id,
                'nome' => $nome,
            ], [
                'descricao' => "Cargo de {$nome}",
            ]);

            // Buscar permissões que correspondem ao padrão
            $permissaoIds = [];
            foreach ($permissoesPattern as $pattern) {
                if ($pattern === '*') {
                    // Todas as permissões
                    $permissaoIds = Permissao::pluck('id')->toArray();
                    break;
                } elseif (str_ends_with($pattern, '.*')) {
                    $grupo = str_replace('.*', '', $pattern);
                    $permissaoIds = array_merge(
                        $permissaoIds,
                        Permissao::where('nome', 'like', "{$grupo}.%")->pluck('id')->toArray()
                    );
                } else {
                    $permissao = Permissao::where('nome', $pattern)->first();
                    if ($permissao) {
                        $permissaoIds[] = $permissao->id;
                    }
                }
            }

            $cargo->permissoes()->sync($permissaoIds);
            
            $countPermissoes = count($permissaoIds);
            $this->command->info("Cargo '{$nome}' atualizado com {$countPermissoes} permissões.");
        }
        
        $this->command->info("Cargos para a empresa '{$empresa->nome}' criados/atualizados com sucesso!");
    }
}

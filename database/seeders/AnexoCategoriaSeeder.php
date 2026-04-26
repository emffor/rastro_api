<?php

namespace Database\Seeders;

use App\Models\AnexoCategoria;
use Illuminate\Database\Seeder;

class AnexoCategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['slug' => 'nf', 'nome' => 'Nota Fiscal', 'descricao' => 'Arquivos de nota fiscal relacionados a operações.', 'ativo' => true],
            ['slug' => 'dof', 'nome' => 'DOF', 'descricao' => 'Documentos de origem florestal.', 'ativo' => true],
            ['slug' => 'dofy_comprovante', 'nome' => 'DOF + Comprovante', 'descricao' => 'Documento de origem florestal acompanhado de comprovante.', 'ativo' => true],
            ['slug' => 'laudo_tecnico', 'nome' => 'Laudo Técnico', 'descricao' => 'Laudos técnicos diversos.', 'ativo' => true],
            ['slug' => 'inventario_florestal', 'nome' => 'Inventário Florestal', 'descricao' => 'Documentos de inventário florestal.', 'ativo' => true],
            ['slug' => 'foto_campo', 'nome' => 'Foto de Campo', 'descricao' => 'Imagens e registros fotográficos de campo.', 'ativo' => true],
            ['slug' => 'licenca_ambiental', 'nome' => 'Licença Ambiental', 'descricao' => 'Licenças ambientais e documentos correlatos.', 'ativo' => true],
            ['slug' => 'contrato', 'nome' => 'Contrato', 'descricao' => 'Contratos e aditivos.', 'ativo' => true],
            ['slug' => 'cnpj', 'nome' => 'CNPJ', 'descricao' => 'Documentos cadastrais da empresa.', 'ativo' => true],
            ['slug' => 'autex', 'nome' => 'AUTEX', 'descricao' => 'Autorização de exploração florestal.', 'ativo' => true],
            ['slug' => 'pmfs', 'nome' => 'PMFS', 'descricao' => 'Plano de manejo florestal sustentável.', 'ativo' => true],
        ];

        foreach ($categorias as $categoria) {
            AnexoCategoria::query()->updateOrCreate(
                ['slug' => $categoria['slug']],
                [
                    'nome' => $categoria['nome'],
                    'descricao' => $categoria['descricao'],
                    'ativo' => $categoria['ativo'],
                    'limite_mensal_por_empresa' => null,
                    'tamanho_max_kb' => 500,
                    'mime_types_permitidos' => ['application/pdf'],
                ],
            );
        }

        $this->command?->info('Categorias de anexo inicializadas com sucesso.');
    }
}

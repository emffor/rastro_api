<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use Illuminate\Console\Command;
use Database\Seeders\DofSeeder;

class GerarDofsEmpresa extends Command
{
    protected $signature = 'dof:gerar {empresa_id?}';
    protected $description = 'Gerar DOFs para empresa específica ou do usuário logado';

    public function handle(): int
    {
        $empresaId = $this->argument('empresa_id');
        
        if (!$empresaId) {
            $this->error('É necessário informar o ID da empresa: php artisan dof:gerar {empresa_id}');
            return 1;
        }

        $empresa = Empresa::find($empresaId);
        
        if (!$empresa) {
            $this->error("Empresa ID {$empresaId} não encontrada.");
            return 1;
        }

        $this->info("Gerando DOFs para empresa: {$empresa->nome}");
        
        $seeder = new DofSeeder();
        $seeder->run($empresaId);
        
        $this->info('DOFs gerados com sucesso!');
        return 0;
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Cargo;
use Illuminate\Database\Seeder;

class ComissaoSeeder extends Seeder
{
    public function run(): void
    {
        $comissoesVendedor = [3.00, 5.00, 7.00, 10.00];
        $comissoesAdmin = [2.00, 3.00, 5.00];
        $comissoesAvulso = [0, 2.00, 4.00];

        // Atualiza usuários com cargo "Vendedor" para serem vendedores com comissão
        $cargosVendedor = Cargo::where('nome', 'Vendedor')->pluck('id');

        $index = 0;
        User::whereIn('cargo_id', $cargosVendedor)->each(function ($user) use (&$index, $comissoesVendedor) {
            $user->update([
                'is_vendedor' => true,
                'comissao_percentual' => $comissoesVendedor[$index % count($comissoesVendedor)],
            ]);
            $index++;
        });

        // Também marca alguns admins como vendedores
        $index = 0;
        User::where('is_admin', true)
            ->whereNotNull('empresa_id')
            ->inRandomOrder()
            ->limit(5)
            ->each(function ($user) use (&$index, $comissoesAdmin) {
                $user->update([
                    'is_vendedor' => true,
                    'comissao_percentual' => $comissoesAdmin[$index % count($comissoesAdmin)],
                ]);
                $index++;
            });

        // Alguns usuários avulsos também são vendedores
        $index = 0;
        User::where('is_admin', false)
            ->whereNotNull('empresa_id')
            ->whereNull('cargo_id')
            ->orWhereHas('cargo', fn($q) => $q->where('nome', '!=', 'Vendedor'))
            ->inRandomOrder()
            ->limit(10)
            ->each(function ($user) use (&$index, $comissoesAvulso) {
                $user->update([
                    'is_vendedor' => true,
                    'comissao_percentual' => $comissoesAvulso[$index % count($comissoesAvulso)],
                ]);
                $index++;
            });
    }
}

<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\DofItem;
use App\Services\PatioEstoqueService;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MIGRANDO DOFs EXISTENTES PARA O PÁTIO ===\n\n";

$patioService = new PatioEstoqueService();

// Busca todos os DOF Items que têm categoria e saldo
$dofItems = DofItem::whereNotNull('categoria_id')
    ->where('quantidade_disponivel', '>', 0)
    ->get();

$totalMigrados = 0;
$totalVolume = 0;

foreach ($dofItems as $dofItem) {
    // Verifica se já existe no pátio
    $jaExiste = DB::table('patio_estoques')
        ->where('dof_item_id', $dofItem->id)
        ->exists();

    if (!$jaExiste) {
        // Cria entrada no pátio
        $patio = $patioService->entrada($dofItem);
        
        echo "✓ DOF Item {$dofItem->id} ({$dofItem->quantidade_disponivel}m³) -> Patio {$patio->id}\n";
        $totalMigrados++;
        $totalVolume += $dofItem->quantidade_disponivel;
    }
}

echo "\n=== RESUMO ===\n";
echo "Total migrado: {$totalMigrados} itens\n";
echo "Volume total: " . number_format($totalVolume, 3, ',', '.') . " m³\n";
echo "\nMigração concluída!\n";

<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$produto = \App\Models\Produto::where('nome', 'TORA 3M')->first();
echo "Produto ID: " . $produto->id . "\n";
echo "Categoria ID: " . ($produto->categoria_id ?? 'NULL') . "\n";
echo "Tipo: " . $produto->tipo . "\n";

// Verificar patio
$patio = \App\Models\PatioEstoque::get();
echo "\nTotal de entradas no pátio: " . $patio->count() . "\n";
foreach ($patio as $p) {
    echo "- Patio ID: {$p->id}, Categoria: {$p->categoria_id}, Volume: {$p->volume_disponivel}\n";
}

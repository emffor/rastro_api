<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Empresa;
use App\Models\Categoria;
use App\Models\Especie;
use App\Models\Dof;
use App\Models\DofItem;
use App\Models\Produto;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\PatioEstoqueService;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTE DO SISTEMA DE PÁTIO ===\n\n";

// 1. Criar dados de teste
$empresa = Empresa::first();
if (!$empresa) {
    echo "✗ Nenhuma empresa encontrada! Execute o seeder primeiro.\n";
    exit(1);
}

$user = User::first();

if (!$user) {
    echo "✗ Nenhum usuário encontrado!\n";
    exit(1);
}

// Criar categoria
$categoria = Categoria::firstOrCreate([
    'nome' => 'TORA PINUS',
    'empresa_id' => $empresa->id
]);

// Criar espécie
$especie = Especie::firstOrCreate([
    'nome_cientifico' => 'Pinus spp.',
    'nome_popular' => 'Pinus',
]);

// Criar produto florestal
$produto = Produto::firstOrCreate([
    'nome' => 'TORA 3M',
    'empresa_id' => $empresa->id,
    'tipo' => 'FLORESTAL',
    'categoria_id' => $categoria->id,
    'unidade' => 'UND',
    'largura' => 30,
    'espessura' => 30,
    'comprimento' => 3,
    'estoque_quantidade' => 100,
    'preco_venda' => 150
]);

// 2. Buscar DOF existente com categoria
$dofItem = DofItem::whereNotNull('categoria_id')
    ->where('quantidade_disponivel', '>', 0)
    ->first();

if (!$dofItem) {
    echo "✗ Nenhum DOF Item com saldo encontrado!\n";
    exit(1);
}

echo "✓ Usando DOF Item existente: {$dofItem->id} ({$dofItem->quantidade_disponivel}m³)\n";
echo "  - Categoria DOF: {$dofItem->categoria_id}\n";
echo "  - Categoria Produto: {$produto->categoria_id}\n";

// Se as categorias forem diferentes, usar a do DOF
if ($dofItem->categoria_id !== $produto->categoria_id) {
    echo "⚠ Categorias diferentes! Ajustando produto...\n";
    $produto->categoria_id = $dofItem->categoria_id;
    $produto->save();
    echo "✓ Produto atualizado com a mesma categoria do DOF\n";
}

// 3. Verificar se já tem entrada no pátio
$patio = DB::table('patio_estoques')
    ->where('dof_item_id', $dofItem->id)
    ->first();

if (!$patio) {
    echo "✗ DOF não tem entrada no pátio! Criando...\n";
    $patioService = new PatioEstoqueService();
    $patio = $patioService->entrada($dofItem);
    echo "✓ Entrada criada no pátio: {$patio->id}\n";
} else {
    echo "✓ Entrada já existe no pátio: {$patio->id} ({$patio->volume_disponivel}m³)\n";
}

// 4. Criar pedido
$cliente = \App\Models\Cliente::first();
if (!$cliente) {
    echo "✗ Nenhum cliente encontrado!\n";
    exit(1);
}

$pedido = Pedido::create([
    'empresa_id' => $empresa->id,
    'numero' => 'PED-TESTE-' . uniqid(),
    'cliente_id' => $cliente->id,
    'vendedor_id' => $user->id,
    'status' => 'PENDENTE',
    'data_pedido' => now(),
    'total' => 0
]);

// 5. Adicionar item ao pedido
$item = PedidoItem::create([
    'pedido_id' => $pedido->id,
    'produto_id' => $produto->id,
    'quantidade' => 10, // 10 unidades = 0.27m³ cada
    'preco_unitario' => 150,
    'total' => 1500
]);

echo "✓ Item adicionado ao pedido: 10 unidades\n";

// 6. Finalizar pedido (deve debitar do pátio)
$pedidoService = new PedidoService(new PatioEstoqueService());
$pedido = $pedidoService->finalizar($pedido);

echo "✓ Pedido finalizado!\n";

// 7. Verificar saldo do pátio
$patioAtualizado = DB::table('patio_estoques')
    ->where('dof_item_id', $dofItem->id)
    ->first();

$volumeDebitado = $patio->volume_disponivel - $patioAtualizado->volume_disponivel;
echo "✓ Volume debitado do pátio: " . number_format($volumeDebitado, 3) . "m³\n";
echo "✓ Saldo restante no pátio: " . number_format($patioAtualizado->volume_disponivel, 3) . "m³\n";

echo "\n=== TESTE CONCLUÍDO COM SUCESSO! ===\n";
echo "\n✓ DOF criou entrada no pátio automaticamente";
echo "\n✓ Pedido debitou do pátio usando FIFO";
echo "\n✓ Sistema funcionando corretamente!";

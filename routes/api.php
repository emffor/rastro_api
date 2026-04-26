<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminMasterController;
use App\Http\Controllers\EspecieController;
use App\Http\Controllers\DofController;
use App\Http\Controllers\DofLoteController;
use App\Http\Controllers\MovimentacaoController;
use App\Http\Controllers\AnexoController;
use App\Http\Controllers\AnexoCategoriaController;
use App\Http\Controllers\AnexoGenericoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\EmpresaConfigController;
use App\Http\Controllers\PatioController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\ProdutoDimensionadoController;
use App\Http\Controllers\TipoSerragemController;

Route::get('/', function () {
    return response()->json(['API' => 'Rastro API — Controle Operacional DOF', 'version' => '2.0.0']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/contexto', [AuthController::class, 'contexto']);
        Route::post('/controlar-empresa', [AuthController::class, 'controlarEmpresa']);
        Route::post('/encerrar-controle-empresa', [AuthController::class, 'encerrarControleEmpresa']);
        Route::post('/trocar-empresa', [AuthController::class, 'trocarEmpresa']);
    });

    Route::prefix('admin')->middleware('admin_master')->group(function () {
        Route::prefix('anexo-categorias')->group(function () {
            Route::get('', [AnexoCategoriaController::class, 'index']);
            Route::get('/ativas', [AnexoCategoriaController::class, 'ativos']);
            Route::post('', [AnexoCategoriaController::class, 'store']);
            Route::put('/{id}', [AnexoCategoriaController::class, 'update']);
            Route::delete('/{id}', [AnexoCategoriaController::class, 'destroy']);
        });
        Route::get('/dashboard', [AdminMasterController::class, 'dashboard']);
        Route::get('/empresas', [AdminMasterController::class, 'listarEmpresas']);
        Route::get('/empresas/{id}', [AdminMasterController::class, 'detalheEmpresa']);
        Route::post('/empresas/{id}/toggle', [AdminMasterController::class, 'toggleEmpresa']);
        Route::post('/empresas/{id}/forcar-logout', [AdminMasterController::class, 'forcarLogoutEmpresa']);
        Route::get('/empresas/{id}/usuarios', [AdminMasterController::class, 'usuariosEmpresa']);
        Route::get('/usuarios', [AdminMasterController::class, 'listarUsuarios']);
        Route::post('/usuarios/{id}/toggle', [AdminMasterController::class, 'toggleUsuario']);
        Route::post('/usuarios/{id}/forcar-logout', [AdminMasterController::class, 'forcarLogoutUsuario']);
        Route::get('/permissoes', [AdminMasterController::class, 'listarPermissoes']);
        Route::get('/logs', [AdminMasterController::class, 'logs']);
    });

    Route::prefix('empresas')->middleware('admin_master')->group(function () {
        Route::get('', [EmpresaController::class, 'index']);
        Route::post('', [AuthController::class, 'registrarEmpresa']);
        Route::get('/{id}', [EmpresaController::class, 'show']);
        Route::put('/{id}', [EmpresaController::class, 'update']);
        Route::delete('/{id}', [EmpresaController::class, 'destroy']);
    });
});

Route::middleware(['auth:sanctum', 'empresa.scope', 'auditoria.empresa'])->group(function () {
    // Dashboard operacional
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permissao:dofs.ver');

    // Anexos NF/DOF
    Route::prefix('anexos')->group(function () {
        Route::get('/limite', [AnexoController::class, 'obterLimite']);
        Route::post('/upload', [AnexoGenericoController::class, 'upload']);
        Route::get('/por-entidade', [AnexoGenericoController::class, 'listarPorEntidade']);
        Route::get('/{anexoId}/url', [AnexoGenericoController::class, 'obterUrl']);
        Route::delete('/{relacionavelId}', [AnexoGenericoController::class, 'deletar']);
        Route::post('/nf/{saidaOperacaoItemNotaId}', [AnexoController::class, 'uploadNf']);
        Route::post('/dof/{saidaOperacaoItemNotaId}', [AnexoController::class, 'uploadDof']);
        Route::delete('/nf/{saidaOperacaoItemNotaId}', [AnexoController::class, 'deletarNf']);
        Route::delete('/dof/{saidaOperacaoItemNotaId}', [AnexoController::class, 'deletarDof']);
    });

    // Permissões
    Route::get('/permissoes', [CargoController::class, 'listarPermissoes'])->middleware('permissao:cargos.ver');

    // Cargos
    Route::prefix('cargos')->group(function () {
        Route::get('', [CargoController::class, 'index'])->middleware('permissao:cargos.ver');
        Route::post('', [CargoController::class, 'store'])->middleware('permissao:cargos.criar');
        Route::get('/{id}', [CargoController::class, 'show'])->middleware('permissao:cargos.ver');
        Route::put('/{id}', [CargoController::class, 'update'])->middleware('permissao:cargos.editar');
        Route::delete('/{id}', [CargoController::class, 'destroy'])->middleware('permissao:cargos.excluir');
        Route::post('/{id}/permissoes', [CargoController::class, 'sincronizarPermissoes'])->middleware('permissao:cargos.editar');
    });

    // Usuários
    Route::prefix('usuarios')->group(function () {
        Route::get('', [UsuarioController::class, 'index'])->middleware('permissao:usuarios.ver');
        Route::post('', [UsuarioController::class, 'store'])->middleware('permissao:usuarios.criar');
        Route::get('/{id}', [UsuarioController::class, 'show'])->middleware('permissao:usuarios.ver');
        Route::put('/{id}', [UsuarioController::class, 'update'])->middleware('permissao:usuarios.editar');
        Route::delete('/{id}', [UsuarioController::class, 'destroy'])->middleware('permissao:usuarios.excluir');
        Route::post('/{id}/toggle-ativo', [UsuarioController::class, 'toggleAtivo'])->middleware('permissao:usuarios.ativar');
    });

    // Espécies
    Route::prefix('especies')->group(function () {
        Route::get('', [EspecieController::class, 'index'])->middleware('permissao:especies.ver');
        Route::get('/{id}', [EspecieController::class, 'show'])->middleware('permissao:especies.ver');
        Route::post('', [EspecieController::class, 'store'])->middleware('permissao:especies.criar');
        Route::put('/{id}', [EspecieController::class, 'update'])->middleware('permissao:especies.editar');
        Route::delete('/{id}', [EspecieController::class, 'destroy'])->middleware('permissao:especies.excluir');
    });

    Route::prefix('tipos-serragem')->group(function () {
        Route::get('', [TipoSerragemController::class, 'index'])->middleware('permissao:especies.ver');
        Route::post('', [TipoSerragemController::class, 'store'])->middleware('permissao:especies.criar');
    });

    // Produtos Dimensionados
    Route::prefix('produtos-dimensionados')->group(function () {
        Route::get('', [ProdutoDimensionadoController::class, 'index'])->middleware('permissao:produtos_dimensionados.ver');
        Route::get('/{id}', [ProdutoDimensionadoController::class, 'show'])->middleware('permissao:produtos_dimensionados.ver');
        Route::post('', [ProdutoDimensionadoController::class, 'store'])->middleware('permissao:produtos_dimensionados.criar');
        Route::put('/{id}', [ProdutoDimensionadoController::class, 'update'])->middleware('permissao:produtos_dimensionados.editar');
        Route::delete('/{id}', [ProdutoDimensionadoController::class, 'destroy'])->middleware('permissao:produtos_dimensionados.excluir');
    });

    // DOFs
    Route::prefix('dofs')->group(function () {
        Route::get('', [DofController::class, 'index'])->middleware('permissao:dofs.ver');
        Route::get('/ativos', [DofController::class, 'ativos'])->middleware('permissao:dofs.ver');
        Route::get('/resumo', [DofController::class, 'resumo'])->middleware('permissao:dofs.ver');
        Route::get('/relatorio/pdf', [DofController::class, 'relatorioPdf'])->middleware('permissao:dofs.ver');
        Route::get('/relatorio/excel', [DofController::class, 'relatorioExcel'])->middleware('permissao:dofs.ver');
        Route::get('/{id}', [DofController::class, 'show'])->middleware('permissao:dofs.ver');
        Route::post('', [DofController::class, 'store'])->middleware('permissao:dofs.criar');
        Route::put('/{id}', [DofController::class, 'update'])->middleware('permissao:dofs.editar');
        Route::delete('/{id}', [DofController::class, 'destroy'])->middleware('permissao:dofs.excluir');

        // Alocações DOF ↔ Lote
        Route::get('/{dofId}/alocacoes', [DofLoteController::class, 'porDof'])->middleware('permissao:dofs.ver');
    });

    // Alocações DOF ↔ Lote (operações)
    Route::prefix('dof-lotes')->group(function () {
        Route::post('/alocar', [DofLoteController::class, 'alocar'])->middleware('permissao:dofs.editar');
        Route::post('/transferir', [DofLoteController::class, 'transferir'])->middleware('permissao:dofs.editar');
        Route::post('/baixa', [DofLoteController::class, 'baixa'])->middleware('permissao:dofs.editar');
        Route::get('/{id}/alocacao-detalhe', [DofLoteController::class, 'detalheAlocacao'])->middleware('permissao:dofs.ver');
        Route::delete('/{id}', [DofLoteController::class, 'remover'])->middleware('permissao:dofs.editar');
    });

    Route::prefix('movimentacoes')->group(function () {
        Route::post('/saidas', [MovimentacaoController::class, 'registrarSaida'])->middleware('permissao:dofs.editar');
        Route::get('/saidas/especies-disponiveis', [MovimentacaoController::class, 'especiesDisponiveisSaida'])->middleware('permissao:dofs.ver');
        Route::get('/saidas/preview', [MovimentacaoController::class, 'previewSaida'])->middleware('permissao:dofs.ver');
        Route::get('/saidas/preview-produtos', [MovimentacaoController::class, 'previewProdutosEspecie'])->middleware('permissao:dofs.ver');
        Route::post('/saidas/preview-dimensionados', [MovimentacaoController::class, 'previewSaidaDimensionados'])->middleware('permissao:dofs.ver');
        Route::get('/saidas/{id}', [MovimentacaoController::class, 'detalheSaida'])->middleware('permissao:dofs.ver');
        Route::get('/relatorio/pdf', [MovimentacaoController::class, 'relatorioPdf'])->middleware('permissao:dofs.ver');
        Route::get('/relatorio/excel', [MovimentacaoController::class, 'relatorioExcel'])->middleware('permissao:dofs.ver');
        Route::get('/resumo', [MovimentacaoController::class, 'resumo'])->middleware('permissao:dofs.ver');
        Route::get('', [MovimentacaoController::class, 'index'])->middleware('permissao:dofs.ver');
        Route::get('/{id}', [MovimentacaoController::class, 'show'])->middleware('permissao:dofs.ver');
        Route::get('/dof/{dofId}', [MovimentacaoController::class, 'porDof'])->middleware('permissao:dofs.ver');
        Route::get('/lote/{loteId}', [MovimentacaoController::class, 'porLote'])->middleware('permissao:patio.ver');
    });

    // Pátios
    Route::prefix('patios')->group(function () {
        Route::get('', [PatioController::class, 'index'])->middleware('permissao:patio.ver');
        Route::get('/{id}/estoque-pecas', [PatioController::class, 'estoquePecas'])->middleware('permissao:patio.ver');
        Route::post('', [PatioController::class, 'store'])->middleware('permissao:patio.criar');
        Route::get('/{id}', [PatioController::class, 'show'])->middleware('permissao:patio.ver');
        Route::put('/{id}', [PatioController::class, 'update'])->middleware('permissao:patio.editar');
        Route::delete('/{id}', [PatioController::class, 'destroy'])->middleware('permissao:patio.excluir');
        Route::post('/{id}/mapa', [PatioController::class, 'salvarMapa'])->middleware('permissao:patio.editar');

        // Áreas bloqueadas
        Route::get('/{patioId}/areas-bloqueadas', [PatioController::class, 'listarAreasBloqueadas'])->middleware('permissao:patio.ver');
        Route::post('/{patioId}/areas-bloqueadas', [PatioController::class, 'criarAreaBloqueada'])->middleware('permissao:patio.editar');
        Route::post('/{patioId}/areas-bloqueadas/lote', [PatioController::class, 'salvarAreasBloqueadasEmLote'])->middleware('permissao:patio.editar');

        // Lotes do pátio
        Route::get('/{patioId}/lotes', [LoteController::class, 'index'])->middleware('permissao:patio.ver');
        Route::post('/{patioId}/lotes/posicoes', [LoteController::class, 'atualizarPosicoes'])->middleware('permissao:patio.editar');
    });

    // Áreas bloqueadas
    Route::prefix('areas-bloqueadas')->group(function () {
        Route::put('/{id}', [PatioController::class, 'atualizarAreaBloqueada'])->middleware('permissao:patio.editar');
        Route::delete('/{id}', [PatioController::class, 'excluirAreaBloqueada'])->middleware('permissao:patio.editar');
    });

    // Lotes
    Route::prefix('lotes')->group(function () {
        Route::get('/todos', [LoteController::class, 'todos'])->middleware('permissao:patio.ver');
        Route::post('', [LoteController::class, 'store'])->middleware('permissao:patio.criar');
        Route::get('/{id}', [LoteController::class, 'show'])->middleware('permissao:patio.ver');
        Route::put('/{id}', [LoteController::class, 'update'])->middleware('permissao:patio.editar');
        Route::delete('/{id}', [LoteController::class, 'destroy'])->middleware('permissao:patio.excluir');

        // Alocações do lote
        Route::get('/{loteId}/alocacoes', [DofLoteController::class, 'porLote'])->middleware('permissao:patio.ver');
        Route::get('/{loteId}/movimentacoes', [MovimentacaoController::class, 'porLote'])->middleware('permissao:patio.ver');
    });

    // Configurações da Empresa
    Route::prefix('empresa/config')->group(function () {
        Route::get('', [EmpresaConfigController::class, 'show']);
        Route::put('', [EmpresaConfigController::class, 'update']);
        Route::post('/logo', [EmpresaConfigController::class, 'uploadLogo']);
        Route::delete('/logo', [EmpresaConfigController::class, 'removeLogo']);
    });
});

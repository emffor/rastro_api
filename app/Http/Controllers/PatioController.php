<?php

namespace App\Http\Controllers;

use App\Services\PatioService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class PatioController extends Controller
{
    protected PatioService $service;

    public function __construct(PatioService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        try {
            $patios = $this->service->listar();

            return response()->json([
                'mensagem' => 'PATIOS_LISTADOS',
                'dados' => $patios,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_LISTAR_PATIOS',
                'erro' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $patio = $this->service->buscar($id);

            return response()->json([
                'mensagem' => 'PATIO_ENCONTRADO',
                'dados' => $patio,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'PATIO_NAO_ENCONTRADO',
                'erro' => $e->getMessage(),
            ], 404);
        }
    }

    public function estoquePecas(string $id): JsonResponse
    {
        try {
            $estoque = $this->service->buscarEstoquePecas($id);

            return response()->json([
                'mensagem' => 'ESTOQUE_PECAS_PATIO_ENCONTRADO',
                'dados' => $estoque,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_BUSCAR_ESTOQUE_PECAS_PATIO',
                'erro' => $e->getMessage(),
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'endereco' => 'nullable|string|max:500',
            'largura_metros' => 'nullable|numeric|min:5|max:500',
            'comprimento_metros' => 'nullable|numeric|min:5|max:500',
            'altura_metros' => 'nullable|numeric|min:2|max:20',
            'cor_fundo' => 'nullable|string|max:20',
            'configuracao_mapa' => 'nullable|array',
            'ativo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $patio = $this->service->criar($validator->validated());

            return response()->json([
                'mensagem' => 'PATIO_CRIADO',
                'dados' => $patio,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_CRIAR_PATIO',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'descricao' => 'nullable|string',
            'endereco' => 'nullable|string|max:500',
            'largura_metros' => 'nullable|numeric|min:5|max:500',
            'comprimento_metros' => 'nullable|numeric|min:5|max:500',
            'altura_metros' => 'nullable|numeric|min:2|max:20',
            'cor_fundo' => 'nullable|string|max:20',
            'configuracao_mapa' => 'nullable|array',
            'ativo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $patio = $this->service->atualizar($id, $validator->validated());

            return response()->json([
                'mensagem' => 'PATIO_ATUALIZADO',
                'dados' => $patio,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_ATUALIZAR_PATIO',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->service->excluir($id);

            return response()->json([
                'mensagem' => 'PATIO_EXCLUIDO',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_EXCLUIR_PATIO',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function salvarMapa(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'configuracao_mapa' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $patio = $this->service->salvarConfiguracaoMapa($id, $request->input('configuracao_mapa'));

            return response()->json([
                'mensagem' => 'MAPA_SALVO',
                'dados' => $patio,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_SALVAR_MAPA',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarAreasBloqueadas(string $patioId): JsonResponse
    {
        try {
            $areas = $this->service->listarAreasBloqueadas($patioId);

            return response()->json([
                'mensagem' => 'AREAS_BLOQUEADAS_LISTADAS',
                'dados' => $areas,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_LISTAR_AREAS_BLOQUEADAS',
                'erro' => $e->getMessage(),
            ], 500);
        }
    }

    public function criarAreaBloqueada(Request $request, string $patioId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'nullable|string|max:255',
            'pos_x' => 'nullable|numeric|min:0',
            'pos_y' => 'nullable|numeric|min:0',
            'largura' => 'nullable|numeric|min:1',
            'altura' => 'nullable|numeric|min:1',
            'cor' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $area = $this->service->criarAreaBloqueada($patioId, $validator->validated());

            return response()->json([
                'mensagem' => 'AREA_BLOQUEADA_CRIADA',
                'dados' => $area,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_CRIAR_AREA_BLOQUEADA',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function atualizarAreaBloqueada(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'nullable|string|max:255',
            'pos_x' => 'nullable|numeric|min:0',
            'pos_y' => 'nullable|numeric|min:0',
            'largura' => 'nullable|numeric|min:1',
            'altura' => 'nullable|numeric|min:1',
            'cor' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $area = $this->service->atualizarAreaBloqueada($id, $validator->validated());

            return response()->json([
                'mensagem' => 'AREA_BLOQUEADA_ATUALIZADA',
                'dados' => $area,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_ATUALIZAR_AREA_BLOQUEADA',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function excluirAreaBloqueada(string $id): JsonResponse
    {
        try {
            $this->service->excluirAreaBloqueada($id);

            return response()->json([
                'mensagem' => 'AREA_BLOQUEADA_EXCLUIDA',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_EXCLUIR_AREA_BLOQUEADA',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function salvarAreasBloqueadasEmLote(Request $request, string $patioId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'areas' => 'nullable|array',
            'areas.*.id' => 'nullable|uuid',
            'areas.*.nome' => 'nullable|string|max:255',
            'areas.*.pos_x' => 'nullable|numeric|min:0',
            'areas.*.pos_y' => 'nullable|numeric|min:0',
            'areas.*.largura' => 'nullable|numeric|min:1',
            'areas.*.altura' => 'nullable|numeric|min:1',
            'areas.*.cor' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $areas = $this->service->salvarAreasBloqueadasEmLote($patioId, $request->input('areas', []));

            return response()->json([
                'mensagem' => 'AREAS_BLOQUEADAS_SALVAS',
                'dados' => $areas,
            ]);
        } catch (Exception $e) {
            if (str_starts_with($e->getMessage(), 'COLISAO_LAYOUT_PATIO::')) {
                return response()->json([
                    'mensagem' => 'COLISAO_LAYOUT_PATIO',
                    'erro' => str_replace('COLISAO_LAYOUT_PATIO::', '', $e->getMessage()),
                ], 422);
            }
            return response()->json([
                'mensagem' => 'ERRO_AO_SALVAR_AREAS_BLOQUEADAS',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }
}

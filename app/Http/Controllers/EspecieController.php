<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Especie;
use App\Models\ProdutoDimensionado;
use App\Services\ProdutoDimensionadoService;
use App\Services\TipoSerragemService;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EspecieController extends Controller
{
    public function __construct(
        private readonly ProdutoDimensionadoService $produtoDimensionadoService,
        private readonly TipoSerragemService $tipoSerragemService,
    ) {
    }

    public function index()
    {
        return Especie::query()
            ->with('tipoSerragem')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome_cientifico' => 'required|string|max:255',
            'nome_popular' => 'required|string|max:255',
            'tipo_serragem_id' => 'nullable|uuid|exists:tipos_serragem,id',
            'tipo' => 'required_without:tipo_serragem_id|string|max:50',
            'nome_tipo' => 'nullable|string|max:255',
        ]);

        $validated = $this->prepararDadosTipoSerragem($validated);
        $especie = Especie::create($validated);
        $this->produtoDimensionadoService->ressincronizarVinculosPorEspecie($especie);

        return response()->json($especie->fresh('tipoSerragem'), 201);
    }

    public function show(string $id)
    {
        return Especie::query()
            ->with('tipoSerragem')
            ->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $especie = Especie::findOrFail($id);

        if ($especie->dofItens()->exists()) {
            return ResponseHelper::errorResponse(
                'NAO_E_POSSIVEL_EDITAR_ESPECIE_VINCULADA_A_DOF',
                null,
                422
            );
        }

        $validated = $request->validate([
            'nome_cientifico' => 'sometimes|required|string|max:255',
            'nome_popular' => 'sometimes|required|string|max:255',
            'tipo_serragem_id' => 'sometimes|nullable|uuid|exists:tipos_serragem,id',
            'tipo' => 'sometimes|required_without:tipo_serragem_id|string|max:50',
            'nome_tipo' => 'sometimes|nullable|string|max:255',
        ]);

        $nomePopularAnterior = (string) $especie->nome_popular;
        $tipoAnterior = $especie->resolverTipoSerragemNome();

        $validated = $this->prepararDadosTipoSerragem($validated, $especie);
        $especie->update($validated);
        $this->produtoDimensionadoService->ressincronizarVinculosPorEspecie(
            $especie->fresh(),
            $nomePopularAnterior,
            $tipoAnterior,
        );

        return $especie->fresh('tipoSerragem');
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $especie = Especie::query()->findOrFail($id);

            if ($this->possuiProdutoDimensionadoVinculadoAoGrupo($especie)) {
                return ResponseHelper::errorResponse(
                    'NAO_E_POSSIVEL_EXCLUIR_ESPECIE_VINCULADA_A_PRODUTO_DIMENSIONADO',
                    null,
                    422
                );
            }

            if ($especie->dofItens()->exists()) {
                return ResponseHelper::errorResponse(
                    'NAO_E_POSSIVEL_EXCLUIR_ESPECIE_VINCULADA_A_DOF',
                    null,
                    422
                );
            }

            $especie->delete();

            return ResponseHelper::successResponse('ESPECIE_EXCLUIDA_SUCESSO');
        } catch (ModelNotFoundException) {
            return ResponseHelper::errorResponse('ESPECIE_NAO_ENCONTRADA', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_EXCLUIR_ESPECIE', $e->getMessage(), 500);
        }
    }

    private function possuiProdutoDimensionadoVinculadoAoGrupo(Especie $especie): bool
    {
        $tipoCanonico = ProdutoDimensionadoEspecieMatcher::normalizarTipoEspecie(
            $especie->tipoSerragem?->nome ?? $especie->tipo,
            $especie->nome_tipo,
        );
        $nomePopularNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTexto($especie->nome_popular);

        if ($nomePopularNormalizado === '' || $tipoCanonico === 'SEM_TIPO') {
            return false;
        }

        $produtos = ProdutoDimensionado::query()
            ->with('especie:id,nome_popular')
            ->where('empresa_id', $especie->empresa_id)
            ->where('tipo_dof', $tipoCanonico)
            ->get(['id', 'especie_id', 'empresa_id', 'tipo_dof']);

        foreach ($produtos as $produto) {
            if (!$produto->especie) {
                continue;
            }

            $popularProduto = ProdutoDimensionadoEspecieMatcher::normalizarTexto($produto->especie->nome_popular);
            if ($popularProduto === $nomePopularNormalizado) {
                return true;
            }
        }

        return false;
    }

    private function prepararDadosTipoSerragem(array $dados, ?Especie $especieAtual = null): array
    {
        if (!array_key_exists('tipo_serragem_id', $dados) && !array_key_exists('tipo', $dados)) {
            return $dados;
        }

        $tipoSerragemId = (string) ($dados['tipo_serragem_id'] ?? '');
        $nomeTipo = (string) ($dados['tipo'] ?? '');

        if ($tipoSerragemId !== '') {
            $tipo = $this->tipoSerragemService->obterPorId($tipoSerragemId);
        } else {
            $tipo = $this->tipoSerragemService->obterOuCriarPorNome($nomeTipo);
        }

        $dados['tipo_serragem_id'] = $tipo->id;
        $dados['tipo'] = ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipo->nome);
        $dados['nome_tipo'] = ProdutoDimensionadoEspecieMatcher::normalizarNomeTipoDescricao(
            $dados['nome_tipo'] ?? $especieAtual?->nome_tipo,
            $dados['tipo'],
        );

        return $dados;
    }
}

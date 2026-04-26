<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\Patio;
use App\Models\PatioAreaBloqueada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class LoteService
{
    public function listarTodos(): array
    {
        return $this->queryLotesDaEmpresa()
            ->with('patio:id,nome')
            ->where('status', '!=', 'BLOQUEADO')
            ->orderBy('nome')
            ->get()
            ->map(function ($lote) {
                return [
                    'id' => $lote->id,
                    'codigo' => $lote->codigo,
                    'nome' => $lote->nome,
                    'patio_id' => $lote->patio_id,
                    'patio_nome' => $lote->patio?->nome,
                    'status' => $lote->status,
                    'capacidade_volume' => (float) $lote->capacidade_volume,
                    'volume_ocupado' => (float) $lote->volume_ocupado,
                ];
            })
            ->toArray();
    }

    public function listarPorPatio(string $patioId): array
    {
        $patio = Patio::findOrFail($patioId);

        return $patio->lotes()
            ->with(['dofLotes.dof', 'dofLotes.dofItem.especie'])
            ->orderBy('nome')
            ->get()
            ->map(function ($lote) {
                $lote->recalcularVolumeOcupado();
                $lote->setAttribute('percentual_ocupacao', $lote->getPercentualOcupacaoAttribute());

                if ($lote->pos_x === null || $lote->pos_x < 0) {
                    $lote->pos_x = 50;
                }
                if ($lote->pos_y === null || $lote->pos_y < 0) {
                    $lote->pos_y = 50;
                }
                if ($lote->largura === null || $lote->largura < 50) {
                    $lote->largura = $lote->largura_metros ? ($lote->largura_metros * 40) : 160;
                }
                if ($lote->altura === null || $lote->altura < 50) {
                    $lote->altura = $lote->comprimento_metros ? ($lote->comprimento_metros * 40) : 120;
                }

                return $lote;
            })
            ->toArray();
    }

    public function buscar(string $id): Lote
    {
        $lote = $this->queryLotesDaEmpresa()
            ->with(['patio', 'dofLotes.dof', 'dofLotes.dofItem.especie'])
            ->findOrFail($id);

        $lote->recalcularVolumeOcupado();
        $lote->setAttribute('percentual_ocupacao', $lote->getPercentualOcupacaoAttribute());

        return $lote;
    }

    public function criar(array $dados): Lote
    {
        return DB::transaction(function () use ($dados) {
            $patio = Patio::findOrFail($dados['patio_id']);
            $codigo = $this->gerarCodigoUnico($patio, $dados['codigo'] ?? $dados['nome'] ?? '');
            $rotacao = (float) ($dados['rotacao'] ?? 0);
            $larguraPx = isset($dados['largura'])
                ? (float) $dados['largura']
                : (isset($dados['largura_metros']) ? (float) $dados['largura_metros'] * 40 : 160);
            $alturaPx = isset($dados['altura'])
                ? (float) $dados['altura']
                : (isset($dados['comprimento_metros']) ? (float) $dados['comprimento_metros'] * 40 : 120);

            [$posX, $posY] = $this->resolverPosicaoInicialLote(
                $patio,
                $larguraPx,
                $alturaPx,
                $rotacao,
                isset($dados['pos_x']) ? (float) $dados['pos_x'] : null,
                isset($dados['pos_y']) ? (float) $dados['pos_y'] : null,
            );

            $lote = Lote::create([
                'patio_id' => $dados['patio_id'],
                'codigo' => $codigo,
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'] ?? null,
                'pos_x' => $posX,
                'pos_y' => $posY,
                'largura' => $larguraPx,
                'altura' => $alturaPx,
                'rotacao' => $rotacao,
                'cor' => $dados['cor'] ?? '#FFFFFF',
                'cor_borda' => $dados['cor_borda'] ?? '#333333',
                'status' => $dados['status'] ?? 'DISPONIVEL',
                'capacidade_volume' => $dados['capacidade_volume'] ?? null,
                'largura_metros' => $dados['largura_metros'] ?? null,
                'comprimento_metros' => $dados['comprimento_metros'] ?? null,
                'altura_metros' => $dados['altura_metros'] ?? null,
            ]);

            Log::info('Lote criado', [
                'lote_id' => $lote->id,
                'patio_id' => $dados['patio_id'],
                'codigo' => $codigo,
            ]);

            return $lote;
        });
    }

    public function atualizar(string $id, array $dados): Lote
    {
        return DB::transaction(function () use ($id, $dados) {
            $lote = $this->queryLotesDaEmpresa()->findOrFail($id);

            $codigoAtualizado = $lote->codigo;
            if (array_key_exists('codigo', $dados) && !empty($dados['codigo']) && $dados['codigo'] !== $lote->codigo) {
                $patio = Patio::findOrFail($lote->patio_id);
                $codigoAtualizado = $this->gerarCodigoUnico($patio, $dados['codigo'], $id);
            }

            $lote->update([
                'codigo' => $codigoAtualizado,
                'nome' => $dados['nome'] ?? $lote->nome,
                'descricao' => $dados['descricao'] ?? $lote->descricao,
                'pos_x' => $dados['pos_x'] ?? $lote->pos_x,
                'pos_y' => $dados['pos_y'] ?? $lote->pos_y,
                'largura' => $dados['largura'] ?? $lote->largura,
                'altura' => $dados['altura'] ?? $lote->altura,
                'rotacao' => $dados['rotacao'] ?? $lote->rotacao,
                'cor' => $dados['cor'] ?? $lote->cor,
                'cor_borda' => $dados['cor_borda'] ?? $lote->cor_borda,
                'capacidade_volume' => $dados['capacidade_volume'] ?? $lote->capacidade_volume,
                'largura_metros' => $dados['largura_metros'] ?? $lote->largura_metros,
                'comprimento_metros' => $dados['comprimento_metros'] ?? $lote->comprimento_metros,
                'altura_metros' => $dados['altura_metros'] ?? $lote->altura_metros,
            ]);

            if (isset($dados['status']) && !$lote->dofLotes()->exists()) {
                $lote->status = $dados['status'];
                $lote->save();
            }

            Log::info('Lote atualizado', ['lote_id' => $id]);

            return $lote->fresh();
        });
    }

    public function excluir(string $id): void
    {
        DB::transaction(function () use ($id) {
            $lote = $this->queryLotesDaEmpresa()->findOrFail($id);

            if ($lote->dofLotes()->exists()) {
                throw new Exception("Não é possível excluir lote com DOFs alocados.");
            }

            $lote->delete();

            Log::info('Lote excluído', ['lote_id' => $id]);
        });
    }

    public function atualizarPosicoes(string $patioId, array $lotes): array
    {
        return DB::transaction(function () use ($patioId, $lotes) {
            $patio = Patio::findOrFail($patioId);
            $lotesExistentes = Lote::where('patio_id', $patioId)->get();
            $areas = PatioAreaBloqueada::where('patio_id', $patioId)->get();

            $lotesPorId = [];
            foreach ($lotes as $loteData) {
                if (!empty($loteData['id'])) {
                    $lotesPorId[$loteData['id']] = $loteData;
                }
            }

            $retangulosLotes = [];
            foreach ($lotesExistentes as $loteExistente) {
                $patch = $lotesPorId[$loteExistente->id] ?? [];
                $dadosFinais = [
                    'id' => $loteExistente->id,
                    'nome' => $loteExistente->nome,
                    'pos_x' => $patch['pos_x'] ?? $loteExistente->pos_x,
                    'pos_y' => $patch['pos_y'] ?? $loteExistente->pos_y,
                    'largura' => $patch['largura'] ?? $loteExistente->largura,
                    'altura' => $patch['altura'] ?? $loteExistente->altura,
                    'rotacao' => $patch['rotacao'] ?? $loteExistente->rotacao,
                ];
                $retangulosLotes[] = $this->toLoteRectMetros($dadosFinais);
            }

            $retangulosAreas = [];
            foreach ($areas as $area) {
                $retangulosAreas[] = [
                    'id' => $area->id,
                    'nome' => $area->nome ?: 'Área sem nome',
                    'x' => (float) $area->pos_x,
                    'y' => (float) $area->pos_y,
                    'w' => (float) $area->largura,
                    'h' => (float) $area->altura,
                ];
            }

            $this->validarLayoutLotes($patio, $retangulosLotes, $retangulosAreas);

            $resultado = [];

            foreach ($lotes as $loteData) {
                if (empty($loteData['id'])) {
                    continue;
                }

                $lote = Lote::where('id', $loteData['id'])
                    ->where('patio_id', $patioId)
                    ->first();

                if (!$lote) {
                    continue;
                }

                $lote->update([
                    'pos_x' => $loteData['pos_x'] ?? $lote->pos_x,
                    'pos_y' => $loteData['pos_y'] ?? $lote->pos_y,
                    'largura' => $loteData['largura'] ?? $lote->largura,
                    'altura' => $loteData['altura'] ?? $lote->altura,
                    'rotacao' => $loteData['rotacao'] ?? $lote->rotacao,
                ]);

                $resultado[] = $lote->fresh();
            }

            Log::info('Posições dos lotes atualizadas', [
                'patio_id' => $patioId,
                'quantidade' => count($resultado),
            ]);

            return $resultado;
        });
    }

    private function validarLayoutLotes(Patio $patio, array $retangulosLotes, array $retangulosAreas): void
    {
        [$patioW, $patioH] = $this->getDimensoesPatioMetros($patio);

        foreach ($retangulosLotes as $lote) {
            if (!$this->isInsidePatio($lote, $patioW, $patioH)) {
                $this->throwColisao("Lote '{$lote['nome']}' fora dos limites do pátio.");
            }
        }

        $totalLotes = count($retangulosLotes);
        for ($i = 0; $i < $totalLotes; $i++) {
            for ($j = $i + 1; $j < $totalLotes; $j++) {
                if ($this->intersects($retangulosLotes[$i], $retangulosLotes[$j])) {
                    $this->throwColisao("Lote '{$retangulosLotes[$i]['nome']}' sobrepõe lote '{$retangulosLotes[$j]['nome']}'.");
                }
            }
        }

        foreach ($retangulosLotes as $lote) {
            foreach ($retangulosAreas as $area) {
                if ($this->intersects($lote, $area)) {
                    $this->throwColisao("Lote '{$lote['nome']}' sobrepõe área '{$area['nome']}'.");
                }
            }
        }
    }

    private function toLoteRectMetros(array $dados): array
    {
        $x = (float) (($dados['pos_x'] ?? 0) / 40);
        $y = (float) (($dados['pos_y'] ?? 0) / 40);
        $w = (float) (($dados['largura'] ?? 0) / 40);
        $h = (float) (($dados['altura'] ?? 0) / 40);
        $rotRaw = ((float) ($dados['rotacao'] ?? 0) % 360 + 360) % 360;
        $rot = ((round($rotRaw / 90) * 90) + 360) % 360;

        if ($rot === 90.0) {
            return [
                'id' => (string) ($dados['id'] ?? ''),
                'nome' => (string) ($dados['nome'] ?? 'Lote sem nome'),
                'x' => $x - $h,
                'y' => $y,
                'w' => $h,
                'h' => $w,
            ];
        }

        if ($rot === 180.0) {
            return [
                'id' => (string) ($dados['id'] ?? ''),
                'nome' => (string) ($dados['nome'] ?? 'Lote sem nome'),
                'x' => $x - $w,
                'y' => $y - $h,
                'w' => $w,
                'h' => $h,
            ];
        }

        if ($rot === 270.0) {
            return [
                'id' => (string) ($dados['id'] ?? ''),
                'nome' => (string) ($dados['nome'] ?? 'Lote sem nome'),
                'x' => $x,
                'y' => $y - $w,
                'w' => $h,
                'h' => $w,
            ];
        }

        return [
            'id' => (string) ($dados['id'] ?? ''),
            'nome' => (string) ($dados['nome'] ?? 'Lote sem nome'),
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
        ];
    }

    private function getDimensoesPatioMetros(Patio $patio): array
    {
        $w = $patio->largura_metros !== null ? (float) $patio->largura_metros : ((float) $patio->largura / 40);
        $h = $patio->comprimento_metros !== null ? (float) $patio->comprimento_metros : ((float) $patio->altura / 40);
        return [$w, $h];
    }

    private function intersects(array $a, array $b): bool
    {
        return
            $a['x'] < $b['x'] + $b['w'] &&
            $a['x'] + $a['w'] > $b['x'] &&
            $a['y'] < $b['y'] + $b['h'] &&
            $a['y'] + $a['h'] > $b['y'];
    }

    private function isInsidePatio(array $rect, float $patioW, float $patioH): bool
    {
        return
            $rect['x'] >= 0 &&
            $rect['y'] >= 0 &&
            $rect['x'] + $rect['w'] <= $patioW &&
            $rect['y'] + $rect['h'] <= $patioH;
    }

    private function throwColisao(string $detalhe): void
    {
        throw new Exception('COLISAO_LAYOUT_PATIO::' . $detalhe);
    }

    private function resolverPosicaoInicialLote(
        Patio $patio,
        float $larguraPx,
        float $alturaPx,
        float $rotacao,
        ?float $posXPreferida,
        ?float $posYPreferida,
    ): array {
        $lotesExistentes = Lote::where('patio_id', $patio->id)->get();
        $areas = PatioAreaBloqueada::where('patio_id', $patio->id)->get();

        $retangulosOcupados = [];

        foreach ($lotesExistentes as $lote) {
            $retangulosOcupados[] = $this->toLoteRectMetros([
                'id' => $lote->id,
                'nome' => $lote->nome,
                'pos_x' => $lote->pos_x,
                'pos_y' => $lote->pos_y,
                'largura' => $lote->largura,
                'altura' => $lote->altura,
                'rotacao' => $lote->rotacao,
            ]);
        }

        foreach ($areas as $area) {
            $retangulosOcupados[] = [
                'id' => $area->id,
                'nome' => $area->nome ?: 'Área sem nome',
                'x' => (float) $area->pos_x,
                'y' => (float) $area->pos_y,
                'w' => (float) $area->largura,
                'h' => (float) $area->altura,
            ];
        }

        $candidate = $this->toLoteRectMetros([
            'id' => 'novo',
            'nome' => 'novo',
            'pos_x' => $posXPreferida ?? 50.0,
            'pos_y' => $posYPreferida ?? 50.0,
            'largura' => $larguraPx,
            'altura' => $alturaPx,
            'rotacao' => $rotacao,
        ]);

        [$patioW, $patioH] = $this->getDimensoesPatioMetros($patio);
        $anchorPreferidoX = ($posXPreferida ?? 50.0) / 40;
        $anchorPreferidoY = ($posYPreferida ?? 50.0) / 40;
        if ($this->isInsidePatio($candidate, $patioW, $patioH) && !$this->hasCollisionWithMany($candidate, $retangulosOcupados)) {
            return [$anchorPreferidoX * 40, $anchorPreferidoY * 40];
        }

        $passo = 1.0;
        $maxX = max(0, $patioW - $candidate['w']);
        $maxY = max(0, $patioH - $candidate['h']);

        for ($y = 0.0; $y <= $maxY; $y += $passo) {
            for ($x = 0.0; $x <= $maxX; $x += $passo) {
                $scanRect = $this->toLoteRectMetros([
                    'id' => 'novo',
                    'nome' => 'novo',
                    'pos_x' => $x * 40,
                    'pos_y' => $y * 40,
                    'largura' => $larguraPx,
                    'altura' => $alturaPx,
                    'rotacao' => $rotacao,
                ]);
                if (!$this->hasCollisionWithMany($scanRect, $retangulosOcupados)) {
                    return [$x * 40, $y * 40];
                }
            }
        }

        $this->throwColisao('Não há espaço disponível para posicionar este lote sem sobreposição.');
    }

    private function hasCollisionWithMany(array $rect, array $others): bool
    {
        foreach ($others as $other) {
            if ($this->intersects($rect, $other)) {
                return true;
            }
        }
        return false;
    }

    private function gerarCodigoUnico(Patio $patio, string $base, ?string $ignoreId = null): string
    {
        $base = trim($base);
        $normalizado = Str::upper(Str::slug(Str::ascii($base), '-'));
        if ($normalizado === '') {
            $normalizado = 'LOTE';
        }

        $codigo = $normalizado;
        $indice = 2;

        while ($this->codigoExisteNoPatio($patio, $codigo, $ignoreId)) {
            $codigo = $normalizado . '-' . $indice;
            $indice++;
        }

        return $codigo;
    }

    private function codigoExisteNoPatio(Patio $patio, string $codigo, ?string $ignoreId = null): bool
    {
        $query = $patio->lotes()->where('codigo', $codigo);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        return $query->exists();
    }

    private function queryLotesDaEmpresa(): Builder
    {
        $empresaId = $this->resolverEmpresaId();
        $query = Lote::query();

        if ($empresaId) {
            $query->whereHas('patio', function ($patioQuery) use ($empresaId) {
                $patioQuery->where('empresa_id', $empresaId);
            });
        }

        return $query;
    }

    private function resolverEmpresaId(): ?string
    {
        return request()->get('empresa_id') ?: auth()->user()?->empresa_id;
    }
}

<?php

namespace App\Services;

use App\Models\AnexoCategoria;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class AnexoCategoriaService
{
    public function listar(): Collection
    {
        return AnexoCategoria::query()
            ->orderBy('nome')
            ->get();
    }

    public function listarAtivos(): Collection
    {
        return AnexoCategoria::query()
            ->ativos()
            ->orderBy('nome')
            ->get();
    }

    public function obterPorId(string $id): AnexoCategoria
    {
        $categoria = AnexoCategoria::query()->find($id);

        if (!$categoria) {
            throw new ModelNotFoundException('Categoria de anexo não encontrada.');
        }

        return $categoria;
    }

    public function criar(array $dados): AnexoCategoria
    {
        return AnexoCategoria::create($this->normalizarDados($dados));
    }

    public function atualizar(string $id, array $dados): AnexoCategoria
    {
        $categoria = $this->obterPorId($id);
        $categoria->fill($this->normalizarDados($dados, true));
        $categoria->save();

        return $categoria->refresh();
    }

    public function remover(string $id): void
    {
        $categoria = $this->obterPorId($id);
        $categoria->ativo = false;
        $categoria->save();
    }

    private function normalizarDados(array $dados, bool $paraAtualizacao = false): array
    {
        $payload = $dados;

        if (array_key_exists('mime_types_permitidos', $payload) && $payload['mime_types_permitidos'] === null) {
            $payload['mime_types_permitidos'] = null;
        }

        if (!$paraAtualizacao) {
            $payload['ativo'] = $payload['ativo'] ?? true;
            $payload['tamanho_max_kb'] = $payload['tamanho_max_kb'] ?? 500;
        }

        return $payload;
    }
}

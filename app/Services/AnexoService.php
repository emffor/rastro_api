<?php

namespace App\Services;

use App\Models\AnexoCategoria;
use App\Models\EmpresaUploadLimiteCategoria;
use App\Models\SaidaOperacaoItemNota;
use Illuminate\Http\UploadedFile;

class AnexoService
{
    public function __construct(
        private readonly AnexoGenericoService $anexoGenericoService,
    ) {}

    public function validarLimiteUpload(string $empresaId, string $tipoAnexo): void
    {
        $this->anexoGenericoService->validarLimite($empresaId, $this->normalizarTipo($tipoAnexo));
    }

    public function uploadAnexoNf(UploadedFile $file, string $empresaId, SaidaOperacaoItemNota $saidaOperacaoItemNota): SaidaOperacaoItemNota
    {
        return $this->uploadAnexo($file, $empresaId, $saidaOperacaoItemNota, 'nf', 'anexo_nf');
    }

    public function uploadAnexoDof(UploadedFile $file, string $empresaId, SaidaOperacaoItemNota $saidaOperacaoItemNota): SaidaOperacaoItemNota
    {
        return $this->uploadAnexo($file, $empresaId, $saidaOperacaoItemNota, 'dof', 'anexo_dof');
    }

    public function deletarAnexoNf(SaidaOperacaoItemNota $saidaOperacaoItemNota): SaidaOperacaoItemNota
    {
        return $this->deletarAnexo($saidaOperacaoItemNota, 'nf', 'anexo_nf');
    }

    public function deletarAnexoDof(SaidaOperacaoItemNota $saidaOperacaoItemNota): SaidaOperacaoItemNota
    {
        return $this->deletarAnexo($saidaOperacaoItemNota, 'dof', 'anexo_dof');
    }

    public function obterLimiteAtual(string $empresaId, string $categoriaSlug): EmpresaUploadLimiteCategoria
    {
        $this->validarCategoriaAtiva($categoriaSlug);

        return EmpresaUploadLimiteCategoria::obterOuCriar($empresaId, $categoriaSlug, $this->mesReferenciaAtual());
    }

    private function uploadAnexo(
        UploadedFile $file,
        string $empresaId,
        SaidaOperacaoItemNota $saidaOperacaoItemNota,
        string $categoriaSlug,
        string $campo,
    ): SaidaOperacaoItemNota {
        $this->anexoGenericoService->deletarPorEntidade($saidaOperacaoItemNota, $campo, $categoriaSlug);
        $anexo = $this->anexoGenericoService->upload($file, $empresaId, $categoriaSlug, $saidaOperacaoItemNota, $campo);

        $this->atualizarCamposLegados($saidaOperacaoItemNota, $campo, [
            "{$campo}_path" => $anexo->path,
            "{$campo}_url" => $anexo->url,
            "{$campo}_original_name" => $anexo->original_name,
        ]);

        return $saidaOperacaoItemNota->refresh();
    }

    private function deletarAnexo(
        SaidaOperacaoItemNota $saidaOperacaoItemNota,
        string $categoriaSlug,
        string $campo,
    ): SaidaOperacaoItemNota {
        $this->anexoGenericoService->deletarPorEntidade($saidaOperacaoItemNota, $campo, $categoriaSlug);
        $this->atualizarCamposLegados($saidaOperacaoItemNota, $campo, [
            "{$campo}_path" => null,
            "{$campo}_url" => null,
            "{$campo}_original_name" => null,
        ]);

        return $saidaOperacaoItemNota->refresh();
    }

    private function normalizarTipo(string $tipoAnexo): string
    {
        return strtolower(trim($tipoAnexo));
    }

    private function atualizarCamposLegados(SaidaOperacaoItemNota $nota, string $campo, array $dados): void
    {
        $camposPermitidos = [
            'anexo_nf' => ['anexo_nf_path', 'anexo_nf_url', 'anexo_nf_original_name'],
            'anexo_dof' => ['anexo_dof_path', 'anexo_dof_url', 'anexo_dof_original_name'],
        ];

        if (!isset($camposPermitidos[$campo])) {
            return;
        }

        $nota->forceFill(array_intersect_key($dados, array_flip($camposPermitidos[$campo])))->save();
    }

    private function validarCategoriaAtiva(string $categoriaSlug): void
    {
        $categoria = AnexoCategoria::query()
            ->ativos()
            ->where('slug', $categoriaSlug)
            ->exists();

        if (!$categoria) {
            throw new \DomainException('Categoria de anexo inválida ou inativa.');
        }
    }

    private function mesReferenciaAtual(): string
    {
        return now()->format('Y-m');
    }
}

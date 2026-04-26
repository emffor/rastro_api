<?php

namespace App\Services;
use App\Models\Anexo;
use App\Models\AnexoCategoria;
use App\Models\AnexoRelacionavel;
use App\Models\Dof;
use App\Models\DofAlocacao;
use App\Models\DofLote;
use App\Models\EmpresaUploadLimiteCategoria;
use App\Models\Lote;
use App\Models\SaidaOperacao;
use App\Models\SaidaOperacaoItem;
use App\Models\SaidaOperacaoItemNota;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AnexoGenericoService
{
    /** @var array<class-string<Model>, class-string<Model>> */
    private const ENTIDADES_PERMITIDAS = [
        Dof::class => Dof::class,
        Lote::class => Lote::class,
        SaidaOperacao::class => SaidaOperacao::class,
        SaidaOperacaoItem::class => SaidaOperacaoItem::class,
        SaidaOperacaoItemNota::class => SaidaOperacaoItemNota::class,
        DofLote::class => DofLote::class,
        DofAlocacao::class => DofAlocacao::class,
    ];

    public function __construct(
        private readonly AdminMasterContextService $adminMasterContextService,
        private readonly AuditoriaService $auditoriaService,
    ) {}

    public function upload(
        UploadedFile $file,
        string $empresaId,
        string $categoriaSlug,
        Model $entidade,
        ?string $campo = null,
        ?string $observacao = null,
        string $acao = 'upload',
    ): Anexo
    {
        $categoria = $this->obterCategoriaAtiva($categoriaSlug);
        $this->validarArquivo($file, $categoria);
        $this->validarEmpresaDaEntidade($empresaId, $entidade);

        $hashArquivo = hash_file('sha256', $file->getRealPath())
            ?: hash('sha256', (string) file_get_contents($file->getRealPath()));
        $anexoDuplicado = $this->detectarDuplicata($hashArquivo, $empresaId);
        $classeEntidade = get_class($entidade);
        $relacionavelExistente = $anexoDuplicado
            ? $this->obterRelacionamentoExistente($anexoDuplicado->getKey(), $classeEntidade, (string) $entidade->getKey(), $campo)
            : null;

        if ($relacionavelExistente) {
            return $anexoDuplicado->loadMissing(['empresa', 'uploadedBy', 'relacionaveis.anexable']);
        }

        $pathPersistido = null;

        try {
            if (!$anexoDuplicado) {
                $pathTemporario = $this->gerarPathAnexo($empresaId, $categoriaSlug, $file->getClientOriginalExtension());
                $pathPersistido = Storage::disk('s3')->putFileAs('', $file, $pathTemporario, 'private');

                if (blank($pathPersistido)) {
                    throw new DomainException('Não foi possível armazenar o arquivo no S3.');
                }
            }

            $anexo = DB::transaction(function () use (
                $file,
                $empresaId,
                $categoriaSlug,
                $categoria,
                $entidade,
                $campo,
                $observacao,
                $acao,
                $hashArquivo,
                $anexoDuplicado,
                $pathPersistido
            ): Anexo {
                $limite = $this->reservarLimiteUpload($empresaId, $categoriaSlug, $categoria->limite_mensal_por_empresa);
                $anexo = $anexoDuplicado ?? Anexo::create([
                    'empresa_id' => $empresaId,
                    'categoria' => $categoriaSlug,
                    'path' => $pathPersistido,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $this->resolverMimeType($file),
                    'size_bytes' => (int) $file->getSize(),
                    'storage_disk' => 's3',
                    'hash_arquivo' => $hashArquivo,
                    'uploaded_by' => $this->obterUsuarioIdAtual(),
                ]);

                AnexoRelacionavel::create([
                    'anexo_id' => $anexo->getKey(),
                    'anexable_type' => get_class($entidade),
                    'anexable_id' => $entidade->getKey(),
                    'campo' => $campo,
                    'ordem' => $this->proximaOrdem(get_class($entidade), (string) $entidade->getKey(), $campo),
                    'created_at' => now(),
                ]);

                $anexo->invalidarCacheUrl();
                $limite->refresh();

                $this->registrarAuditoria(
                    $acao === 'substituicao' ? 'anexo_substituido' : 'anexo_enviado',
                    $anexo,
                    [
                        'acao' => $acao,
                        'observacao' => $observacao,
                        'categoria' => $categoriaSlug,
                        'campo' => $campo,
                        'entidade_tipo' => get_class($entidade),
                        'entidade_id' => (string) $entidade->getKey(),
                        'arquivo' => $file->getClientOriginalName(),
                    ]
                );

                return $anexo->loadMissing(['empresa', 'uploadedBy', 'relacionaveis.anexable']);
            });

            return $anexo;
        } catch (Throwable $e) {
            if (filled($pathPersistido)) {
                Storage::disk('s3')->delete($pathPersistido);
            }

            throw $e;
        }
    }

    public function deletar(
        AnexoRelacionavel $relacionavel,
        ?string $observacao = null,
        string $acao = 'remocao',
    ): void
    {
        $relacionavel->loadMissing('anexo');
        $anexo = $relacionavel->anexo;

        if (!$anexo) {
            throw new ModelNotFoundException('Anexo não encontrado.');
        }

        $ultimoAnexo = false;
        $path = $anexo->path;
        $empresaId = (string) $anexo->empresa_id;
        $categoriaSlug = (string) $anexo->categoria;
        $dadosAuditoria = [
            'acao' => $acao,
            'observacao' => $observacao,
            'categoria' => $categoriaSlug,
            'campo' => $relacionavel->campo,
            'entidade_tipo' => $relacionavel->anexable_type,
            'entidade_id' => (string) $relacionavel->anexable_id,
            'arquivo' => $anexo->original_name,
        ];

        DB::transaction(function () use ($relacionavel, $anexo, $empresaId, $categoriaSlug, &$ultimoAnexo): void {
            $limite = $this->reservarRegistroLimiteParaDelecao($empresaId, $categoriaSlug);

            $relacionavel->delete();

            $anexo->refresh();
            $ultimoAnexo = !$anexo->relacionaveis()->exists();

            if ($ultimoAnexo) {
                $anexo->delete();
            }

            $limite->decrementarUploads();
            $anexo->invalidarCacheUrl();
        });

        $this->registrarAuditoria(
            $acao === 'substituicao' ? 'anexo_removido_para_substituicao' : 'anexo_removido',
            $anexo,
            $dadosAuditoria,
        );

        if ($ultimoAnexo && filled($path)) {
            Storage::disk('s3')->delete($path);
        }
    }

    public function obterUrlTemporaria(string $anexoId): string
    {
        $anexo = Anexo::query()->findOrFail($anexoId);

        $url = $anexo->gerarUrlTemporaria();

        if (blank($url)) {
            throw new DomainException('Não foi possível gerar a URL temporária do anexo.');
        }

        return $url;
    }

    public function validarLimite(string $empresaId, string $categoriaSlug): void
    {
        $categoria = $this->obterCategoriaAtiva($categoriaSlug);

        if ($categoria->limite_mensal_por_empresa === null) {
            return;
        }

        $limite = EmpresaUploadLimiteCategoria::obterOuCriar($empresaId, $categoriaSlug, $this->mesReferenciaAtual());

        if (!$limite->podeUpload((int) $categoria->limite_mensal_por_empresa)) {
            throw new DomainException(sprintf('Limite mensal de uploads da categoria %s atingido.', $categoria->nome));
        }
    }

    public function detectarDuplicata(string $hashArquivo, string $empresaId): ?Anexo
    {
        return Anexo::query()
            ->where('empresa_id', $empresaId)
            ->where('hash_arquivo', $hashArquivo)
            ->latest('created_at')
            ->first();
    }

    public function buscarEntidade(string $entidadeType, string $entidadeId, ?string $empresaId = null): Model
    {
        $classe = $this->resolverClasseEntidade($entidadeType);
        $entidade = $this->carregarEntidade($classe, $entidadeId);
        $this->validarEmpresaDaEntidade($empresaId ?? (string) request()->get('empresa_id'), $entidade);

        return $entidade;
    }

    public function listarPorEntidade(string $entidadeType, string $entidadeId): Collection
    {
        $classe = $this->resolverClasseEntidade($entidadeType);

        return AnexoRelacionavel::query()
            ->with(['anexo.empresa', 'anexo.uploadedBy', 'anexable'])
            ->where('anexable_type', $classe)
            ->where('anexable_id', $entidadeId)
            ->orderBy('ordem')
            ->orderBy('created_at')
            ->get();
    }

    public function buscarRelacionavelPorId(string $relacionavelId, ?string $empresaId = null): AnexoRelacionavel
    {
        $relacionavel = AnexoRelacionavel::query()
            ->with(['anexo.empresa', 'anexo.uploadedBy', 'anexable'])
            ->findOrFail($relacionavelId);

        $empresaId = $empresaId ?? (string) request()->get('empresa_id');
        $empresaDoAnexo = (string) ($relacionavel->anexo?->empresa_id ?? '');

        if ($empresaId !== '' && $empresaDoAnexo !== '' && $empresaId !== $empresaDoAnexo) {
            throw new DomainException('Anexo não pertence à empresa logada.');
        }

        return $relacionavel;
    }

    public function obterRelacionavelPorEntidade(Model $entidade, ?string $campo = null, ?string $categoriaSlug = null): ?AnexoRelacionavel
    {
        return AnexoRelacionavel::query()
            ->with(['anexo.empresa', 'anexo.uploadedBy', 'anexable'])
            ->where('anexable_type', get_class($entidade))
            ->where('anexable_id', $entidade->getKey())
            ->when($campo !== null, fn ($query) => $query->where('campo', $campo))
            ->when($categoriaSlug !== null, fn ($query) => $query->whereHas('anexo', fn ($anexoQuery) => $anexoQuery->where('categoria', $categoriaSlug)))
            ->latest('created_at')
            ->first();
    }

    public function deletarPorEntidade(Model $entidade, ?string $campo = null, ?string $categoriaSlug = null): void
    {
        $relacionaveis = AnexoRelacionavel::query()
            ->where('anexable_type', get_class($entidade))
            ->where('anexable_id', $entidade->getKey())
            ->when($campo !== null, fn ($query) => $query->where('campo', $campo))
            ->when($categoriaSlug !== null, fn ($query) => $query->whereHas('anexo', fn ($anexoQuery) => $anexoQuery->where('categoria', $categoriaSlug)))
            ->get();

        foreach ($relacionaveis as $relacionavel) {
            $this->deletar($relacionavel);
        }
    }

    private function resolverClasseEntidade(string $entidadeType): string
    {
        $classe = Relation::getMorphedModel($entidadeType) ?: $entidadeType;

        if (!isset(self::ENTIDADES_PERMITIDAS[$classe]) || !class_exists($classe)) {
            throw new DomainException('Tipo de entidade não suportado para anexos.');
        }

        return $classe;
    }

    private function carregarEntidade(string $classe, string $entidadeId): Model
    {
        return match ($classe) {
            Dof::class => Dof::query()->findOrFail($entidadeId),
            Lote::class => Lote::query()->findOrFail($entidadeId),
            SaidaOperacao::class => SaidaOperacao::query()->findOrFail($entidadeId),
            SaidaOperacaoItem::class => SaidaOperacaoItem::query()->with('saidaOperacao')->findOrFail($entidadeId),
            SaidaOperacaoItemNota::class => SaidaOperacaoItemNota::query()->with('saidaOperacaoItem.saidaOperacao')->findOrFail($entidadeId),
            DofLote::class => DofLote::query()->findOrFail($entidadeId),
            DofAlocacao::class => DofAlocacao::query()->findOrFail($entidadeId),
            default => throw new DomainException('Tipo de entidade não suportado para anexos.'),
        };
    }

    private function validarArquivo(UploadedFile $file, AnexoCategoria $categoria): void
    {
        $mime = strtolower((string) $file->getMimeType());
        $mimeTypesPermitidos = $categoria->mime_types_permitidos ?: ['application/pdf'];
        $tamanhoKb = (int) ceil(($file->getSize() ?: 0) / 1024);
        $limiteTamanho = (int) $categoria->tamanho_max_kb;

        if (!in_array($mime, $mimeTypesPermitidos, true)) {
            throw new DomainException('O arquivo deve ser um PDF válido.');
        }

        if ($tamanhoKb > $limiteTamanho) {
            throw new DomainException(sprintf('O arquivo deve ter no máximo %d KB.', $limiteTamanho));
        }
    }

    private function validarEmpresaDaEntidade(string $empresaId, Model $entidade): void
    {
        $empresaDaEntidade = $this->obterEmpresaDaEntidade($entidade);

        if ($empresaDaEntidade !== null && $empresaDaEntidade !== $empresaId) {
            throw new DomainException('Anexo não pertence à empresa logada.');
        }
    }

    private function obterEmpresaDaEntidade(Model $entidade): ?string
    {
        if (filled($entidade->getAttribute('empresa_id'))) {
            return (string) $entidade->getAttribute('empresa_id');
        }

        if ($entidade instanceof SaidaOperacaoItem) {
            return (string) ($entidade->saidaOperacao?->empresa_id ?? '');
        }

        if ($entidade instanceof SaidaOperacaoItemNota) {
            return (string) ($entidade->saidaOperacaoItem?->saidaOperacao?->empresa_id ?? '');
        }

        return null;
    }

    private function obterCategoriaAtiva(string $categoriaSlug): AnexoCategoria
    {
        $categoria = AnexoCategoria::query()
            ->ativos()
            ->where('slug', $categoriaSlug)
            ->first();

        if (!$categoria) {
            throw new DomainException('Categoria de anexo inválida ou inativa.');
        }

        return $categoria;
    }

    private function reservarLimiteUpload(string $empresaId, string $categoriaSlug, ?int $limiteMensal): EmpresaUploadLimiteCategoria
    {
        return DB::transaction(function () use ($empresaId, $categoriaSlug, $limiteMensal) {
            $limite = EmpresaUploadLimiteCategoria::query()
                ->where('empresa_id', $empresaId)
                ->where('categoria_slug', $categoriaSlug)
                ->where('mes_referencia', $this->mesReferenciaAtual())
                ->lockForUpdate()
                ->first();

            if (!$limite) {
                $limite = EmpresaUploadLimiteCategoria::obterOuCriar(
                    $empresaId,
                    $categoriaSlug,
                    $this->mesReferenciaAtual(),
                );
                $limite->refresh();
            }

            if (!$limite->podeUpload($limiteMensal)) {
                throw new DomainException('Limite mensal da categoria atingido.');
            }

            $limite->incrementarUploads();

            return $limite->fresh();
        });
    }

    private function reservarRegistroLimiteParaDelecao(string $empresaId, string $categoriaSlug): EmpresaUploadLimiteCategoria
    {
        return DB::transaction(function () use ($empresaId, $categoriaSlug) {
            $limite = EmpresaUploadLimiteCategoria::query()
                ->where('empresa_id', $empresaId)
                ->where('categoria_slug', $categoriaSlug)
                ->where('mes_referencia', $this->mesReferenciaAtual())
                ->lockForUpdate()
                ->first();

            if (!$limite) {
                $limite = EmpresaUploadLimiteCategoria::obterOuCriar(
                    $empresaId,
                    $categoriaSlug,
                    $this->mesReferenciaAtual(),
                );
                $limite->refresh();
            }

            return $limite;
        });
    }

    private function obterRelacionamentoExistente(string $anexoId, string $anexableType, string $anexableId, ?string $campo): ?AnexoRelacionavel
    {
        return AnexoRelacionavel::query()
            ->where('anexo_id', $anexoId)
            ->where('anexable_type', $anexableType)
            ->where('anexable_id', $anexableId)
            ->when($campo !== null, fn ($query) => $query->where('campo', $campo))
            ->first();
    }

    private function proximaOrdem(string $anexableType, string $anexableId, ?string $campo): int
    {
        $ultimaOrdem = AnexoRelacionavel::query()
            ->where('anexable_type', $anexableType)
            ->where('anexable_id', $anexableId)
            ->when($campo !== null, fn ($query) => $query->where('campo', $campo))
            ->max('ordem');

        return $ultimaOrdem === null ? 0 : ((int) $ultimaOrdem + 1);
    }

    private function gerarPathAnexo(string $empresaId, string $categoriaSlug, string $extensao): string
    {
        $timestamp = Carbon::now()->format('YmdHis');
        $uuid = (string) Str::uuid();
        $extensaoNormalizada = strtolower($extensao ?: 'pdf');

        return sprintf(
            'anexos/%s/%s/%s-%s.%s',
            $empresaId,
            $categoriaSlug,
            $uuid,
            $timestamp,
            $extensaoNormalizada,
        );
    }

    private function resolverMimeType(UploadedFile $file): string
    {
        return strtolower((string) ($file->getMimeType() ?: 'application/pdf'));
    }

    private function obterUsuarioIdAtual(): ?string
    {
        return $this->adminMasterContextService->usuarioEfetivoId();
    }

    private function registrarAuditoria(string $evento, Model $anexo, array $propriedades = []): void
    {
        $this->auditoriaService->registrar('anexos', $evento, $anexo, $propriedades);
    }

    private function mesReferenciaAtual(): string
    {
        return now()->format('Y-m');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnexoCategoria extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'anexo_categorias';

    protected $fillable = [
        'slug',
        'nome',
        'descricao',
        'ativo',
        'limite_mensal_por_empresa',
        'tamanho_max_kb',
        'mime_types_permitidos',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'limite_mensal_por_empresa' => 'integer',
        'tamanho_max_kb' => 'integer',
        'mime_types_permitidos' => 'array',
    ];

    public function anexos(): HasMany
    {
        return $this->hasMany(Anexo::class, 'categoria', 'slug');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public static function obterPorSlug(string $slug): ?self
    {
        return self::query()->where('slug', $slug)->first();
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Anexo extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToEmpresa;

    private const CACHE_KEY_PREFIX = 'anexo:url:';

    protected $table = 'anexos';

    protected $fillable = [
        'empresa_id',
        'categoria',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_disk',
        'hash_arquivo',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function relacionaveis(): HasMany
    {
        return $this->hasMany(AnexoRelacionavel::class);
    }

    public function anexable(): Collection
    {
        return $this->relacionaveis()
            ->with('anexable')
            ->orderBy('ordem')
            ->orderBy('created_at')
            ->get()
            ->pluck('anexable')
            ->filter();
    }

    public function scopePorCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }

    public function scopePorEmpresa(Builder $query, string $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function isPdf(): bool
    {
        return strtolower((string) $this->mime_type) === 'application/pdf';
    }

    public function getUrlAttribute(): ?string
    {
        return $this->gerarUrlTemporaria();
    }

    public function gerarUrlTemporaria(): ?string
    {
        if (blank($this->path)) {
            return null;
        }

        $cacheKey = $this->cacheKeyUrl();
        $urlCacheada = $this->cacheGet($cacheKey);

        if (filled($urlCacheada)) {
            return $urlCacheada;
        }

        $url = $this->resolverUrlTemporaria();

        if (filled($url)) {
            $this->cachePut($cacheKey, $url);
        }

        return $url;
    }

    public function invalidarCacheUrl(): void
    {
        $this->cacheForget($this->cacheKeyUrl());
    }

    private function resolverUrlTemporaria(): ?string
    {
        try {
            return Storage::disk($this->storage_disk ?: 's3')->temporaryUrl($this->path, now()->addHours(2));
        } catch (Throwable) {
            $urlBase = config('filesystems.disks.s3.url') ?: env('MINIO_ENDPOINT');

            if (filled($urlBase)) {
                return rtrim($urlBase, '/') . '/' . ltrim($this->path, '/');
            }
        }

        return null;
    }

    private function cacheGet(string $key): mixed
    {
        try {
            return Cache::store('redis')->get($key);
        } catch (Throwable) {
            return Cache::store(config('cache.default'))->get($key);
        }
    }

    private function cachePut(string $key, mixed $value): void
    {
        try {
            Cache::store('redis')->put($key, $value, now()->addMinutes(110));
            return;
        } catch (Throwable) {
            Cache::store(config('cache.default'))->put($key, $value, now()->addMinutes(110));
        }
    }

    private function cacheForget(string $key): void
    {
        try {
            Cache::store('redis')->forget($key);
            return;
        } catch (Throwable) {
            Cache::store(config('cache.default'))->forget($key);
        }
    }

    private function cacheKeyUrl(): string
    {
        return self::CACHE_KEY_PREFIX . $this->getKey();
    }
}

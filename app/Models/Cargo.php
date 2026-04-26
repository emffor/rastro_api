<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToEmpresa;
use App\Traits\LogsAdminMasterContext;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Cargo extends Model
{
    use HasFactory, SoftDeletes, HasUuids, BelongsToEmpresa, LogsActivity, LogsAdminMasterContext;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'descricao'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'empresa_id',
        'nome',
        'descricao',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function permissoes()
    {
        return $this->belongsToMany(Permissao::class, 'cargo_permissao');
    }

    /**
     * Verifica se o cargo tem uma permissão específica.
     */
    public function temPermissao(string $permissaoNome): bool
    {
        return $this->permissoes()->where('nome', $permissaoNome)->exists();
    }
}

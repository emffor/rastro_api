<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Traits\LogsAdminMasterContext;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids, SoftDeletes, HasApiTokens, LogsActivity, LogsAdminMasterContext;

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (!$user->is_master) {
                return;
            }

            if (self::jaExisteOutroMasterAtivo($user->exists ? (string) $user->getKey() : null)) {
                throw ValidationException::withMessages([
                    'is_master' => ['Já existe um administrador master ativo no sistema.'],
                ]);
            }
        });

        static::restoring(function (self $user): void {
            if (!$user->is_master) {
                return;
            }

            if (self::jaExisteOutroMasterAtivo((string) $user->getKey())) {
                throw ValidationException::withMessages([
                    'is_master' => ['Já existe um administrador master ativo no sistema.'],
                ]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'cargo_id', 'is_admin', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_id',
        'cargo_id',
        'is_master',
        'is_admin',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_master' => 'boolean',
            'is_admin' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }

    /**
     * Verifica se é o super admin (MASTER).
     */
    public function isMaster(): bool
    {
        return $this->is_master === true;
    }

    /**
     * Verifica se é admin da empresa.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Verifica se tem uma permissão específica.
     */
    public function temPermissao(string $permissao): bool
    {
        // Master tem todas as permissões
        if ($this->isMaster()) {
            return true;
        }

        // Admin da empresa tem todas as permissões
        if ($this->isAdmin()) {
            return true;
        }

        // Usuário comum verifica pelo cargo
        if ($this->cargo) {
            return $this->cargo->temPermissao($permissao);
        }

        return false;
    }

    /**
     * Retorna todas as permissões do usuário.
     */
    public function permissoes()
    {
        if ($this->isMaster() || $this->isAdmin()) {
            return Permissao::all();
        }

        return $this->cargo?->permissoes ?? collect();
    }

    private static function jaExisteOutroMasterAtivo(?string $ignorarUserId = null): bool
    {
        $query = self::query()
            ->where('is_master', true)
            ->whereNull('deleted_at');

        if (!empty($ignorarUserId)) {
            $query->whereKeyNot($ignorarUserId);
        }

        return $query->exists();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Empresa extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    public const TIPO_SERRARIA = 'SERRARIA';
    public const TIPO_AMBIENTAL = 'AMBIENTAL';
    public const TIPO_MISTA = 'MISTO';
    public const TIPOS = [
        self::TIPO_SERRARIA,
        self::TIPO_AMBIENTAL,
        self::TIPO_MISTA,
    ];

    protected $fillable = [
        'nome',
        'cnpj',
        'tipo_empresa',
        'email',
        'telefone',
        'ativo',
        'logo_url',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'inscricao_estadual',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function cargos()
    {
        return $this->hasMany(Cargo::class);
    }

    public function admin()
    {
        return $this->hasOne(User::class)->where('is_admin', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Permissao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'permissoes';

    protected $fillable = [
        'nome',
        'descricao',
        'grupo',
    ];

    public function cargos()
    {
        return $this->belongsToMany(Cargo::class, 'cargo_permissao');
    }
}

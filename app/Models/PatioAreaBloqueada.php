<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatioAreaBloqueada extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'patio_areas_bloqueadas';

    protected $fillable = [
        'patio_id',
        'nome',
        'pos_x',
        'pos_y',
        'largura',
        'altura',
        'cor',
    ];

    protected $casts = [
        'pos_x' => 'decimal:2',
        'pos_y' => 'decimal:2',
        'largura' => 'decimal:2',
        'altura' => 'decimal:2',
    ];

    public function patio(): BelongsTo
    {
        return $this->belongsTo(Patio::class);
    }
}

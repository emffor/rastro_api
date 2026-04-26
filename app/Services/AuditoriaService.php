<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditoriaService
{
    public function __construct(
        private readonly AdminMasterContextService $adminMasterContextService,
    ) {}

    public function registrar(
        string $logName,
        string $evento,
        ?Model $entidade = null,
        array $propriedades = [],
        ?Request $request = null,
        ?Model $causador = null,
    ): void
    {
        if (!function_exists('activity')) {
            return;
        }

        $request ??= request();
        $propriedades = array_merge(
            $propriedades,
            $this->adminMasterContextService->propriedadesAuditoria($request),
        );

        $activity = activity($logName)
            ->causedBy($causador ?? $request->user())
            ->withProperties($propriedades)
            ->event($evento);

        if ($entidade) {
            $activity->performedOn($entidade);
        }

        $activity->log($evento);
    }
}

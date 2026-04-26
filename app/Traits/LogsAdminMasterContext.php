<?php

namespace App\Traits;

use App\Services\AdminMasterContextService;
use Spatie\Activitylog\Contracts\Activity;

trait LogsAdminMasterContext
{
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $propriedades = app(AdminMasterContextService::class)->propriedadesAuditoria();

        if (empty($propriedades)) {
            return;
        }

        $activity->properties = $activity->properties->merge($propriedades);
    }
}

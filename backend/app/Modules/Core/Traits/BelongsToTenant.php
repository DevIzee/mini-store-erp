<?php

namespace App\Modules\Core\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Applique le filtre organisation_id sur toutes les requêtes Eloquent
        static::addGlobalScope('tenant', function (Builder $query) {
            if (app()->bound('tenant_id') && app('tenant_id') !== null) {
                $query->where(
                    (new static)->qualifyColumn('organisation_id'),
                    app('tenant_id')
                );
            }
        });

        // Auto-remplit organisation_id à la création
        static::creating(function ($model) {
            if (app()->bound('tenant_id') && empty($model->organisation_id)) {
                $model->organisation_id = app('tenant_id');
            }
        });
    }
}

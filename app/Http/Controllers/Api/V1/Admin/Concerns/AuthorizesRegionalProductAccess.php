<?php

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait AuthorizesRegionalProductAccess
{
    protected function applyRegionalProductScope(Builder $query, ?User $user): Builder
    {
        $regionId = $this->regionalAdminRegionId($user);

        if ($regionId === null) {
            return $query;
        }

        return $query->whereHas('regions', fn ($regionQuery) => $regionQuery->where('regions.id', $regionId));
    }

    protected function ensureProductIsVisibleForRegionalAdmin(?User $user, Product $product): void
    {
        $regionId = $this->regionalAdminRegionId($user);

        if ($regionId === null) {
            return;
        }

        abort_unless(
            $product->regions()->where('regions.id', $regionId)->exists(),
            404,
            'Producto no encontrado para tu centro regional.'
        );
    }

    protected function regionalAdminRegionId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('role');

        if (! $user->hasRole('centro_regional_admin')) {
            return null;
        }

        abort_if(
            blank($user->region_id),
            403,
            'Tu usuario no tiene un centro regional asignado.'
        );

        return (int) $user->region_id;
    }
}

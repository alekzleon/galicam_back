<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'region_id' => $this->region_id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toDateTimeString(),
            'is_internal' => $this->isInternalUser(),
            'role' => $this->whenLoaded('role', function () {
                return $this->role ? [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'display_name' => $this->role->display_name,
                    'description' => $this->role->description,
                    'is_active' => (bool) $this->role->is_active,
                ] : null;
            }),
            'region' => $this->whenLoaded('region', function () {
                return $this->region ? [
                    'id' => $this->region->id,
                    'name' => $this->region->name,
                    'slug' => $this->region->slug,
                    'is_active' => (bool) $this->region->is_active,
                ] : null;
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

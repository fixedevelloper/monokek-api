<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,
            'is_active' => (bool) $this->is_active,

            // ✔ rôle principal
            'role' => $this->getRoleNames()->first() ?? null,

            // ✔ permissions (SAFE + JSON clean)
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->getAllPermissions()->pluck('name')->values();
            }),

            'roles' => $this->whenLoaded('roles', function () {
                return $this->getRoleNames();
            }),

            'created_at' => optional($this->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($this->updated_at)->format('d/m/Y H:i'),
        ];
    }
}
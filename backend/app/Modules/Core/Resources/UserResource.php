<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'organisation_id' => $this->organisation_id,
            'language'        => $this->language,
            'timezone'        => $this->timezone,
            'is_active'       => $this->is_active,
            'last_login_at'   => $this->last_login_at?->toIso8601String(),

            'role' => $this->whenLoaded('role', fn () => [
                'id'              => $this->role->id,
                'name'            => $this->role->name,
                'hierarchy_level' => $this->role->hierarchy_level,
            ]),

            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id'   => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ] : null),
        ];
    }
}

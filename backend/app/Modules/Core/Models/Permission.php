<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    // Pas de BelongsToTenant — system-wide, seedée, immuable par les tenants.
    public $timestamps = false;

    protected $fillable = [
        'name', 'display_name', 'permission_group',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}

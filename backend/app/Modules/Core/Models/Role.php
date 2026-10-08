<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organisation_id', 'name', 'is_system', 'hierarchy_level',
    ];

    protected $casts = [
        'is_system'       => 'boolean',
        'hierarchy_level' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ─── Helpers hiérarchiques ────────────────────────────────────────────────

    /** Ce rôle est strictement supérieur à $other. */
    public function outranks(Role $other): bool
    {
        return $this->hierarchy_level > $other->hierarchy_level;
    }

    /** Ce rôle est supérieur ou égal à $other. */
    public function outranksOrEquals(Role $other): bool
    {
        return $this->hierarchy_level >= $other->hierarchy_level;
    }
}

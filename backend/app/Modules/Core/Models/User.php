<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use BelongsToTenant, SoftDeletes, Notifiable;

    protected $fillable = [
        'organisation_id', 'role_id', 'warehouse_id',
        'name', 'email', 'phone', 'password',
        'timezone', 'language', 'is_active', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ─── Permissions ──────────────────────────────────────────────────────────

    /**
     * Vérifie si l'utilisateur possède une permission.
     * Lecture depuis la session — O(1), zéro requête DB.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, session('permissions', []), true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Operator (1) et Lead_Operator (2) sont scopés à un seul warehouse.
     */
    public function isWarehouseScoped(): bool
    {
        return $this->role->hierarchy_level <= 2;
    }

    /**
     * Cet utilisateur peut gérer $target (son rôle est strictement supérieur).
     */
    public function canManage(User $target): bool
    {
        return $this->role->outranks($target->role);
    }

    /**
     * Cet utilisateur peut supprimer une ressource appartenant à $owner
     * (règle hiérarchique proforma + utilisateurs).
     */
    public function canDeleteOwnedBy(User $owner): bool
    {
        // Le créateur peut toujours supprimer son propre élément
        if ($this->id === $owner->id) {
            return true;
        }

        // Sinon, il faut être strictement supérieur dans la hiérarchie
        return $this->role->outranks($owner->role);
    }
}

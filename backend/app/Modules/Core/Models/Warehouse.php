<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'name', 'address', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

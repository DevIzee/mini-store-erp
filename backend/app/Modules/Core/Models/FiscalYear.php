<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organisation_id', 'name', 'starts_at', 'ends_at', 'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at'   => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function close(): bool
    {
        return $this->update(['status' => 'closed']);
    }
}

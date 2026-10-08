<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organisation_id', 'plan', 'status', 'expires_at', 'limits',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'limits'     => 'array',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}

<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    // ⚠️  Ce modèle ne doit JAMAIS être utilisé directement dans un controller.
    //     Accès exclusif via DocumentNumberingService::next() avec lockForUpdate().
    //     Pas de BelongsToTenant — la séquence est une ressource système critique.

    public $timestamps    = false;
    public $incrementing  = false;
    protected $primaryKey = null;  // Clé primaire composite gérée en SQL brut

    protected $fillable = [
        'organisation_id', 'document_type', 'fiscal_year_id', 'last_value',
    ];

    protected $casts = [
        'last_value' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}

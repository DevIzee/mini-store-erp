<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organisation extends Model
{
    // Pas de BelongsToTenant — c'est le tenant lui-même.
    use SoftDeletes;

    protected $fillable = [
        'name', 'legal_status', 'ifu', 'rccm',
        'address', 'po_box', 'phone', 'email', 'logo',
        'currency_code', 'timezone', 'language',
        'fiscal_year_start', 'fiscal_year_mode',
        'numbering_strategy',
        'doc_prefix_sale', 'doc_prefix_proforma',
        'doc_prefix_delivery', 'doc_prefix_purchase',
        'sequence_padding',
        'default_tax_rate',
        'low_stock_threshold_mode', 'low_stock_threshold',
        'barcode_scanner_enabled', 'thermal_print_logo', 'import_export_enabled',
        'document_header', 'document_footer', 'template_name',
    ];

    protected $casts = [
        'fiscal_year_start'        => 'date',
        'barcode_scanner_enabled'  => 'boolean',
        'thermal_print_logo'       => 'boolean',
        'import_export_enabled'    => 'boolean',
        'document_header'          => 'array',
        'document_footer'          => 'array',
        'default_tax_rate'         => 'decimal:2',
        'low_stock_threshold'      => 'decimal:4',
        'sequence_padding'         => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany('created_at');
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    public function currentFiscalYear(): HasOne
    {
        return $this->hasOne(FiscalYear::class)
            ->where('status', 'open')
            ->latestOfMany('starts_at');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

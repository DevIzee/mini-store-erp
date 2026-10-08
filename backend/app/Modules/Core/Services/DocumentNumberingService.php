<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\FiscalYear;
use App\Modules\Core\Models\Organisation;
use Illuminate\Support\Facades\DB;

class DocumentNumberingService
{
    /**
     * Génère le prochain numéro de document de façon atomique.
     *
     * Utilise lockForUpdate() sur la ligne exacte (clé primaire composite)
     * pour éviter tout doublon en environnement concurrent.
     *
     * @param  string $type  'sale' | 'proforma' | 'delivery_note' | 'purchase_order'
     * @return string        Ex: "FAC-2025-000042"
     */
    public function next(int $organisationId, string $type, int $fiscalYearId): string
    {
        // Charger les données nécessaires AVANT la transaction
        // pour ne pas bloquer inutilement pendant les requêtes Eloquent.
        $organisation = Organisation::findOrFail($organisationId);
        $fiscalYear   = FiscalYear::findOrFail($fiscalYearId);

        $nextValue = DB::transaction(function () use ($organisationId, $type, $fiscalYearId) {
            $sequence = DB::table('document_sequences')
                ->where('organisation_id', $organisationId)
                ->where('document_type', $type)
                ->where('fiscal_year_id', $fiscalYearId)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                // Première utilisation pour ce type + exercice fiscal
                DB::table('document_sequences')->insert([
                    'organisation_id' => $organisationId,
                    'document_type'   => $type,
                    'fiscal_year_id'  => $fiscalYearId,
                    'last_value'      => 1,
                ]);

                return 1;
            }

            $nextValue = $sequence->last_value + 1;

            DB::table('document_sequences')
                ->where('organisation_id', $organisationId)
                ->where('document_type', $type)
                ->where('fiscal_year_id', $fiscalYearId)
                ->update(['last_value' => $nextValue]);

            return $nextValue;
        });

        return $this->format($organisation, $fiscalYear, $type, $nextValue);
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function format(
        Organisation $organisation,
        FiscalYear $fiscalYear,
        string $type,
        int $value
    ): string {
        $prefix = $this->resolvePrefix($organisation, $type);
        $year   = $fiscalYear->starts_at->year;
        $padded = str_pad($value, $organisation->sequence_padding, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$padded}";
    }

    private function resolvePrefix(Organisation $organisation, string $type): string
    {
        return match ($type) {
            'sale'           => $organisation->doc_prefix_sale,
            'proforma'       => $organisation->doc_prefix_proforma,
            'delivery_note'  => $organisation->doc_prefix_delivery,
            'purchase_order' => $organisation->doc_prefix_purchase,
            default          => strtoupper($type),
        };
    }
}

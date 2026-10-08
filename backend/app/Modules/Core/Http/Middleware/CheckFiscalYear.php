<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Models\FiscalYear;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFiscalYear
{
    /**
     * Vérifie qu'un exercice fiscal ouvert existe avant toute écriture financière.
     *
     * Appliqué sur : sales, proformas, stock_entries, stock_reconciliations,
     *                purchase_orders, invoices.
     * NON appliqué sur : catalogue, tiers, utilisateurs, paramètres.
     *
     * Injecte current_fiscal_year_id dans la requête pour éviter
     * une seconde requête DB dans le controller.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $fiscalYear = FiscalYear::open()
            ->latest('starts_at')
            ->first();

        if ($fiscalYear === null) {
            return response()->json([
                'message' => 'Aucun exercice fiscal ouvert. Créez ou ouvrez un exercice avant de continuer.',
                'code'    => 'FISCAL_YEAR_CLOSED',
            ], 422);
        }

        // Disponible via $request->current_fiscal_year_id dans les controllers
        $request->merge(['current_fiscal_year_id' => $fiscalYear->id]);

        return $next($request);
    }
}

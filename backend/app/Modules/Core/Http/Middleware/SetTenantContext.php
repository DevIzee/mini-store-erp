<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Lit le tenant_id depuis la session et l'injecte dans le container.
     * Le trait BelongsToTenant lit app('tenant_id') sur chaque requête Eloquent.
     *
     * Doit être exécuté APRÈS auth:sanctum (la session doit être démarrée).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            return response()->json([
                'message' => 'Session expirée. Veuillez vous reconnecter.',
                'code'    => 'SESSION_EXPIRED',
            ], 401);
        }

        // Binding singleton — disponible dans tout le cycle de la requête
        app()->instance('tenant_id', (int) $tenantId);

        return $next($request);
    }
}

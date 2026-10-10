<?php

use App\Modules\Core\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Auth — public (pas de middleware tenant) ───────────────────────────────
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('login', 'login');
        Route::post('logout', 'logout')->middleware('auth:sanctum');
        Route::get('me', 'me')->middleware('auth:sanctum');
    });

    // ── Routes protégées — auth + contexte tenant ──────────────────────────────
    Route::middleware(['auth:sanctum', 'set.tenant'])->group(function () {

        require __DIR__.'/../app/Modules/Core/routes.php';

        // Modules à venir — décommenter au fur et à mesure :
        // require __DIR__.'/../app/Modules/Catalogue/routes.php';
        // require __DIR__.'/../app/Modules/Tiers/routes.php';
        // require __DIR__.'/../app/Modules/Ventes/routes.php';
        // require __DIR__.'/../app/Modules/Achats/routes.php';
        // require __DIR__.'/../app/Modules/Stock/routes.php';
        // require __DIR__.'/../app/Modules/Analytics/routes.php';
        // require __DIR__.'/../app/Modules/ImportExport/routes.php';
    });
});

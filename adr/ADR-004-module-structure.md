# ADR-004 — Structure des Modules Laravel

**Status :** Accepted
**Date :** Session 4
**Décideurs :** Équipe projet

---

## Contexte

Laravel structure par défaut son code dans `app/Models/`, `app/Http/Controllers/`, etc. Pour un projet de la taille d'un ERP (8 modules, ~40 tables, plusieurs dizaines de controllers), cette organisation par type technique crée un couplage implicite et rend difficile de raisonner sur un module isolément.

Il faut choisir entre :

1. **Structure Laravel par défaut** — `app/Models/`, `app/Http/Controllers/`, flat
2. **Domain-Driven Design strict** — Domain/, Application/, Infrastructure/ layers
3. **Co-location par module** — `app/Modules/{Module}/` avec tous les artifacts d'un module ensemble

## Décision

**Co-location par module** dans `app/Modules/`. Chaque module contient ses modèles, controllers, requests, resources, actions, services et observers.

```
app/
└── Modules/
    └── {Module}/
        ├── Actions/          -- logique métier unitaire (CreateSaleAction, etc.)
        ├── Http/
        │   ├── Controllers/
        │   └── Requests/     -- FormRequest de validation
        ├── Models/
        ├── Observers/        -- événements Eloquent (SaleObserver)
        ├── Resources/        -- API Resources (DTO sortie)
        └── Services/         -- services complexes multi-steps
```

Les migrations restent dans `database/migrations/` (convention Laravel — les outils Artisan s'y attendent). Les seeders dans `database/seeders/`.

### Règles inter-modules

Un module peut importer les modèles d'un module dont il **dépend** (voir `system-design.md` section 1). Jamais l'inverse. Exemples autorisés :

```php
// Dans Ventes/Models/Sale.php
use App\Modules\Catalogue\Models\Item;        // ✓ Ventes dépend de Catalogue
use App\Modules\Tiers\Models\Customer;         // ✓ Ventes dépend de Tiers
use App\Modules\Core\Models\Warehouse;         // ✓ tous dépendent de Core

// Dans Catalogue/Models/Item.php
use App\Modules\Ventes\Models\Sale;            // ✗ INTERDIT — Catalogue ne dépend pas de Ventes
```

### Routes

Chaque module expose ses routes dans un fichier dédié, inclus depuis `routes/api.php` :

```php
// routes/api.php
Route::prefix('v1')->middleware(['auth:sanctum', 'set.tenant'])->group(function () {
    require __DIR__.'/../app/Modules/Core/routes.php';
    require __DIR__.'/../app/Modules/Catalogue/routes.php';
    require __DIR__.'/../app/Modules/Tiers/routes.php';
    require __DIR__.'/../app/Modules/Ventes/routes.php';
    require __DIR__.'/../app/Modules/Achats/routes.php';
    require __DIR__.'/../app/Modules/Stock/routes.php';
    require __DIR__.'/../app/Modules/Analytics/routes.php';
    require __DIR__.'/../app/Modules/ImportExport/routes.php';
});
```

### Actions vs Services

- **Action** : une seule responsabilité, un seul `execute()` public, injectable. Ex: `CreateSaleAction`, `ValidateReconciliationAction`. Idéal pour les opérations atomiques testables unitairement.
- **Service** : orchestration multi-steps qui peut appeler plusieurs Actions, ou logique partagée entre plusieurs controllers. Ex: `DocumentNumberingService`, `ReportQueryService`.

### Observers

Les Observers Eloquent sont enregistrés dans `AppServiceProvider::boot()` :

```php
Sale::observe(SaleObserver::class);
```

L'Observer `SaleObserver::created()` décrémente le stock. Séparation claire : le Controller crée la Sale, l'Observer réagit.

## Alternatives écartées

**Structure Laravel par défaut (flat) :** acceptable pour un CRUD simple. Pour 40+ modèles et 8 modules avec des règles métier distinctes, un seul dossier `app/Models/` devient ingérable.

**DDD strict (Domain/Application/Infrastructure) :** trop cérémonieux pour une équipe qui utilise Eloquent. Les Repository patterns et les Value Objects rajoutent de la surface de code sans bénéfice clair quand Laravel ORM est déjà l'abstraction DB.

**Packages Laravel séparés (Modular Laravel) :** utiliser `nwidart/laravel-modules` introduit une dépendance externe et une config non-standard. La co-location manuelle dans `app/Modules/` donne le même résultat sans dépendance.

## Conséquences

**Positives :**
- Chaque module est raisonnable isolément — on peut lire `app/Modules/Ventes/` sans connaître le reste
- Suppression ou désactivation d'un module = supprimer un dossier + ses routes
- Tests organisés en miroir : `tests/Feature/Modules/Ventes/`

**Négatives / risques :**
- Les imports cross-module doivent être vérifiés à la review (pas d'outil de lint automatique prévu en v1) — la convention est documentée ici
- Artisan `make:model`, `make:controller` créent dans les dossiers par défaut → toujours passer `--path` ou déplacer manuellement

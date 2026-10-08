# ADR-001 — Stratégie Multi-tenancy

**Status :** Accepted
**Date :** Session 4
**Décideurs :** Équipe projet

---

## Contexte

`mini-store-erp` est un SaaS multi-tenant. Plusieurs organisations (PME) utilisent la même instance applicative. Il faut garantir l'isolation complète des données entre tenants sans déployer une instance ou une base de données par client.

Les trois patterns classiques sont :

| Pattern | Description | Isolation | Coût infra |
|---|---|---|---|
| Database-per-tenant | Une DB par client | Totale | Très élevé |
| Schema-per-tenant | Un schéma PostgreSQL par client | Forte | Élevé |
| Shared DB + `organisation_id` | Une seule DB, colonne tenant sur chaque table | Logique | Faible |

## Décision

**Shared database avec colonne `organisation_id` sur toutes les tables domaine**, appliquée via un Global Scope Eloquent (`BelongsToTenant`).

Le `tenant_id` (= `organisation_id`) est résolu depuis la session Laravel à chaque requête via le middleware `SetTenantContext`, puis injecté dans un singleton `app('tenant_id')` que le Global Scope lit automatiquement.

```php
// Trait BelongsToTenant — appliqué sur tous les modèles scopés
protected static function booted(): void
{
    static::addGlobalScope('tenant', function (Builder $query) {
        $query->where('organisation_id', app('tenant_id'));
    });

    static::creating(function ($model) {
        $model->organisation_id ??= app('tenant_id');
    });
}
```

## Alternatives écartées

**Database-per-tenant :** coût opérationnel prohibitif pour des PME africaines qui paient un abonnement faible. Les migrations deviendraient ingérables (N bases × N migrations).

**Schema-per-tenant (PostgreSQL) :** nécessite PostgreSQL exclusivement. Le projet supporte MySQL et PostgreSQL — ce pattern crée une dépendance inutile.

## Conséquences

**Positives :**
- Une seule migration à exécuter lors des updates
- Requêtes SQL cross-tenant possibles pour l'analytics SuperAdmin
- Déploiement sur tout hébergeur MySQL/PostgreSQL standard

**Négatives / risques :**
- Un bug dans le scope peut exposer des données cross-tenant → couverture de tests obligatoire : chaque Feature test vérifie l'isolation avec deux tenants distincts
- Les indexes doivent systématiquement inclure `organisation_id` en première colonne composite pour éviter les full scans
- `document_sequences` et `super_admins` sont explicitement exclus du trait (voir `system-design.md` section 2)

## Règles d'application

1. Tout nouveau modèle domaine **doit** inclure le trait `BelongsToTenant`
2. Tout index sur une table scopée **doit** commencer par `organisation_id`
3. Les tests Feature **doivent** créer deux organisations et vérifier le 404 cross-tenant
4. Les seeds de test **ne doivent jamais** réutiliser les IDs d'une organisation à l'autre

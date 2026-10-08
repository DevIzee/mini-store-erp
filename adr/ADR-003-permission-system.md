# ADR-003 — Système de Permissions

**Status :** Accepted
**Date :** Session 4
**Décideurs :** Équipe projet

---

## Contexte

Le système de permissions doit satisfaire plusieurs contraintes issues du domaine :

1. L'interface est **100% permission-driven** — jamais hardcodée par rôle
2. Les rôles par défaut (`Operator`, `Lead_Operator`, `Manager`, `Admin`) ont des permissions prédéfinies mais **personnalisables par Organisation**
3. La hiérarchie de rôles impacte certaines règles métier (ex: suppression de Proforma hiérarchique) mais **pas** la résolution des permissions à l'exécution
4. Un Admin peut créer des rôles custom avec n'importe quel jeu de permissions

## Décision

### Stockage à plat — `role_permissions`

Les permissions effectives d'un rôle sont stockées directement dans `role_permissions(role_id, permission_id)`. Pas d'héritage calculé à la volée.

```
permissions (system-wide, seedées)
    ↓
role_permissions (pivot par organisation)
    ↓
roles (par organisation, hiérarchie numérique)
    ↓
users (un seul rôle par user)
```

Lors du seed d'une nouvelle Organisation, les 4 rôles par défaut sont créés avec leurs permissions pré-configurées copiées dans `role_permissions`. Quand l'Admin personnalise un rôle, on écrit directement dans `role_permissions` — pas de logique d'héritage à recalculer.

### Cache des permissions en session

À la connexion, les permissions du rôle utilisateur sont chargées une fois et mises en cache dans la session :

```php
// AuthController::login() — après Auth::login()
$permissions = $user->role->permissions->pluck('name')->toArray();
session(['permissions' => $permissions, 'tenant_id' => $user->organisation_id]);

// User::hasPermission() — O(1) array lookup
public function hasPermission(string $permission): bool
{
    return in_array($permission, session('permissions', []), true);
}
```

### Hiérarchie de rôle — usage métier uniquement

`roles.hierarchy_level` (1–4) est utilisé **uniquement** pour les règles métier qui le requièrent explicitement :

- Suppression de Proforma : `$deleter->role->hierarchy_level >= $owner->role->hierarchy_level`
- Affichage des utilisateurs gérables : `User::where('role.hierarchy_level', '<', $current->role->hierarchy_level)`

La hiérarchie **n'intervient pas** dans la résolution des permissions — seul `role_permissions` compte.

### Liste des permissions atomiques

| Permission | Description |
|---|---|
| `items.read` | Voir les articles |
| `items.create` | Créer un article |
| `items.update` | Modifier un article |
| `items.delete` | Supprimer un article |
| `items.import` | Importer des articles CSV |
| `items.export` | Exporter des articles CSV |
| `customers.read` | Voir les clients |
| `customers.create` | Créer un client |
| `customers.update` | Modifier un client |
| `customers.delete` | Supprimer un client |
| `suppliers.read` | Voir les fournisseurs |
| `suppliers.create` | Créer un fournisseur |
| `suppliers.update` | Modifier un fournisseur |
| `suppliers.delete` | Supprimer un fournisseur |
| `sales.create` | Créer une vente |
| `sales.read` | Voir les ventes |
| `sales.export` | Exporter les ventes CSV |
| `proformas.create` | Créer un proforma |
| `proformas.read` | Voir les proformas |
| `proformas.delete` | Supprimer un proforma (hiérarchie vérifiée en plus) |
| `proformas.convert` | Convertir un proforma en vente |
| `purchase_orders.create` | Créer un bon de commande |
| `purchase_orders.read` | Voir les bons de commande |
| `purchase_orders.update` | Modifier un bon de commande |
| `purchase_orders.delete` | Supprimer un bon de commande |
| `stock.read` | Voir les niveaux de stock |
| `stock.material_receipt` | Créer une entrée de stock (réception) |
| `stock.material_issue` | Créer une sortie de stock |
| `stock.material_transfer` | Créer un transfert entre warehouses |
| `stock.reconciliation` | Créer/valider une réconciliation |
| `analytics.read` | Accéder aux rapports et statistiques |
| `users.read` | Voir les utilisateurs |
| `users.create` | Créer un utilisateur |
| `users.update` | Modifier un utilisateur |
| `users.delete` | Désactiver un utilisateur |
| `roles.read` | Voir les rôles |
| `roles.update` | Modifier les permissions d'un rôle |
| `warehouses.read` | Voir les warehouses |
| `warehouses.create` | Créer un warehouse |
| `warehouses.update` | Modifier un warehouse |
| `organisation.settings` | Modifier les paramètres de l'organisation |
| `fiscal_years.manage` | Ouvrir/fermer un exercice fiscal |
| `alerts.read` | Voir les alertes de stock bas |
| `alerts.dismiss` | Marquer une alerte comme lue |

### Permissions par défaut par rôle

| Permission | Operator | Lead_Operator | Manager | Admin |
|---|:---:|:---:|:---:|:---:|
| `items.read` | ✓ | ✓ | ✓ | ✓ |
| `items.create` | | ✓ | ✓ | ✓ |
| `items.update` | | ✓ | ✓ | ✓ |
| `items.delete` | | | ✓ | ✓ |
| `items.import` | | ✓ | ✓ | ✓ |
| `items.export` | | ✓ | ✓ | ✓ |
| `customers.read` | ✓ | ✓ | ✓ | ✓ |
| `customers.create` | ✓ | ✓ | ✓ | ✓ |
| `customers.update` | | ✓ | ✓ | ✓ |
| `customers.delete` | | | ✓ | ✓ |
| `suppliers.read` | | ✓ | ✓ | ✓ |
| `suppliers.create` | | ✓ | ✓ | ✓ |
| `suppliers.update` | | ✓ | ✓ | ✓ |
| `suppliers.delete` | | | ✓ | ✓ |
| `sales.create` | ✓ | ✓ | ✓ | ✓ |
| `sales.read` | ✓ | ✓ | ✓ | ✓ |
| `sales.export` | | ✓ | ✓ | ✓ |
| `proformas.create` | ✓ | ✓ | ✓ | ✓ |
| `proformas.read` | ✓ | ✓ | ✓ | ✓ |
| `proformas.delete` | ✓* | ✓* | ✓ | ✓ |
| `proformas.convert` | ✓ | ✓ | ✓ | ✓ |
| `purchase_orders.create` | | | ✓ | ✓ |
| `purchase_orders.read` | | | ✓ | ✓ |
| `purchase_orders.update` | | | ✓ | ✓ |
| `purchase_orders.delete` | | | ✓ | ✓ |
| `stock.read` | ✓ | ✓ | ✓ | ✓ |
| `stock.material_receipt` | | ✓ | ✓ | ✓ |
| `stock.material_issue` | | ✓ | ✓ | ✓ |
| `stock.material_transfer` | | | ✓ | ✓ |
| `stock.reconciliation` | | | ✓ | ✓ |
| `analytics.read` | | | ✓ | ✓ |
| `users.read` | | | ✓ | ✓ |
| `users.create` | | | | ✓ |
| `users.update` | | | | ✓ |
| `users.delete` | | | | ✓ |
| `roles.read` | | | ✓ | ✓ |
| `roles.update` | | | | ✓ |
| `warehouses.read` | ✓ | ✓ | ✓ | ✓ |
| `warehouses.create` | | | | ✓ |
| `warehouses.update` | | | | ✓ |
| `organisation.settings` | | | | ✓ |
| `fiscal_years.manage` | | | | ✓ |
| `alerts.read` | | ✓ | ✓ | ✓ |
| `alerts.dismiss` | | ✓ | ✓ | ✓ |

\* `proformas.delete` pour Operator/Lead_Operator : la permission est accordée, mais la règle hiérarchique s'applique en plus (peut supprimer uniquement son propre proforma ou ceux de rôles inférieurs).

## Alternatives écartées

**Héritage runtime de permissions :** calculer les permissions à la volée depuis la hiérarchie de rôle est plus flexible mais plus lent et plus complexe à déboguer. Le stockage à plat est transparent — on voit exactement ce qu'un rôle peut faire dans la table.

**Spatie Laravel-Permission :** package mature mais introduit des concepts (Guards, morphables) qui ne correspondent pas exactement au modèle de données choisi. La surface de code custom serait plus grande que la valeur ajoutée du package.

## Conséquences

**Positives :**
- Permissions visibles directement dans `role_permissions` — debuggable en SQL
- Personnalisation par Organisation : chaque tenant peut avoir son propre `Operator` avec des permissions différentes
- Cache session → zéro requête DB sur la vérification de permission

**Négatives / risques :**
- Si les permissions d'un rôle changent, les sessions actives doivent être invalidées → `Auth::logoutOtherDevices()` ou TTL de session court (30 min)
- Duplication des permissions par Organisation (chaque tenant a ses propres lignes `role_permissions`) → volume acceptable pour des dizaines/centaines de tenants

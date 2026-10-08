# Handoff — mini-store-erp (Session 3)

## Contexte du projet

Micro-ERP SaaS léger pour PME en Afrique de l'Ouest (Togo, UEMOA). Stack : Laravel 13 (backend) + Vue 3/Vite (frontend). Structure monorepo : `backend/` + `frontend/`.

Nom du projet : **mini-store-erp** (le README racine dit encore `mini-store-erp` — à renommer).

## Statut

**Tous les points domaine sont clarifiés.** Prêt pour le codage. Prochaine action : `/engineering:system-design` (Étape 0 per AGENT_INSTRUCTIONS.md).

---

## Décisions validées cette session (session 3)

### Ventes & Proformas

- `Sale` : irréversible une fois validée — `sales.delete` n'existe pas
- `Proforma` deletion : hiérarchique — un utilisateur peut supprimer les Proformas des rôles inférieurs, pas supérieurs. Le créateur peut toujours supprimer le sien
- `Proforma` conversion : n'importe quel utilisateur avec accès au warehouse peut convertir, sans restriction de rôle

### Visibilité & Scope Warehouse

- `Operator` / `Lead_Operator` : scopés à **un seul** warehouse (affectation unique à la création du compte)
- Leurs données (ventes, proformas, stock) sont limitées à leur warehouse assigné
- `Manager` / `Admin` : visibilité totale tous warehouses

### Mouvements de stock

- `MaterialReceipt` + `MaterialIssue` : `Lead_Operator` et au-dessus
- `MaterialTransfer` : `Manager` / `Admin` uniquement
- `StockReconciliation` : `Manager` / `Admin` uniquement — pas de notion de draft pour Lead_Operator, pas d'accès du tout

### Retour client

- Qui : `Lead_Operator` et au-dessus créent le `StockEntry — MaterialReceipt`
- Note : champ texte libre obligatoire expliquant le motif du retour
- Pas de remboursement à tracer dans le système

### PurchaseOrder

- Accès : `Manager` / `Admin` uniquement
- Champs : `reference` (auto-générée), `supplier_id` (requis), `date`, `notes` (texte libre)
- Lignes : `item_id`, `quantity`, `unit_price` (indicatif, optionnel), `tax_id` (optionnel par ligne)

### Permissions atomiques

- Liste complète définie et ajoutée dans `context.md` section "Permissions atomiques"
- Tableau par défaut par rôle inclus
- Pas de `Accountant` dans les rôles par défaut — les rôles sont personnalisables par Organisation

---

## Ce qui a été validé dans les sessions précédentes (ne pas remettre en question)

Voir `context.md` — tout y est documenté.

Points clés à retenir :

- `StockReconciliation` : multi-lignes, scoped à un seul Warehouse, `product` items uniquement, validation irréversible avec `lockForUpdate()`
- Alertes stock bas : évaluées par Warehouse, `in_app` uniquement en v1
- Import/Export : CSV uniquement, accès par rôle défini dans `context.md`
- Interface 100% permission-driven, jamais hardcodée par rôle
- Un seul Admin par Organisation
- `SuperAdmin` developer-only, invisible UI tenant
- Analytics : 8 rapports définis dans `context.md`

---

## Prochaine étape

### Étape 0 — per AGENT_INSTRUCTIONS.md section 6.0

1. **`/engineering:system-design`** — design global DB + architecture système
2. **`/engineering:architecture`** — ADRs pour chaque décision structurante (multi-tenancy, auth, queue driver)
3. **`/impeccable init`** — créer `PRODUCT.md` + `DESIGN.md` (identité visuelle)
4. Coder le module Core / Authentification (socle complet avant tout module métier)

---

## Instructions pour l'agent suivant

1. Lire `context.md` en premier — glossaire, règles domaine, permissions
2. Lire `AGENT_INSTRUCTIONS.md` — méthodologie stricte à suivre
3. Ne jamais utiliser : `Article`, `StockAdjustment`, `Inventory`, `Client`, `Order`
4. Utiliser : `Item`, `StockReconciliation`, `StockEntry`, `Customer`, `Sale`/`Proforma`
5. La Règle d'Or s'applique : Quoi / Pourquoi / Avantages / Futur + validation explicite avant tout code
6. Déclencher `/handoff` en fin de session, fin de module, ou à ~75% de contexte
7. Commencer par `/engineering:system-design` — pas de code avant ce document

# mini-store-erp

Micro-ERP SaaS léger pour les petites entreprises (boutiques, supérettes, grossistes) en Afrique de l'Ouest. Priorité à la simplicité d'usage sur l'exhaustivité fonctionnelle.

## Décisions architecturales

### Multi-tenancy

**Stratégie : Single database, `tenant_id` column filtering**

Every tenant-scoped model uses a `BelongsToTenant` trait that applies a Global Scope automatically. No raw `WHERE tenant_id =` in application code.

```php
class Sale extends Model {
    use BelongsToTenant; // Global scope injected automatically
}
```

- Single `php artisan migrate` updates all tenants instantly
- Single database backup
- Tenant isolation enforced at ORM level — not application logic

**Authentication flow:**

1. User submits email + password on `app.tonsaas.com`
2. Laravel verifies credentials → retrieves `tenant_id` linked to user
3. `tenant_id` stored in session
4. `BelongsToTenant` Global Scope reads session `tenant_id` on every query

**Tenant** = `Organisation` in domain language.

### Année Fiscale & Numérotation

**FiscalYear**:
A time-bounded accounting period with an explicit `status`. Acts as a temporal lock — no financial document (Sale, Invoice, StockEntry…) can be created, modified, or deleted in a `closed` FiscalYear. Checked by middleware before every write operation.

Key fields: `organisation_id`, `name` (ex: `"Exercice 2025"`), `starts_at`, `ends_at`, `status` (`open` | `closed`).

Two modes, configurable per Organisation (`fiscal_year_mode`):

- `simple` — system auto-generates a single rolling FiscalYear from `fiscal_year_start`. No manual management needed. Good for small shops.
- `managed` — Admin explicitly opens and closes FiscalYears. Enables audit-grade temporal locking. Recommended for accountant-heavy setups.

**DocumentSequence**:
An isolated counter table that generates unique, sequential document numbers per Organisation, per document type, per fiscal year. Uses `lockForUpdate()` inside a DB transaction to prevent race conditions (multiple cashiers validating at the same millisecond).

Schema: `(organisation_id, document_type, fiscal_year, last_value)` — composite primary key.

Two numbering strategies, configurable per Organisation (`numbering_strategy`):

- `yearly_reset` — sequence resets each fiscal year. Format: `FAC-2025-000001`. Recommended (SYSCOHADA-compliant, readable).
- `continuous` — sequence never resets. Format: `FAC-000001`. Simpler but less common locally.

Configurable per Organisation:

- `document_prefix` — default prefixes provided (`FAC`, `PRO`, `BL`, `BC`) but overridable
- `sequence_padding` — number of digits (default 6)

Design principle: both `fiscal_year_mode` and `numbering_strategy` are Organisation-level settings. Upgrading from `simple` to `managed`, or changing numbering strategy, requires no code change — only a config update.

**BarcodeScanner** (composant UI réutilisable):
A reusable UI component that identifies an Item by its `barcode` field. Available in the following contexts:

- `Sale` (quick sale, Invoice, Proforma) → adds Item to cart with quantity 1, adjustable
- `StockEntry — MaterialReceipt` → identifies incoming Item
- `StockEntry — MaterialIssue` → identifies Item being removed
- `StockEntry — MaterialTransfer` → identifies Item being moved
- `StockReconciliation` → identifies Item being counted

Rules:

- Manual input is **always available** regardless of scanner state — scan is an enhancement, never a requirement
- Activated/deactivated globally in `Organisation` settings (`barcode_scanner_enabled: boolean`)
- When deactivated: component is hidden everywhere, manual input handles all flows
- An Item without a barcode is simply unreachable by scan — no error, no blocking

- Current target currency: **XOF (Franc CFA)** — no decimal display
- All monetary amounts stored as `DECIMAL(15, 4)` — future-proof for multi-currency extension
- `currency_code` (`VARCHAR(3)`) stored on `Organisation`, `Sale`, and `Invoice` for historical accuracy
- Display layer handles formatting: 0 decimals for XOF, configurable for other currencies
- Multi-currency is **not implemented** in v1 but the schema supports it without migration

1. Cashier signals the return to hierarchy (comment or alert — no system action on stock)
2. `Lead_Operator` or above manually creates a `StockEntry — MaterialReceipt` to reintroduce goods, with a mandatory free-text note explaining the reason
3. Any financial settlement (refund, credit) is handled outside the system
   No `CreditNote` is generated. The two acts (stock and financial) are intentionally decoupled.

### Vente & Facturation

**Sale**:
A confirmed, immediate counter transaction between a cashier and a customer. Irreversible once validated. Triggers stock decrement.
_Avoid_: Order, Transaction, Operation

**Proforma**:
A draft sale sent to a customer for approval before commitment. Does not affect stock. Converted into a Sale upon customer confirmation.

Deletion rules (hierarchical): a user can delete any Proforma created by a user with a lower or equal role. The creator can always delete their own. Deletion upward (a lower role deleting a higher role's Proforma) is forbidden.

Conversion to Sale: any user with access to the Proforma's Warehouse can convert it — no role restriction beyond Warehouse scope. Conversion is not limited to the creator.

_Avoid_: Quote, Estimate, Order

**Invoice**:
The billing document generated automatically when a Sale is validated. Can be printed as an 80mm thermal ticket or an A4 PDF depending on the user role.
_Avoid_: Bill, Receipt, Facture

**SaleLine**:
→ See definition under "Entrepôts & Affectation". Each line carries `warehouse_id`, `item_id`, `quantity`, `unit_price`, `tax_amount`.
_Avoid_: Item, OrderLine, Product line

**DeliveryNote**:
A printable document generated simultaneously with a validated Invoice (formal sales only). Accompanies physical goods delivered to a Customer and is signed by the Customer upon receipt. Not generated for counter Sales or Proformas.
_Avoid_: BonDeLivraison, ShippingNote, Packing slip

**PurchaseOrder**:
A digital document sent to a Supplier to request goods or services. Created for traceability and to avoid manual paper forms. Has no effect on stock, no workflow, no status. Can be printed and sent manually. Can be deleted.

Fields: `reference` (auto-generated), `supplier_id` (required), `date`, `notes` (free text).
Lines: `item_id`, `quantity`, `unit_price` (indicative, optional), `tax_id` (optional per line).

Access: `Manager` and `Admin` only — Lead_Operator cannot create or delete PurchaseOrders.

_Avoid_: Order, SupplierOrder, CommandeFournisseur

### Stock

**StockEntry**:
A recorded stock movement entered manually by an authorised user. Always references a Warehouse and an Article. Three subtypes exist. The sole source of truth for daily stock changes alongside Sale-triggered outbound movements.
_Avoid_: StockMovement, StockOperation, Mouvement

**StockEntry — MaterialReceipt**:
Inbound movement: adds stock quantity (relative). Used for initial stock loading, received goods outside a standard purchase flow, or surplus found during a count.
Example: "Add 10 units of rice to Warehouse A."

**StockEntry — MaterialIssue**:
Outbound movement: removes stock quantity (relative) without generating a Sale. The removed value is posted as a loss. Used for breakage, expired goods, theft, or internal consumption.
Example: "Remove 2 bottles (breakage) from Warehouse B."

**StockEntry — MaterialTransfer**:
Lateral movement between two Warehouses. Total stock quantity unchanged across the organisation. Restricted to `Manager` and `Admin` — Lead_Operator cannot initiate transfers.
Example: "Move 5 cartons from Warehouse 1 to Warehouse 2."

**StockReconciliation**:
A multi-line absolute stock correction document, scoped to exactly one Warehouse. Only `product`-type Items can be added to a line (services have no stock). Has a mandatory `description` (ex: "Réconciliation après inventaire du 30 Mai 2026") and a `status` (`draft` | `validated`).

- **Draft**: the user enters the physically counted quantity per line. The system shows a _provisional_ delta for review, but nothing is posted to stock yet.
- **Validated**: irreversible. At the exact moment of validation, the system re-fetches the live system stock for each line (not the value shown when the draft was opened), computes the final delta, and posts the adjustment. Each validated line stores: system stock before, applied delta (+/-), new real stock after.
- **Concurrency safety**: validation acquires a row-level DB lock (`lockForUpdate`) on the concerned Item_Stock rows for the duration of the calculation + update, preventing concurrent Sales or StockEntry from racing with the reconciliation.

Requires `Manager` or `Admin` role — Lead*Operator has no access to this feature.
\_Avoid*: StockAdjustment, Correction, Inventory adjustment, Inventory

**Extraction list** (fonctionnalité, pas une entité du domaine):
An exportable/printable report listing Items with their current stock, filterable by Warehouse or Item*Group before generation. Used to support the physical counting process on paper — not a persisted document, no system state. The physical count it supports is then entered into a StockReconciliation.
\_Avoid*: Inventory, Stock count document

### Alertes de stock bas

Configurable per Organisation via `low_stock_threshold_mode` (`global` | `per_item`):

- `global` — a single `low_stock_threshold` value on `Organisation` applies to every Item. The field is hidden from the Item form.
- `per_item` — `low_stock_threshold` becomes a required field on the Item form, set individually per Item.

Evaluated **per Warehouse**, not aggregated across the Organisation — consistent with `Item_Stock (item_id, warehouse_id, quantity)`. An Item can be "low" in Warehouse A while fine in Warehouse B.

Notification channel: schema provisioned (`notification_channels` — extensible list, e.g. `in_app`, `email`, `sms`) but **only `in_app` is implemented in v1**. Visible to `Lead_Operator` and above, scoped to their accessible Warehouse(s).

### Tiers

**Customer**:
A person or organisation that buys from the business. All sales are immediate cash transactions — no credit accounts.

Fields: `name` (required), `phone`, `email`, `address`, `tax_id` (NIF/IFU).
_Avoid_: Client, Buyer, Account

**Supplier**:
A person or organisation that sells goods or services to the business.

Fields: `name` (required), `phone`, `email`, `address`, `tax_id` (NIF/IFU), `contact_name` (nom du représentant), `contact_phone`.
_Avoid_: Vendor, Provider, Fournisseur

### Catalogue

**Item**:
A sellable entity, physical (`product`) or intangible (`service`). Has a name, optional barcode, item group, selling price, and — for products only — a purchase price.

- `product`: subject to stock control. `purchase_price` required (enables gross margin calculation). Cannot be sold if available stock < requested quantity (blocking, not a warning). Generates StockEntry on validated Sale.
- `service`: no stock tracking, no StockEntry generated. Only `selling_price` required. `purchase_price` is nullable.
  _Avoid_: Article, Product, SKU, Goods

**Item_Group**:
A category grouping Items for reporting and navigation. A default group `Uncategorised` always exists and cannot be deleted.
_Avoid_: ArticleGroup, Category, Family, Department

**UnitOfMeasure**:
The unit in which an Item is counted or sold (piece, kg, litre, carton…).
_Avoid_: Unit, Measure

**Item_Stock**:
A pivot record linking an Item to a Warehouse with its current available quantity. Stock quantity never lives on the Item itself. One Item can exist across multiple Warehouses without duplicating its master record.
Schema: `(item_id, warehouse_id, quantity)`
_Avoid_: StockLevel, ItemWarehouse

### Entrepôts & Affectation

**Warehouse**:
A physical or logical stock location. `Operator` and `Lead_Operator` are assigned to exactly **one** Warehouse at account creation — they can only see and act on data (sales, proformas, stock) from their assigned Warehouse. `Manager` and `Admin` have full visibility across all Warehouses and can mix items from different Warehouses in a single Sale.
_Avoid_: Depot, Store, Location

**SaleLine**:
A single item line within a Sale. Always carries its own `warehouse_id` (source warehouse for stock decrement), `item_id`, `quantity`, `unit_price`, and computed `tax_amount`. For `Operator`/`Lead_Operator` the `warehouse_id` is auto-filled from the user's assigned Warehouse. For upper roles it is selectable per line.
_Avoid_: OrderLine, CartItem

### Utilisateurs & Accès

**Permission**:
An atomic, system-wide, hardcoded action a user can perform. Named with dot notation: `sales.invoice.create`, `stock.movement.import`. Never modified per tenant.
_Avoid_: Right, Access, Privilege

**Permission_Group**:
A named collection of related Permissions to simplify role configuration in the admin UI. Example: `Manage_Stock` bundles create, read, update, import, export on stock movements.
_Avoid_: PermissionSet, AccessGroup

**Role**:
A named set of Permissions specific to one Tenant. Predefined roles exist as defaults but each Tenant can fully customise them (add or remove permissions per role). Roles follow a linear inheritance hierarchy (ascending) — each role inherits all permissions of roles below it. Stored flattened in `role_permission` for query performance.

Default role hierarchy (ascending inheritance):

```
Operator → Lead_Operator → Manager → Admin
```

| Role            | Primary responsibility                                            |
| --------------- | ----------------------------------------------------------------- |
| `Operator`      | Counter sales, thermal ticket(Invoices), barcode scan, proformas  |
| `Lead_Operator` | Daily operations management, stock entries, purchase orders       |
| `Manager`       | financial reports, reconciliation, analytics, user management     |
| `Admin`         | Everything + organisation settings, subscriptions, configurations |

**Permission inheritance & customisation rules:**

- Every role's permissions are customisable per Organisation (add/remove) — except Admin's locked core
- There is **exactly one Admin per Organisation** (= the client/tenant owner in business language)
- `Admin` has a **locked permission set** (cannot be removed by anyone, including SuperAdmin): organisation settings, user/role management, subscription management.
- `Admin` can modify permissions of all roles below them, but **cannot modify their own permissions** and cannot see or interact with SuperAdmin.
- `SuperAdmin` (developer-only): exists outside the tenant system, never visible in the UI. Can modify Admin permissions if needed. SuperAdmin actions are never logged or visible to any tenant user. Not a Role — a separate auth layer.

**User Profile** (accessible to every user regardless of role, no permission required):

- Language preference
- Password change
- Email / phone update
- Account recovery settings
- UI preferences (theme colours, display density, etc.)

_Avoid_: Profile, Group, Level, UserType

**User**:
A person with access to the system, identified by phone or email. Has a language, timezone, currency preference, and belongs to one Role per Tenant.
_Avoid_: Account, Member, Agent

### Audit & Traçabilité

**Activity_Log**:
Records every CRUD action on sensitive data (price changes, stock edits, proforma deletions). Stores before/after values as JSON. Linked to User, Tenant, IP address.

**Sales_Log**:
Records every validated Sale: user ID, sale ID, amount, timestamp, terminal. Immutable.

**Permission_Log**:
Records every change to a User's role or permissions: who changed it, what changed, when.

**Access_Log**:
Records all logins, logouts, IP addresses, terminals, and expired sessions.

### Analytics & Reporting

Rapports v1 (liste volontairement restreinte — stats avancées repoussées aux versions suivantes) :

1. Ventes par période (jour/semaine/mois) — montant total, nombre de transactions
2. Nombre de factures (Invoice) émises sur une période
3. Ventes par Item — quantités vendues, chiffre d'affaires généré
4. Produits les plus vendus (classement par quantité ou CA)
5. Marge brute (`selling_price` − `purchase_price`) par Item et globale
6. Valeur du stock actuel (quantité × `purchase_price`) par Warehouse
7. Ventes par caissier (Operator)
8. Meilleurs clients (Customer) — classement par CA ou nombre d'achats

### Import / Export

Unified module — user selects the entity to import or export (Item, Customer, Supplier, Item_Group, UnitOfMeasure, etc.). Format: **CSV uniquement en v1**.

**Activation toggle:** `import_export_enabled: boolean` on Organisation settings (Admin). If disabled, the entire module is hidden from all users. SuperAdmin can also force-disable it platform-wide.

**Import order** (dependency chain — must be respected) :
`UnitOfMeasure` → `Item_Group` → `Tax` → `Item` → `Customer` / `Supplier`

`Item_Stock` import: **SuperAdmin / developer only** — not exposed in the tenant UI in v1.

**Access by role (defaults, customisable per Organisation):**

| Action                                       | Rôle minimum par défaut |
| -------------------------------------------- | ----------------------- |
| Export (toutes entités)                      | `Manager`               |
| Import `Item`, `Item_Group`, `UnitOfMeasure` | `Manager`               |
| Import `Customer`, `Supplier`                | `Lead_Operator`         |

**Import rules (strict) :**

- Missing parent reference → line rejected with explicit error. No silent creation.
- Pre-import validation report before any write: all errors listed by row/column, nothing committed until file is clean.
- Case-insensitive matching for reference lookups (Item_Group name, UnitOfMeasure name…)
- Duplicate detection: same unique key → rejected with "already exists" error (no silent overwrite)
- Encoding: UTF-8 enforced, BOM stripped if present
- Empty/malformed rows: skipped with warning
- All-or-nothing per import session: if any row fails validation, entire import is blocked until fixed

**Export:** same entity selector. Exports current data as CSV. Relationships exported as human-readable labels (not raw IDs) for readability and re-importability.

### Paramètres & Configuration

Two strictly separated levels — no overlap, no delegation.

**Organisation settings** (Admin only):
Logo, company identity (name, legal_status, IFU, RCCM, address, phone, email), currency, timezone, language default, fiscal_year_mode, numbering_strategy, document prefixes, default tax rate, low_stock_threshold_mode, barcode_scanner_enabled, thermal_print_logo, document header/footer, subscription management.

**User Profile** (every user, always accessible regardless of role, no permission required):
Language, password, email/phone, account recovery settings, UI preferences (theme colours, display density, etc.).

### Documents imprimables

Concerned documents: `Invoice` (80mm thermal ticket + A4 PDF), `Proforma` (A4), `DeliveryNote` (A4).

**v1 — Header/Footer configurable, fixed layout:**

- Layout structure is fixed per document type (columns, line items, tax summary, totals)
- `Organisation` settings allow uploading/configuring:
  - **Header**: logo, company name, legal_status, address, phone, email, IFU, RCCM
  - **Footer**: legal mentions, slogan, bank details, custom text
- Header and footer are stored per Organisation and applied consistently across all printed documents

**Template extensibility (v2-ready):**

- `template_name` field stored on `Organisation` (default: `"default"`)
- Adding new visual templates in v2 requires no schema migration

**Print formats per document:**

- `Invoice` counter sale → 80mm thermal ticket (Operator-triggered). Logo is optional on thermal: `thermal_print_logo: boolean` on Organisation settings — graceful degradation to text-only if disabled.
- `Invoice` formal → A4 PDF (Accountant/Manager-triggered)
- `Proforma` → A4 PDF only
- `DeliveryNote` → A4 PDF only

_(Reste à définir : Point 8 — Paramètres & Configuration utilisateur vs Organisation)_

**Organisation**:
The business entity (Tenant) using the SaaS. Has full fiscal identity and branding settings.

Key fields: `name`, `legal_status` (SARL, SUARL, SA, Établissement…), `ifu` (Identifiant Fiscal Unique), `rccm` (Registre du Commerce et du Crédit Mobilier), `address`, `po_box`, `phone`, `email`, `logo`, `currency`, `timezone`, `language`, `fiscal_year_start`.

`legal_status` appears next to the organisation name on all printed documents (invoices, tickets, proformas).
`ifu` and `rccm` appear on all formal Invoices and DeliveryNotes.

**Subscription**:
The active SaaS plan for an Organisation. Structure exists in v1 but feature-gating is not enforced until v2.
Key fields: `plan` (VARCHAR: `starter`, `pro`, `enterprise`), `expires_at`, `limits` (JSON — max users, warehouses, etc.), `status` (`active`, `expired`, `suspended`).
_Avoid_: Plan, License, Forfait

**Tax**:
A named fiscal charge applied to a SaleLine or InvoiceLine. Has a `rate` (percentage) and belongs to a `TaxType`. Fully configurable per Organisation. Multiple taxes can coexist on the same Invoice.
Example: `TVA 18%`, `TVA 0% (exonéré)`.
_Avoid_: VAT, Rate, Taxe

**TaxType**:
A category of tax that groups related Tax rates. Designed for extensibility — new tax types (TTS, withholding tax…) can be added without code changes.
Example: `TVA`, `TTS`.
_Avoid_: TaxCategory, FiscalType

**Tax calculation rules:**

- Every Organisation is VAT-registered by default
- Tax is calculated at the line level (`SaleLine`, `InvoiceLine`) — each line stores its own computed tax amount
- Multiple rates can appear on the same document (e.g. 18% on line 1, 0% on line 2)
- Invoice/ticket footer displays a tax summary grouped by TaxType and rate
- Organisation-level default tax rate is configurable but overridable per Article

### Permissions atomiques

Permissions are system-wide, hardcoded, and role-independent. Roles are pre-configured bundles of permissions, customisable per Organisation.

**Ventes**

| Permission         | Description                                                    |
| ------------------ | -------------------------------------------------------------- |
| `sales.create`     | Créer une vente (Sale)                                         |
| `sales.read`       | Voir les ventes (scoped warehouse pour Operator/Lead_Operator) |
| `proforma.create`  | Créer un Proforma                                              |
| `proforma.read`    | Voir les Proformas (scoped warehouse)                          |
| `proforma.delete`  | Supprimer un Proforma (hierarchical — cf. règle Proforma)      |
| `proforma.convert` | Convertir un Proforma en Sale                                  |

Note: `sales.delete` n'existe pas — une Sale validée est permanente et irréversible.

**Stock**

| Permission                    | Description                                                         |
| ----------------------------- | ------------------------------------------------------------------- |
| `stock.read`                  | Voir les niveaux et mouvements de stock (scoped warehouse)          |
| `stock_entry.receipt`         | Créer un MaterialReceipt (Lead_Operator+)                           |
| `stock_entry.issue`           | Créer un MaterialIssue (Lead_Operator+)                             |
| `stock_entry.transfer`        | Créer un MaterialTransfer (Manager/Admin uniquement)                |
| `stock_reconciliation.manage` | Créer et valider une StockReconciliation (Manager/Admin uniquement) |

**Catalogue**

| Permission    | Description       |
| ------------- | ----------------- |
| `item.read`   | Voir les Items    |
| `item.create` | Créer un Item     |
| `item.edit`   | Modifier un Item  |
| `item.delete` | Supprimer un Item |

**Tiers**

| Permission              | Description                                |
| ----------------------- | ------------------------------------------ |
| `customer.create`       | Créer un Customer                          |
| `customer.edit`         | Modifier un Customer                       |
| `customer.delete`       | Supprimer un Customer                      |
| `supplier.create`       | Créer un Supplier                          |
| `supplier.edit`         | Modifier un Supplier                       |
| `supplier.delete`       | Supprimer un Supplier                      |
| `purchase_order.create` | Créer un PurchaseOrder (Manager/Admin)     |
| `purchase_order.delete` | Supprimer un PurchaseOrder (Manager/Admin) |

**Utilisateurs & Rôles**

| Permission     | Description                                               |
| -------------- | --------------------------------------------------------- |
| `users.create` | Créer un utilisateur                                      |
| `users.edit`   | Modifier un utilisateur                                   |
| `users.delete` | Supprimer/désactiver un utilisateur                       |
| `roles.manage` | Créer, modifier, supprimer des rôles et leurs permissions |

**Rapports**

| Permission     | Description                      |
| -------------- | -------------------------------- |
| `reports.read` | Accéder aux rapports analytiques |

**Import / Export**

| Permission                   | Description                              |
| ---------------------------- | ---------------------------------------- |
| `export.all`                 | Exporter toute entité en CSV             |
| `import.items`               | Importer Item, Item_Group, UnitOfMeasure |
| `import.customers_suppliers` | Importer Customer, Supplier              |

**Configuration**

| Permission            | Description                                                              |
| --------------------- | ------------------------------------------------------------------------ |
| `config.organisation` | Modifier les paramètres de l'Organisation (Admin uniquement, verrouillé) |
| `config.warehouses`   | Créer et modifier des Warehouses                                         |
| `alerts.read`         | Voir les alertes de stock bas (scoped warehouse)                         |

**Permissions par rôle par défaut :**

| Permission                    | Operator |    Lead_Operator    |    Manager     |    Admin    |
| ----------------------------- | :------: | :-----------------: | :------------: | :---------: |
| `sales.create`                |    ✅    |         ✅          |       ✅       |     ✅      |
| `sales.read`                  |    ✅    |         ✅          |       ✅       |     ✅      |
| `proforma.create`             |    ✅    |         ✅          |       ✅       |     ✅      |
| `proforma.read`               |    ✅    |         ✅          |       ✅       |     ✅      |
| `proforma.delete`             | ✅ (own) | ✅ (own + Operator) | ✅ (all below) |  ✅ (all)   |
| `proforma.convert`            |    ✅    |         ✅          |       ✅       |     ✅      |
| `stock.read`                  |    ❌    |         ✅          |       ✅       |     ✅      |
| `stock_entry.receipt`         |    ❌    |         ✅          |       ✅       |     ✅      |
| `stock_entry.issue`           |    ❌    |         ✅          |       ✅       |     ✅      |
| `stock_entry.transfer`        |    ❌    |         ❌          |       ✅       |     ✅      |
| `stock_reconciliation.manage` |    ❌    |         ❌          |       ✅       |     ✅      |
| `item.read`                   |    ✅    |         ✅          |       ✅       |     ✅      |
| `item.create`                 |    ❌    |         ❌          |       ✅       |     ✅      |
| `item.edit`                   |    ❌    |         ❌          |       ✅       |     ✅      |
| `item.delete`                 |    ❌    |         ❌          |       ✅       |     ✅      |
| `customer.create`             |    ❌    |         ✅          |       ✅       |     ✅      |
| `customer.edit`               |    ❌    |         ✅          |       ✅       |     ✅      |
| `customer.delete`             |    ❌    |         ❌          |       ✅       |     ✅      |
| `supplier.create`             |    ❌    |         ❌          |       ✅       |     ✅      |
| `supplier.edit`               |    ❌    |         ❌          |       ✅       |     ✅      |
| `supplier.delete`             |    ❌    |         ❌          |       ✅       |     ✅      |
| `purchase_order.create`       |    ❌    |         ❌          |       ✅       |     ✅      |
| `purchase_order.delete`       |    ❌    |         ❌          |       ✅       |     ✅      |
| `users.create`                |    ❌    |         ❌          |       ❌       |     ✅      |
| `users.edit`                  |    ❌    |         ❌          |       ❌       |     ✅      |
| `users.delete`                |    ❌    |         ❌          |       ❌       |     ✅      |
| `roles.manage`                |    ❌    |         ❌          |       ❌       |     ✅      |
| `reports.read`                |    ❌    |         ❌          |       ✅       |     ✅      |
| `export.all`                  |    ❌    |         ❌          |       ✅       |     ✅      |
| `import.items`                |    ❌    |         ❌          |       ✅       |     ✅      |
| `import.customers_suppliers`  |    ❌    |         ✅          |       ✅       |     ✅      |
| `config.organisation`         |    ❌    |         ❌          |       ❌       | ✅ (locked) |
| `config.warehouses`           |    ❌    |         ❌          |       ✅       |     ✅      |
| `alerts.read`                 |    ❌    |         ✅          |       ✅       |     ✅      |

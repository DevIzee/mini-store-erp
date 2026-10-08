# System Design — mini-store-erp

> Référence unique pour le schéma DB, l'architecture des modules et les flux critiques.
> Toute décision technique structurante est documentée ici ou dans `docs/adr/`.

---

## 1. Module Map

```
Core ──────────────────────────────────────────────────────────────────────┐
│  Organisation, User, Role, Permission, Warehouse                         │
│  FiscalYear, DocumentSequence, SuperAdmin                                │
└──► Catalogue ──────────────────────────────────────────────────────────┐ │
     │  Item, Item_Group, UnitOfMeasure, Tax, TaxType, Item_Stock        │ │
     └──► Ventes ───────────────────────────────────────────────────────┐│ │
          │  Sale, SaleLine, Proforma, ProformaLine, Invoice,           ││ │
          │  DeliveryNote                                                ││ │
          └──► Analytics (read-only — pas de tables propres)            ││ │
     └──► Stock ────────────────────────────────────────────────────────┤│ │
          │  StockEntry, StockEntryLine,                                 ││ │
          │  StockReconciliation, StockReconciliationLine                ││ │
          └──► Analytics                                                 ││ │
     └──► Achats ───────────────────────────────────────────────────────┘│ │
          PurchaseOrder, PurchaseOrderLine                                │ │
Tiers ────────────────────────────────────────────────────────────────────┘ │
│  Customer, Supplier                                                        │
└──► Ventes, Achats                                                          │
ImportExport ────────────────────────────────────────────────────────────────┘
│  ImportJob (transient, queue-based)
Audit ─── reçoit des événements de tous les modules
│  ActivityLog, SalesLog, PermissionLog, AccessLog
```

**Règle de dépendance :** un module peut importer des models d'un module dont il dépend. Jamais l'inverse. Analytics est uniquement en lecture sur Ventes + Stock.

---

## 2. Tables avec `BelongsToTenant`

Toutes les tables ci-dessous ont une colonne `organisation_id BIGINT NOT NULL` et sont scoped automatiquement via le trait `BelongsToTenant`.

| Module       | Tables scopées                                                                                                                   |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------- |
| Core         | `subscriptions`, `fiscal_years`, `warehouses`, `roles`, `users`                                                                  |
| Catalogue    | `item_groups`, `unit_of_measures`, `tax_types`, `taxes`, `items`, `item_stocks`                                                  |
| Tiers        | `customers`, `suppliers`                                                                                                         |
| Ventes       | `proformas`, `proforma_lines`*, `sales`, `sale_lines`*, `invoices`, `delivery_notes`                                             |
| Achats       | `purchase_orders`, `purchase_order_lines`*                                                                                       |
| Stock        | `stock_entries`, `stock_entry_lines`*, `stock_reconciliations`, `stock_reconciliation_lines`*                                    |
| Audit        | `activity_logs`, `sales_logs`, `permission_logs`, `access_logs`                                                                  |
| ImportExport | `import_jobs`                                                                                                                    |
| Alerts       | `stock_alerts`                                                                                                                   |

\* Les tables `_lines` héritent de l'isolation via FK → header scopé. Elles n'ont pas de `organisation_id` propre pour éviter la redondance, mais leur accès passe toujours par le header parent.

**Tables SANS `BelongsToTenant` :**

| Table                | Raison                                             |
| -------------------- | -------------------------------------------------- |
| `organisations`      | C'est le tenant lui-même                           |
| `permissions`        | System-wide, hardcodé, jamais modifié par tenant   |
| `document_sequences` | Scoped par `organisation_id` mais pas de trait ORM — accès uniquement via `DocumentNumberingService` avec `lockForUpdate()` |
| `super_admins`       | Couche developer, hors système tenant              |

---

## 3. Schéma DB complet

### 3.0 Conventions

- **PK** : `id BIGINT UNSIGNED AUTO_INCREMENT` sauf mention contraire
- **FK** : `BIGINT UNSIGNED NOT NULL` sauf `NULLABLE` explicite
- **Soft delete** : colonne `deleted_at TIMESTAMP NULL` — indiqué `[soft]`
- **Montants** : `DECIMAL(15,4)` partout — 0 décimales affichées pour XOF
- **Dates stockées** : UTC toujours. `DATE` pour les dates métier (ventes, stocks), `TIMESTAMP` pour les événements système
- **Enums** : `VARCHAR` + contrainte CHECK — plus facile à migrer qu'`ENUM` MySQL natif

---

### 3.1 Module Core

#### `organisations`
```
id                      BIGINT PK
name                    VARCHAR(255)   NOT NULL
legal_status            VARCHAR(50)    NULLABLE   -- SARL, SUARL, SA, Établissement…
ifu                     VARCHAR(50)    NULLABLE   -- Identifiant Fiscal Unique
rccm                    VARCHAR(100)   NULLABLE   -- Registre du Commerce
address                 TEXT           NULLABLE
po_box                  VARCHAR(50)    NULLABLE
phone                   VARCHAR(50)    NULLABLE
email                   VARCHAR(255)   NULLABLE
logo                    VARCHAR(500)   NULLABLE   -- chemin storage
currency_code           VARCHAR(3)     NOT NULL   DEFAULT 'XOF'
timezone                VARCHAR(100)   NOT NULL   DEFAULT 'Africa/Abidjan'
language                VARCHAR(10)    NOT NULL   DEFAULT 'fr'
fiscal_year_start       DATE           NOT NULL   -- date de début (jour+mois utilisés)
fiscal_year_mode        VARCHAR(10)    NOT NULL   DEFAULT 'simple'    -- 'simple' | 'managed'
numbering_strategy      VARCHAR(20)    NOT NULL   DEFAULT 'yearly_reset' -- 'yearly_reset' | 'continuous'
doc_prefix_sale         VARCHAR(10)    NOT NULL   DEFAULT 'FAC'
doc_prefix_proforma     VARCHAR(10)    NOT NULL   DEFAULT 'PRO'
doc_prefix_delivery     VARCHAR(10)    NOT NULL   DEFAULT 'BL'
doc_prefix_purchase     VARCHAR(10)    NOT NULL   DEFAULT 'BC'
sequence_padding        TINYINT        NOT NULL   DEFAULT 6
default_tax_rate        DECIMAL(5,2)   NULLABLE
low_stock_threshold_mode VARCHAR(10)   NOT NULL   DEFAULT 'global'    -- 'global' | 'per_item'
low_stock_threshold     DECIMAL(15,4)  NULLABLE   -- utilisé si mode = global
barcode_scanner_enabled BOOLEAN        NOT NULL   DEFAULT TRUE
thermal_print_logo      BOOLEAN        NOT NULL   DEFAULT TRUE
import_export_enabled   BOOLEAN        NOT NULL   DEFAULT TRUE
document_header         JSON           NULLABLE   -- logo, identité, adresse pour impression
document_footer         JSON           NULLABLE   -- mentions légales, RIB, slogan
template_name           VARCHAR(50)    NOT NULL   DEFAULT 'default'
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]
```

#### `subscriptions`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
plan                    VARCHAR(50)    NOT NULL   -- 'starter' | 'pro' | 'enterprise'
status                  VARCHAR(20)    NOT NULL   -- 'active' | 'expired' | 'suspended'
expires_at              TIMESTAMP      NOT NULL
limits                  JSON           NULLABLE   -- { max_users: 5, max_warehouses: 2 }
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id)
```

#### `fiscal_years`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(100)   NOT NULL   -- "Exercice 2025"
starts_at               DATE           NOT NULL
ends_at                 DATE           NOT NULL
status                  VARCHAR(10)    NOT NULL   DEFAULT 'open'  -- 'open' | 'closed'
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id, status)
INDEX (organisation_id, starts_at, ends_at)
UNIQUE (organisation_id, name)
```

#### `document_sequences`
```
organisation_id         BIGINT         NOT NULL   FK → organisations
document_type           VARCHAR(50)    NOT NULL   -- 'sale' | 'proforma' | 'delivery_note' | 'purchase_order'
fiscal_year_id          BIGINT         NOT NULL   FK → fiscal_years
last_value              BIGINT         NOT NULL   DEFAULT 0

PRIMARY KEY (organisation_id, document_type, fiscal_year_id)
```
> Accès exclusif via `DocumentNumberingService::next()` avec `lockForUpdate()` en transaction.

#### `warehouses`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(255)   NOT NULL
address                 TEXT           NULLABLE
is_active               BOOLEAN        NOT NULL   DEFAULT TRUE
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

INDEX (organisation_id)
UNIQUE (organisation_id, name)
```

#### `permissions`
```
id                      BIGINT PK
name                    VARCHAR(100)   NOT NULL   UNIQUE  -- 'sales.create', 'stock.read'…
display_name            VARCHAR(200)   NULLABLE
permission_group        VARCHAR(50)    NULLABLE   -- 'sales' | 'stock' | 'catalogue'… pour UI
```
> Table seed-only. Jamais modifiée par tenant. Pas de `organisation_id`, pas de `BelongsToTenant`.

#### `roles`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(100)   NOT NULL   -- 'Operator', 'Lead_Operator', 'Manager', 'Admin'
is_system               BOOLEAN        NOT NULL   DEFAULT FALSE  -- TRUE = rôle par défaut seedé
hierarchy_level         TINYINT        NOT NULL   -- 1=Operator, 2=Lead_Operator, 3=Manager, 4=Admin
created_at              TIMESTAMP
updated_at              TIMESTAMP

UNIQUE (organisation_id, name)
INDEX (organisation_id, hierarchy_level)
```

#### `role_permissions`
```
role_id                 BIGINT         NOT NULL   FK → roles
permission_id           BIGINT         NOT NULL   FK → permissions

PRIMARY KEY (role_id, permission_id)
```
> Stocké à plat (pas d'héritage calculé à la volée). Lors de la personnalisation d'un rôle, on écrit les permissions effectives directement.

#### `users`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
role_id                 BIGINT         NOT NULL   FK → roles
warehouse_id            BIGINT         NULLABLE   FK → warehouses  -- Operator/Lead_Operator seulement
name                    VARCHAR(255)   NOT NULL
email                   VARCHAR(255)   NULLABLE
phone                   VARCHAR(50)    NULLABLE
email_verified_at       TIMESTAMP      NULLABLE
password                VARCHAR(255)   NOT NULL
timezone                VARCHAR(100)   NOT NULL   DEFAULT 'Africa/Abidjan'
language                VARCHAR(10)    NOT NULL   DEFAULT 'fr'
is_active               BOOLEAN        NOT NULL   DEFAULT TRUE
last_login_at           TIMESTAMP      NULLABLE
remember_token          VARCHAR(100)   NULLABLE
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

INDEX (organisation_id)
INDEX (organisation_id, role_id)
UNIQUE (organisation_id, email)   -- WHERE email IS NOT NULL
UNIQUE (organisation_id, phone)   -- WHERE phone IS NOT NULL
```
> Contrainte : au moins `email` OU `phone` non-null (enforced par FormRequest).

#### `super_admins`
```
id                      BIGINT PK
name                    VARCHAR(255)   NOT NULL
email                   VARCHAR(255)   NOT NULL   UNIQUE
password                VARCHAR(255)   NOT NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```
> Guard Laravel séparé (`super_admin`). Invisible côté tenant. Pas de `organisation_id`.

---

### 3.2 Module Catalogue

#### `item_groups`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(255)   NOT NULL
is_default              BOOLEAN        NOT NULL   DEFAULT FALSE  -- groupe "Uncategorised", undeletable
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

UNIQUE (organisation_id, name)
```

#### `unit_of_measures`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(50)    NOT NULL   -- pièce, kg, litre, carton…
abbreviation            VARCHAR(20)    NULLABLE
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

UNIQUE (organisation_id, name)
```

#### `tax_types`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(100)   NOT NULL   -- 'TVA', 'TTS'
created_at              TIMESTAMP
updated_at              TIMESTAMP

UNIQUE (organisation_id, name)
```

#### `taxes`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
tax_type_id             BIGINT         NOT NULL   FK → tax_types
name                    VARCHAR(100)   NOT NULL   -- 'TVA 18%', 'TVA 0% (exonéré)'
rate                    DECIMAL(5,2)   NOT NULL   -- 18.00
is_active               BOOLEAN        NOT NULL   DEFAULT TRUE
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id, is_active)
```

#### `items`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
item_group_id           BIGINT         NOT NULL   FK → item_groups
unit_of_measure_id      BIGINT         NULLABLE   FK → unit_of_measures
tax_id                  BIGINT         NULLABLE   FK → taxes  -- taxe par défaut de cet item
name                    VARCHAR(255)   NOT NULL
barcode                 VARCHAR(100)   NULLABLE
type                    VARCHAR(10)    NOT NULL   DEFAULT 'product'  -- 'product' | 'service'
selling_price           DECIMAL(15,4)  NOT NULL
purchase_price          DECIMAL(15,4)  NULLABLE   -- requis pour product, nullable pour service
low_stock_threshold     DECIMAL(15,4)  NULLABLE   -- utilisé si org.low_stock_threshold_mode = per_item
is_active               BOOLEAN        NOT NULL   DEFAULT TRUE
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

INDEX (organisation_id, type)
INDEX (organisation_id, item_group_id)
INDEX (organisation_id, barcode)
UNIQUE (organisation_id, barcode)  -- WHERE barcode IS NOT NULL
```

#### `item_stocks`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations  -- pour BelongsToTenant
item_id                 BIGINT         NOT NULL   FK → items
warehouse_id            BIGINT         NOT NULL   FK → warehouses
quantity                DECIMAL(15,4)  NOT NULL   DEFAULT 0
updated_at              TIMESTAMP      NOT NULL

UNIQUE (item_id, warehouse_id)
INDEX (organisation_id, warehouse_id)
INDEX (organisation_id, item_id)
```
> La quantité est toujours mise à jour atomiquement via `lockForUpdate()` ou `increment()`/`decrement()`. Jamais de lecture-modification-écriture naïve.

---

### 3.3 Module Tiers

#### `customers`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(255)   NOT NULL
phone                   VARCHAR(50)    NULLABLE
email                   VARCHAR(255)   NULLABLE
address                 TEXT           NULLABLE
tax_id                  VARCHAR(50)    NULLABLE   -- NIF/IFU
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

INDEX (organisation_id)
INDEX (organisation_id, name)
```

#### `suppliers`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
name                    VARCHAR(255)   NOT NULL
phone                   VARCHAR(50)    NULLABLE
email                   VARCHAR(255)   NULLABLE
address                 TEXT           NULLABLE
tax_id                  VARCHAR(50)    NULLABLE   -- NIF/IFU
contact_name            VARCHAR(255)   NULLABLE   -- nom du représentant
contact_phone           VARCHAR(50)    NULLABLE
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

INDEX (organisation_id)
```

---

### 3.4 Module Achats

#### `purchase_orders`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
supplier_id             BIGINT         NOT NULL   FK → suppliers
user_id                 BIGINT         NOT NULL   FK → users  -- créé par
fiscal_year_id          BIGINT         NOT NULL   FK → fiscal_years
reference               VARCHAR(100)   NOT NULL   -- auto-généré (BC-2025-000001)
date                    DATE           NOT NULL
notes                   TEXT           NULLABLE
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]

UNIQUE (organisation_id, reference)
INDEX (organisation_id, supplier_id)
INDEX (organisation_id, fiscal_year_id)
```

#### `purchase_order_lines`
```
id                      BIGINT PK
purchase_order_id       BIGINT         NOT NULL   FK → purchase_orders
item_id                 BIGINT         NOT NULL   FK → items
tax_id                  BIGINT         NULLABLE   FK → taxes
quantity                DECIMAL(15,4)  NOT NULL
unit_price              DECIMAL(15,4)  NULLABLE   -- indicatif
position                TINYINT        NOT NULL   DEFAULT 1
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (purchase_order_id)
```

---

### 3.5 Module Ventes

#### `proformas`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
warehouse_id            BIGINT         NOT NULL   FK → warehouses  -- warehouse principal / scope
customer_id             BIGINT         NULLABLE   FK → customers
user_id                 BIGINT         NOT NULL   FK → users  -- créé par
fiscal_year_id          BIGINT         NOT NULL   FK → fiscal_years
reference               VARCHAR(100)   NOT NULL   -- PRO-2025-000001
date                    DATE           NOT NULL
notes                   TEXT           NULLABLE
total_ht                DECIMAL(15,4)  NOT NULL   DEFAULT 0
total_tax               DECIMAL(15,4)  NOT NULL   DEFAULT 0
total_ttc               DECIMAL(15,4)  NOT NULL   DEFAULT 0
currency_code           VARCHAR(3)     NOT NULL   DEFAULT 'XOF'
converted_to_sale_id    BIGINT         NULLABLE   FK → sales  -- rempli à la conversion
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP      NULLABLE   [soft]  -- suppression hiérarchique autorisée

INDEX (organisation_id, warehouse_id)
INDEX (organisation_id, user_id)
INDEX (organisation_id, fiscal_year_id)
```

#### `proforma_lines`
```
id                      BIGINT PK
proforma_id             BIGINT         NOT NULL   FK → proformas
item_id                 BIGINT         NOT NULL   FK → items
warehouse_id            BIGINT         NOT NULL   FK → warehouses  -- source par ligne
tax_id                  BIGINT         NULLABLE   FK → taxes
quantity                DECIMAL(15,4)  NOT NULL
unit_price              DECIMAL(15,4)  NOT NULL
tax_amount              DECIMAL(15,4)  NOT NULL   DEFAULT 0
position                TINYINT        NOT NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (proforma_id)
```

#### `sales`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
warehouse_id            BIGINT         NOT NULL   FK → warehouses  -- warehouse principal / scope
customer_id             BIGINT         NULLABLE   FK → customers
user_id                 BIGINT         NOT NULL   FK → users  -- caissier
fiscal_year_id          BIGINT         NOT NULL   FK → fiscal_years
proforma_id             BIGINT         NULLABLE   FK → proformas  -- source si converti
reference               VARCHAR(100)   NOT NULL   -- FAC-2025-000001
date                    DATE           NOT NULL
total_ht                DECIMAL(15,4)  NOT NULL   DEFAULT 0
total_tax               DECIMAL(15,4)  NOT NULL   DEFAULT 0
total_ttc               DECIMAL(15,4)  NOT NULL   DEFAULT 0
currency_code           VARCHAR(3)     NOT NULL   DEFAULT 'XOF'
validated_at            TIMESTAMP      NOT NULL   -- une Sale est toujours validée à la création
created_at              TIMESTAMP
updated_at              TIMESTAMP
-- PAS de deleted_at : Sale irréversible
```
> `validated_at` est rempli à la création — il n'y a pas de Sale en état "draft". La validation déclenche le décrément de stock via Observer.

```
UNIQUE (organisation_id, reference)
INDEX (organisation_id, warehouse_id)
INDEX (organisation_id, user_id)
INDEX (organisation_id, date)
INDEX (organisation_id, fiscal_year_id)
INDEX (organisation_id, customer_id)
```

#### `sale_lines`
```
id                      BIGINT PK
sale_id                 BIGINT         NOT NULL   FK → sales
item_id                 BIGINT         NOT NULL   FK → items
warehouse_id            BIGINT         NOT NULL   FK → warehouses  -- warehouse source pour décrément stock
tax_id                  BIGINT         NULLABLE   FK → taxes
quantity                DECIMAL(15,4)  NOT NULL
unit_price              DECIMAL(15,4)  NOT NULL
tax_amount              DECIMAL(15,4)  NOT NULL   DEFAULT 0
position                TINYINT        NOT NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (sale_id)
INDEX (sale_id, item_id)
```

#### `invoices`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
sale_id                 BIGINT         NOT NULL   UNIQUE FK → sales  -- 1:1
reference               VARCHAR(100)   NOT NULL
issued_at               TIMESTAMP      NOT NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id)
```
> L'Invoice reprend toutes les données de la Sale et ses lignes. Pas de colonnes dupliquées ici — le PDF est généré à la volée depuis `sales` + `sale_lines`.

#### `delivery_notes`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
sale_id                 BIGINT         NOT NULL   UNIQUE FK → sales  -- 1:1 (formal sales only)
reference               VARCHAR(100)   NOT NULL
issued_at               TIMESTAMP      NOT NULL
signed_at               TIMESTAMP      NULLABLE   -- date de signature client
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id)
```

---

### 3.6 Module Stock

#### `stock_entries`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
warehouse_id            BIGINT         NOT NULL   FK → warehouses  -- warehouse source
user_id                 BIGINT         NOT NULL   FK → users
fiscal_year_id          BIGINT         NOT NULL   FK → fiscal_years
type                    VARCHAR(20)    NOT NULL   -- 'material_receipt' | 'material_issue' | 'material_transfer'
to_warehouse_id         BIGINT         NULLABLE   FK → warehouses  -- uniquement pour material_transfer
reference               VARCHAR(100)   NOT NULL
date                    DATE           NOT NULL
notes                   TEXT           NULLABLE   -- obligatoire pour retour client (receipt)
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id, warehouse_id)
INDEX (organisation_id, type)
INDEX (organisation_id, date)
INDEX (organisation_id, fiscal_year_id)
```

#### `stock_entry_lines`
```
id                      BIGINT PK
stock_entry_id          BIGINT         NOT NULL   FK → stock_entries
item_id                 BIGINT         NOT NULL   FK → items
quantity                DECIMAL(15,4)  NOT NULL
unit_cost               DECIMAL(15,4)  NULLABLE   -- coût unitaire à la réception
position                TINYINT        NOT NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (stock_entry_id)
INDEX (stock_entry_id, item_id)
```

#### `stock_reconciliations`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
warehouse_id            BIGINT         NOT NULL   FK → warehouses
user_id                 BIGINT         NOT NULL   FK → users  -- créé par
fiscal_year_id          BIGINT         NOT NULL   FK → fiscal_years
description             TEXT           NOT NULL   -- "Réconciliation après inventaire du 30 Mai 2026"
status                  VARCHAR(10)    NOT NULL   DEFAULT 'draft'  -- 'draft' | 'validated'
validated_at            TIMESTAMP      NULLABLE
validated_by            BIGINT         NULLABLE   FK → users
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id, warehouse_id)
INDEX (organisation_id, status)
INDEX (organisation_id, fiscal_year_id)
```

#### `stock_reconciliation_lines`
```
id                      BIGINT PK
stock_reconciliation_id BIGINT         NOT NULL   FK → stock_reconciliations
item_id                 BIGINT         NOT NULL   FK → items
counted_quantity        DECIMAL(15,4)  NOT NULL   -- quantité comptée physiquement
system_quantity_before  DECIMAL(15,4)  NULLABLE   -- capturé au moment de la validation
delta                   DECIMAL(15,4)  NULLABLE   -- calculé à la validation (+/-)
position                TINYINT        NOT NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (stock_reconciliation_id)
```

---

### 3.7 Alertes de stock

#### `stock_alerts`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
item_id                 BIGINT         NOT NULL   FK → items
warehouse_id            BIGINT         NOT NULL   FK → warehouses
current_quantity        DECIMAL(15,4)  NOT NULL
threshold               DECIMAL(15,4)  NOT NULL
status                  VARCHAR(10)    NOT NULL   DEFAULT 'unread'  -- 'unread' | 'read'
triggered_at            TIMESTAMP      NOT NULL
read_at                 TIMESTAMP      NULLABLE
read_by                 BIGINT         NULLABLE   FK → users

INDEX (organisation_id, status)
INDEX (organisation_id, warehouse_id, status)
```

---

### 3.8 Module Import/Export

#### `import_jobs`
```
id                      CHAR(36) PK    -- UUID
organisation_id         BIGINT         NOT NULL   FK → organisations
user_id                 BIGINT         NOT NULL   FK → users
entity_type             VARCHAR(50)    NOT NULL   -- 'item' | 'customer' | 'supplier' | 'item_group' | 'unit_of_measure'
status                  VARCHAR(15)    NOT NULL   DEFAULT 'pending'  -- 'pending' | 'processing' | 'completed' | 'failed'
file_path               VARCHAR(500)   NOT NULL
total_rows              INT            NULLABLE
processed_rows          INT            NOT NULL   DEFAULT 0
failed_rows             INT            NOT NULL   DEFAULT 0
errors                  JSON           NULLABLE   -- [{ row: 3, column: 'name', message: '...' }]
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX (organisation_id, status)
```

---

### 3.9 Module Audit

#### `activity_logs`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
user_id                 BIGINT         NULLABLE   FK → users  -- null si action système
subject_type            VARCHAR(100)   NOT NULL   -- 'App\Modules\Ventes\Models\Sale'
subject_id              BIGINT         NOT NULL
action                  VARCHAR(20)    NOT NULL   -- 'created' | 'updated' | 'deleted'
before                  JSON           NULLABLE
after                   JSON           NULLABLE
ip_address              VARCHAR(45)    NULLABLE
created_at              TIMESTAMP      NOT NULL   -- immutable

INDEX (organisation_id, subject_type, subject_id)
INDEX (organisation_id, user_id)
INDEX (organisation_id, created_at)
```

#### `sales_logs`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
user_id                 BIGINT         NOT NULL   FK → users
sale_id                 BIGINT         NOT NULL   FK → sales
amount                  DECIMAL(15,4)  NOT NULL
created_at              TIMESTAMP      NOT NULL   -- immutable

INDEX (organisation_id, user_id)
INDEX (organisation_id, sale_id)
INDEX (organisation_id, created_at)
```

#### `permission_logs`
```
id                      BIGINT PK
organisation_id         BIGINT         NOT NULL   FK → organisations
changed_by              BIGINT         NOT NULL   FK → users
target_user_id          BIGINT         NOT NULL   FK → users
action                  VARCHAR(100)   NOT NULL   -- 'role_changed', 'permission_added'…
before                  JSON           NULLABLE
after                   JSON           NULLABLE
created_at              TIMESTAMP      NOT NULL   -- immutable

INDEX (organisation_id, target_user_id)
INDEX (organisation_id, changed_by)
```

#### `access_logs`
```
id                      BIGINT PK
organisation_id         BIGINT         NULLABLE   FK → organisations  -- null si login échoué hors tenant
user_id                 BIGINT         NULLABLE   FK → users
action                  VARCHAR(20)    NOT NULL   -- 'login' | 'logout' | 'session_expired' | 'failed_login'
ip_address              VARCHAR(45)    NULLABLE
user_agent              VARCHAR(500)   NULLABLE
created_at              TIMESTAMP      NOT NULL   -- immutable

INDEX (organisation_id, user_id)
INDEX (organisation_id, action, created_at)
```

---

## 4. Architecture Laravel — `app/Modules/`

```
app/
└── Modules/
    ├── Core/
    │   ├── Actions/
    │   │   ├── CreateOrganisationAction.php
    │   │   └── GenerateDocumentNumberAction.php
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   │   ├── AuthController.php
    │   │   │   ├── OrganisationController.php
    │   │   │   ├── UserController.php
    │   │   │   ├── RoleController.php
    │   │   │   └── WarehouseController.php
    │   │   ├── Middleware/
    │   │   │   ├── SetTenantContext.php    -- lit session tenant_id → injecte dans BelongsToTenant
    │   │   │   └── CheckFiscalYear.php     -- vérifie FiscalYear.status = 'open' sur toute écriture financière
    │   │   └── Requests/
    │   ├── Models/
    │   │   ├── Organisation.php
    │   │   ├── User.php
    │   │   ├── Role.php
    │   │   ├── Permission.php
    │   │   ├── Warehouse.php
    │   │   ├── FiscalYear.php
    │   │   ├── DocumentSequence.php
    │   │   └── SuperAdmin.php
    │   ├── Resources/                      -- API Resources (DTOs)
    │   ├── Services/
    │   │   └── DocumentNumberingService.php
    │   └── Traits/
    │       └── BelongsToTenant.php         -- Global Scope injecté sur tous les modèles scopés
    │
    ├── Catalogue/
    │   ├── Actions/
    │   ├── Http/Controllers/
    │   │   ├── ItemController.php
    │   │   ├── ItemGroupController.php
    │   │   ├── UnitOfMeasureController.php
    │   │   └── TaxController.php
    │   ├── Models/
    │   │   ├── Item.php
    │   │   ├── ItemGroup.php
    │   │   ├── ItemStock.php
    │   │   ├── UnitOfMeasure.php
    │   │   ├── Tax.php
    │   │   └── TaxType.php
    │   └── Resources/
    │
    ├── Tiers/
    │   ├── Http/Controllers/
    │   │   ├── CustomerController.php
    │   │   └── SupplierController.php
    │   ├── Models/
    │   │   ├── Customer.php
    │   │   └── Supplier.php
    │   └── Resources/
    │
    ├── Ventes/
    │   ├── Actions/
    │   │   ├── CreateSaleAction.php        -- déclenche stock + invoice + delivery_note
    │   │   ├── ConvertProformaAction.php
    │   │   └── DeleteProformaAction.php    -- vérifie hiérarchie
    │   ├── Http/Controllers/
    │   │   ├── SaleController.php
    │   │   ├── ProformaController.php
    │   │   ├── InvoiceController.php
    │   │   └── DeliveryNoteController.php
    │   ├── Models/
    │   │   ├── Sale.php
    │   │   ├── SaleLine.php
    │   │   ├── Proforma.php
    │   │   ├── ProformaLine.php
    │   │   ├── Invoice.php
    │   │   └── DeliveryNote.php
    │   ├── Observers/
    │   │   └── SaleObserver.php            -- décrémente Item_Stock à la validation
    │   └── Resources/
    │
    ├── Achats/
    │   ├── Http/Controllers/
    │   │   └── PurchaseOrderController.php
    │   ├── Models/
    │   │   ├── PurchaseOrder.php
    │   │   └── PurchaseOrderLine.php
    │   └── Resources/
    │
    ├── Stock/
    │   ├── Actions/
    │   │   ├── CreateStockEntryAction.php
    │   │   └── ValidateReconciliationAction.php  -- lockForUpdate() sur Item_Stock
    │   ├── Http/Controllers/
    │   │   ├── StockEntryController.php
    │   │   └── StockReconciliationController.php
    │   ├── Models/
    │   │   ├── StockEntry.php
    │   │   ├── StockEntryLine.php
    │   │   ├── StockReconciliation.php
    │   │   └── StockReconciliationLine.php
    │   └── Resources/
    │
    ├── Analytics/
    │   ├── Http/Controllers/
    │   │   └── ReportController.php
    │   └── Services/
    │       └── ReportQueryService.php      -- lectures SQL optimisées, pas de modèles propres
    │
    ├── ImportExport/
    │   ├── Actions/
    │   │   ├── ImportAction.php            -- dispatché en queue
    │   │   └── ExportAction.php
    │   ├── Http/Controllers/
    │   │   └── ImportExportController.php
    │   ├── Jobs/
    │   │   └── ProcessImportJob.php
    │   └── Models/
    │       └── ImportJob.php
    │
    └── Audit/
        ├── Listeners/                      -- écoutent les events de tous les modules
        │   ├── LogActivityListener.php
        │   └── LogAccessListener.php
        └── Models/
            ├── ActivityLog.php
            ├── SalesLog.php
            ├── PermissionLog.php
            └── AccessLog.php
```

**Routes** : chaque module expose ses routes dans `routes/api.php` sous `/api/v1/`. Les modules peuvent avoir leur propier fichier `routes/modules/{module}.php` inclus depuis `app/Providers/RouteServiceProvider`.

---

## 5. Flux critiques

### 5.1 Multi-tenancy — cycle d'une requête

```
Request → SetTenantContext middleware
  → lit user.organisation_id depuis session/token
  → stocke dans app('tenant_id')
  → BelongsToTenant GlobalScope lit app('tenant_id')
  → toute requête Eloquent filtre automatiquement WHERE organisation_id = ?
```

Tests : chaque feature test crée son propre tenant. Un test `cross-tenant` vérifie qu'une ressource d'un tenant retourne 404 depuis un autre tenant.

### 5.2 FiscalYear guard

```
CheckFiscalYear middleware (routes financières POST/PUT/DELETE)
  → résout le fiscal_year actif pour l'organisation
  → si status = 'closed' → retourne 422 FISCAL_YEAR_CLOSED
  → si mode = 'simple' → auto-crée le FiscalYear si inexistant
```

### 5.3 Génération de numéro de document

```
DocumentNumberingService::next($organisationId, $type, $fiscalYearId)
  → BEGIN TRANSACTION
  → SELECT ... FOR UPDATE sur document_sequences
  → INCREMENT last_value
  → FORMAT : {prefix}-{year}-{padded_value}  (ex: FAC-2025-000042)
  → COMMIT
```

### 5.4 Validation d'une Sale (décrément stock)

```
CreateSaleAction::execute($dto)
  → BEGIN TRANSACTION
  → Créer Sale + SaleLines
  → Pour chaque SaleLine :
      → SELECT quantity FROM item_stocks WHERE item_id=? AND warehouse_id=? FOR UPDATE
      → Si quantity < requested_quantity → ROLLBACK → INSUFFICIENT_STOCK
      → UPDATE item_stocks SET quantity = quantity - requested_quantity
  → Créer Invoice (auto)
  → Créer DeliveryNote si formal sale
  → GenerateDocumentNumberAction × 1-2
  → Émettre SaleValidated event → SalesLog listener
  → COMMIT
  → Déclencher CheckLowStockJob (queue, hors transaction)
```

### 5.5 Validation d'une StockReconciliation

```
ValidateReconciliationAction::execute($reconciliation)
  → Vérifier status = 'draft'
  → BEGIN TRANSACTION
  → Pour chaque ligne :
      → SELECT quantity FROM item_stocks WHERE item_id=? AND warehouse_id=? FOR UPDATE
      → Enregistrer system_quantity_before
      → Calculer delta = counted_quantity - system_quantity_before
      → UPDATE item_stocks SET quantity = counted_quantity
      → Enregistrer delta sur la ligne
  → UPDATE stock_reconciliations SET status = 'validated', validated_at = now()
  → COMMIT
```

### 5.6 Warehouse scoping (Operator / Lead_Operator)

```
Toute requête de liste (Sale, Proforma, StockEntry, Item_Stock)
  → Si user.role.hierarchy_level <= 2 (Operator / Lead_Operator)
    → scope additionnel WHERE warehouse_id = user.warehouse_id
  → Si user.role.hierarchy_level >= 3 (Manager / Admin)
    → pas de filtre warehouse (visibilité totale)
```

Implémenté via un second Global Scope `BelongsToWarehouse` conditionnel, appliqué sur les modèles concernés, ou via un helper `warehouseScopeForUser($user)` appelé dans les controllers.

---

## 6. Décisions de schéma notables

| Décision | Raison |
|---|---|
| `DECIMAL(15,4)` pour tous les montants | Pas d'arrondi flottant. Futur multi-devise sans migration. |
| `VARCHAR` + contrainte CHECK pour les enums | Plus facile à migrer qu'`ENUM` MySQL natif. |
| Pas de `deleted_at` sur `sales` | Sale irréversible par design. |
| `sale_lines.warehouse_id` par ligne | Manager peut mixer warehouses sur une même Sale. |
| `document_sequences` avec PK composite | `lockForUpdate()` précis sur la ligne exacte, pas de gap lock. |
| Tables `_lines` sans `organisation_id` | Évite la redondance — isolation garantie par FK → header scopé. |
| `super_admins` table séparée | Guard Laravel distinct, jamais mélangé au système tenant. |
| `item_stocks.organisation_id` redondant | Nécessaire pour BelongsToTenant sans JOIN — trade-off assumé. |
| `proforma.converted_to_sale_id` nullable | Traçabilité de la conversion sans table pivot. |

---

*Généré lors de la Session 4 — à maintenir si le schéma évolue.*

# INSTRUCTIONS SYSTÈME POUR L'IA — DÉVELOPPEMENT MODULAIRE MODERNE

---

## 0. PRÉ-REQUIS ABSOLUS : Références et Docs Officielles

Avant de générer la moindre ligne de code, tu dois **obligatoirement** :

**Lire les fichiers locaux :**

- `context.md` — règles métiers, architecture, vocabulaire du domaine, multi-tenancy
- `handoff.md` (généré par `/handoff`) — état d'avancement, décisions verrouillées (ADRs), modules en cours

Le code produit doit être **100% conforme** à ces deux fichiers. Si une décision y est verrouillée, ne la re-discute pas.

Si `handoff.md` n'existe pas → c'est la première session du projet. Ne pas bloquer : commencer par l'Étape 0 directement.

**Cycle de vie de `context.md` :**

- Créé en début de projet via une session guidée `/grill-with-docs` (l'agent propose, tu valides et commites)
- **Seul le propriétaire du projet peut modifier `context.md`** — l'agent propose des ajouts inline pendant les sessions, jamais en autonomie
- Quand un nouveau concept ou une règle métier émerge pendant le développement, l'agent propose immédiatement le libellé exact à ajouter — tu approuves ou corriges, puis tu commites
- `context.md` contient le vocabulaire et les règles métier. Les ADRs (`docs/adr/`) contiennent les décisions techniques. Les deux se référencent sans se dupliquer.

**Flux `handoff.md` — flux circulaire obligatoire :**

```
DÉBUT DE SESSION
└── Lire context.md + handoff.md → reprendre exactement où on s'est arrêtés

... travail en cours ...

INVOQUER /handoff dès que l'une de ces conditions est vraie :
  ├── Fin de session normale
  ├── Fin d'un module complet
  ├── Contexte de conversation proche de la limite (~75% estimé)
  └── Avant tout changement de sujet majeur

/handoff produit ou met à jour :
  ├── handoff.md        — état exact, prochaine étape précise
  ├── context.md        — proposition d'ajout si nouveau concept (jamais en autonomie)
  └── docs/adr/*.md     — tout ADR généré pendant la session

PROCHAINE SESSION
└── L'agent lit ces fichiers et reprend sans que tu aies à réexpliquer quoi que ce soit
```

**Consulter les documentations officielles :**
Référer-toi aux guides officiels de la stack sélectionnée (Laravel, Vue.js, Elysia, Next.js, Pest, Vitest, Tailwind, etc.). Les patterns recommandés par les créateurs du framework ont priorité absolue.

---

## 1. RÈGLE D'OR : Explication et Validation Avant Tout

Avant d'écrire ou de modifier un fichier, fournis une explication concise en 4 points :

- **Quoi** : Ce qui va être codé (classes, fichiers, composants)
- **Pourquoi** : La raison technique ou d'architecture (cite la convention officielle, `context.md` ou `handoff.md`)
- **Avantages** : Ce que cela apporte immédiatement à l'application
- **Futur** : En quoi cela servira l'évolution du projet

**Tu attends ma validation explicite avant de générer le code.** Pas de "voici ce que je propose, je vais le coder" sans "OK" de ma part.

---

## 2. STANDARDS DE CODE & DOCUMENTATION

### Commentaires

Chaque classe, interface et méthode complexe a un **Docblock complet** (PHPDoc pour Laravel, TSDoc/JSDoc pour TypeScript). Les commentaires inline expliquent le **pourquoi** de la logique, jamais le comment.

### Format des erreurs API

```json
{
  "success": false,
  "error": "CODE_ERREUR_MAJUSCULE",
  "message": "Message clair en anglais (traduit en français côté front)."
}
```

### Format des réponses de succès API

```json
// Ressource simple (GET /resource/1, POST /resource, PUT /resource/1)
{ "success": true, "data": { ... } }

// Liste paginée (GET /resource)
{ "success": true, "data": [ ... ], "meta": { "page": 1, "per_page": 20, "total": 340 } }

// Action asynchrone / job en queue (POST /exports)
{ "success": true, "data": { "job_id": "abc-123" }, "message": "export.queued" }
```

Note : le champ `message` sur les actions async contient une **clé i18n**, jamais une phrase traduite. Le front traduit.

### Pagination — OBLIGATOIRE

- **Toute route qui retourne une liste doit paginer**, quelle que soit la taille estimée des données aujourd'hui
- Stratégie par défaut : **offset** (`page` + `per_page`). La logique est isolée derrière une abstraction (`PaginationService` / helper `paginate()`) pour permettre une migration vers curseur sans réécriture si un module le nécessite
- Valeurs par défaut : `per_page = 20`, maximum autorisé : `100`. Au-delà → erreur `PAGINATION_LIMIT_EXCEEDED`
- Le `meta` dans la réponse reste compatible avec les deux stratégies (offset et curseur)

### Prévention N+1 — OBLIGATOIRE

- **Règle primaire (à l'écriture)** : toute relation utilisée dans une Resource/DTO doit être eager-loadée. Interdit de charger une relation dans une boucle
- **Laravel** : `with()` systématique sur toutes les requêtes qui sérialisent des relations
- **TS/Prisma** : `include` systématique dans les queries qui sérialisent des relations
- **Règle secondaire (filet de sécurité)** : Debugbar + Telescope installés en local uniquement — ils signalent ce qui aurait dû être catchés à l'écriture
- `/engineering:code-review` vérifie les deux niveaux

### Dates & Timezones — OBLIGATOIRE

- **Base de données** : toutes les dates stockées en **UTC**, sans exception
- **Serveur** : tourne en UTC (`APP_TIMEZONE=UTC` Laravel, `TZ=UTC` Node)
- **API** : dates retournées en **ISO 8601 UTC** (`2024-01-15T14:30:00Z`). Jamais de date formatée côté back
- **Front-end** : responsable de toute conversion et affichage localisé selon le timezone de l'utilisateur
- **Timezone utilisateur** : propriété obligatoire du profil (IANA Timezone Database — ex: `Africa/Abidjan`, `Europe/Paris`). Jamais d'abréviations ambiguës (`CET`, `EST`)
- **Tenant** : champ `timezone` obligatoire à la création, valeur par défaut définie dans `context.md`
- **Règles de scheduling** : stocker l'intention locale, pas le timestamp UTC :

```json
{ "time_local": "08:00", "timezone": "Europe/Paris", "recurrence": "daily" }
```

Le scheduler calcule le prochain UTC à l'exécution — absorbe automatiquement les changements DST

### Format des données

Utilise systématiquement des **DTOs ou API Resources** pour formater les réponses vers le front-end (ex : forcer 0 décimale pour la devise XOF).

### Règle anti-troncation

Ne réécris **jamais** un fichier existant dans son intégralité si la modification est locale. Ne remplace jamais du code valide par `// ... reste inchangé`. Fournis des blocs ciblés ou un DIFF clair avec numéros de lignes.

### Internationalisation (i18n) — OBLIGATOIRE

- **Toutes les chaînes utilisateur** (messages API, labels UI, erreurs, notifications) doivent passer par le système i18n.
- Locales minimales : `en` (défaut) + `fr`.
- **Back-end Laravel** : fichiers `lang/en/` et `lang/fr/`. Jamais de chaînes hardcodées dans les réponses API.
- **Back-end TS** : `i18next` configuré avec namespace par module.
- **Front-end Vue** : `vue-i18n` avec fichiers `locales/en.json` et `locales/fr.json`.
- **Front-end Next.js** : `next-intl` avec `messages/en.json` et `messages/fr.json`.
- Les clés de traduction suivent le pattern `module.contexte.clé` (ex : `auth.errors.invalid_credentials`).

### Code Style — OBLIGATOIRE

- **Laravel** : Laravel Pint (PSR-12) + PHPStan niveau 6 minimum.
- **TypeScript** : ESLint + Prettier, config stricte (`strict: true` dans `tsconfig.json`).
- **Pre-commit hooks** : Husky + lint-staged. Aucun commit ne passe sans style clean.
- Ces outils doivent être configurés **au démarrage du projet**, pas ajoutés après.

### Documentation générée

Pour les README, docs API, runbooks → invoquer `/engineering:documentation`.

---

## 3. SÉCURITÉ — STANDARDS NON-NÉGOCIABLES

Ces règles s'appliquent à **chaque module** qui expose des routes HTTP :

- **API Versioning** : toutes les routes sous `/api/v1/`. Un changement cassant = une nouvelle version.
- **Rate limiting** : configurer sur toutes les routes publiques et authentifiées (seuils différenciés).
- **CORS** : whitelist explicite des origines autorisées. Jamais `*` en production.
- **CSP Headers** : Content Security Policy défini en production.
- **Tokens** : expiration courte (access token ≤ 15 min), refresh token rotation à chaque usage, révocation sur déconnexion.
- **Inputs** : toujours sanitisés par les FormRequests (Laravel) ou les schémas Zod/TypeBox (TS). Jamais de requête SQL construite à la main.
- **Secrets** : `.env.example` maintenu à jour, `.env` jamais committé, secrets gérés via variables d'environnement (pas de fallback codé en dur).

---

## 4. WORKFLOW GIT

- **Branches** : `feature/nom-module`, `fix/description-bug`, `refactor/description`
- **Commits** : Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`)
- **PRs** : tests CI verts obligatoires avant merge. Minimum 1 review.
- **CI/CD** : pipeline qui tourne les tests à chaque push sur toutes les branches. Aucun déploiement sans CI vert.
- Avant tout déploiement vers staging ou production → invoquer `/engineering:deploy-checklist`.

---

## 5. MONITORING & LOGS

- **Logs structurés** : format JSON en production. Niveaux : `DEBUG` (local seulement), `INFO`, `WARNING`, `ERROR`.
- **Ne jamais loguer** : données personnelles (PII), mots de passe, tokens, numéros de carte.
- **Error monitoring** : Sentry (ou équivalent) configuré dès le module Core. Chaque exception non-gérée doit remonter.
- En cas de bug ou comportement inattendu pendant le développement → invoquer `/engineering:debug`.

---

## 6. ALGORITHME D'EXÉCUTION PAR MODULE

---

### ÉTAPE 0 — Module "Core / Authentification" (À faire EN PREMIER)

C'est le socle sur lequel tout repose. Rien d'autre ne commence avant que cette étape soit complète et validée.

**6.0.1 — Design système global**
Invoquer `/engineering:system-design` pour produire le document de design :

- Architecture globale (composants, data flow, API contracts)
- Modèle de données global (Organisations/Tenants, Users, Roles, Permissions)
- Stratégie de scalabilité et de résilience

**6.0.2 — Décisions d'architecture (ADRs)**
Pour chaque décision structurante (choix d'ORM, stratégie de multi-tenancy, système d'auth) → invoquer `/engineering:architecture`.
Les ADRs sont versionnés dans `docs/adr/` et référencés dans `context.md`.

**6.0.3 — Socle à construire (code)**

- Structure de la base de données globale (migrations Tenants, Users, Roles, Permissions)
- Injection automatique du `tenant_id` sur **tous** les modèles :
  - Laravel : Trait `BelongsToTenant` + `GlobalScope` Eloquent
  - TypeScript : middleware Prisma/Drizzle de filtrage ORM
- Système centralisé d'audit (`activity_logs`) : qui a fait quoi, quand, sur quel tenant
- **Champ `timezone` obligatoire** sur la table `tenants`, avec valeur par défaut définie dans `context.md`
- **Queue driver** : configurer Redis (ou Upstash Redis en low-cost) comme driver de queue. Driver `database` acceptable en phase initiale — l'abstraction doit permettre un upgrade sans réécriture de code
- **Outils N+1 (local uniquement)** : Debugbar + Telescope (Laravel) ou `log: ['query']` Prisma (TS)
- Setup i18n back-end + front-end (voir Section 2)
- Setup code style enforcement (Pint/PHPStan ou ESLint/Prettier + Husky)
- Configuration Sentry (ou équivalent)
- Squelette CI/CD
- **`PRODUCT.md` + `DESIGN.md`** : créer via `/impeccable init`. Définir et valider l'identité visuelle du projet (palette, typo, composants) **une fois pour toutes** avant la Phase B du premier module. Une fois validée, verrouillée pour tous les modules suivants.

**Stress-test du plan initial** : avant de coder, invoquer `/grill-me` sur l'architecture de l'Étape 0 pour identifier les angles morts.

---

### PHASE A — BACK-END (pour chaque module métier)

#### A.1 — Plan de Tests

Invoquer `/engineering:testing-strategy` pour définir :

- Quels comportements tester (chemin critique, edge cases, sécurité)
- Quelle couche par zone (unitaire, feature/intégration, contrat)
- Les cibles de couverture

#### A.2 — Contrats & Interfaces Cross-Module

Si le module interagit avec un module existant, définir les interfaces formellement.
Pour les décisions de contrat structurantes → invoquer `/engineering:architecture` (ADR).

#### A.3 — Persistance (DB)

- Migrations avec **index explicites** : clé étrangère + colonnes fréquemment requêtées + index composites sur `(tenant_id, ...)` pour toutes les tables multi-tenant
- **Migrations additives uniquement** : nouvelles colonnes ajoutées en `nullable()` par défaut. Jamais de `NOT NULL` sans valeur par défaut sur une table existante
- Chaque migration a un `down()` symétrique au `up()` — ni plus, ni moins
- Les suppressions de colonnes = migration séparée, déploiement suivant (jamais dans la même migration que l'ajout)
- `migrate:fresh --seed` doit tourner sans erreur avant tout commit de migration
- Modèles avec injection automatique `tenant_id` (Trait ou middleware)
- Factories réalistes pour les tests
- **Seeders de démo** : peuplement instantané et réaliste en environnement local

**Politique Soft Delete / DeletionGuard :**

- Soft delete vs hard delete = décision par entité, documentée dans `context.md` au cas par cas
- **Toute suppression passe obligatoirement par une `DeletionGuard`** (service ou action dédié) qui vérifie les dépendances avant d'autoriser l'opération. Si la ressource est référencée ailleurs → erreur métier claire, pas un crash DB
- Les index d'unicité sur les tables avec soft delete doivent inclure `deleted_at` pour éviter les conflits à la recréation
- **Anonymisation RGPD** : les entités utilisateur sont conçues pour permettre l'anonymisation des PII (remplacement par valeurs neutres) en lieu et place d'une suppression — préserve l'historique d'audit tout en respectant les obligations légales

**Jobs & Queues :**

- Toute opération > 200ms estimée ou dépendant d'un service externe → job en queue, jamais inline dans le controller
- Chaque job est co-localisé dans le module qui le concerne
- `failed_jobs` loggés dans Sentry, retry policy : 3 tentatives max, backoff exponentiel
- Pour les opérations critiques où l'utilisateur attend un résultat → polling léger (front re-demande le statut) par défaut, WebSockets uniquement si le besoin est avéré

#### A.4 — Cycle TDD (Red → Green → Refactor)

Invoquer `/tdd` pour exécuter le cycle complet :

- **Red** : un test → il échoue
- **Green** : code minimal pour le passer
- Répéter par tranches verticales (behavior par behavior)
- **Jamais** d'écriture en masse de tous les tests avant l'implémentation

**Isolation des données de test — OBLIGATOIRE :**

- Chaque test crée **son propre tenant + ses propres utilisateurs** via les factories. Jamais de dépendance aux seeders ou à un état partagé entre tests
- **Laravel** : trait `RefreshDatabase` ou `DatabaseTransactions` sur chaque test feature — la base repart propre après chaque test
- **TS/Vitest** : `beforeEach` qui recrée un tenant isolé, `afterEach` qui purge
- Jamais de `tenant_id` hardcodé dans un test — toujours généré dynamiquement par la factory

**Test d'isolation cross-tenant (Module Core — obligatoire) :**
Un test dédié vérifie qu'un utilisateur d'un tenant ne peut pas voir les données d'un autre tenant. Le résultat attendu est **404** (la ressource est invisible), jamais 403 (qui confirmerait son existence) :

```php
it('un tenant ne voit pas les données d\'un autre tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $resource = Resource::factory()->for($tenantA)->create();

    actingAs(User::factory()->for($tenantB)->create())
        ->getJson("/api/v1/resources/{$resource->id}")
        ->assertStatus(404);
});
```

Ce test tourne à chaque CI.

#### A.5 — Exposition HTTP

- FormRequests (Laravel) ou schémas Zod/TypeBox (TS) pour toute validation d'input
- Controllers légers : délèguent aux Actions/Services
- Routes sécurisées par permissions (Spatie/Laravel-Permission ou équivalent)
- Toutes les routes sous `/api/v1/`
- Rate limiting configuré

#### A.6 — Documentation API

Invoquer `/engineering:documentation` pour générer/mettre à jour la doc OpenAPI du module.

#### A.7 — Code Review

Invoquer `/engineering:code-review` sur le code produit. Bloquer sur :

- Requêtes N+1
- Failles de sécurité (injection, auth bypass, exposition de données)
- Violations de la règle d'isolation tenant
- Chaînes non-traduites dans les réponses API

---

### PHASE B — FRONT-END (une fois le back-end du module validé à 100%)

#### B.1 — Design & UX (Shape)

Invoquer `/impeccable shape [module]` :

- Grilling UX interactif (questions sur l'ergonomie, disposition, comportements)
- Proposition de structure détaillée en texte
- Ajustements jusqu'à validation explicite de ta part ("OK")

Les maquettes ou visuels que tu fournis sont intégrés à cette étape.

#### B.2 — Codage complet (Craft)

Invoquer `/impeccable craft [module]` pour produire le code complet.

**En complément du skill, ces règles métier spécifiques au projet doivent être appliquées :**

- **Pinia stores** (Vue) ou **Zustand/Context** (Next.js) pour toute la gestion d'état globale
- **Affichage piloté à 100% par les permissions back-end** : chaque bouton, tab, ou section est conditionné par les droits de l'utilisateur courant. Aucun élément d'UI n'est caché côté client seulement.
- Les **widgets du dashboard** correspondent exactement aux données servies par les API Resources du module
- **Comportement adaptatif** (ex : scanner de code-barres) : la logique de détection du device et du mode d'entrée est gérée dans le store, pas dans les composants

#### B.3 — Animations & Motion

Pour toute décision de motion design pendant B.2 → consulter `/emil-design-eng`.
Après implémentation des animations → invoquer `/review-animations` pour validation.

#### B.4 — Harden & Audit

- Invoquer `/impeccable harden [module]` : i18n, états vides, états d'erreur, edge cases
- Invoquer `/impeccable audit [module]` : accessibilité (WCAG 2.1 AA), performance, responsive
  - **Core Web Vitals cibles** : LCP < 2.5s, CLS < 0.1, INP < 200ms
  - Code splitting : le code de chaque module est chargé en lazy par défaut

#### B.5 — Tests Front

Tests unitaires/composants sur :

- Logique des stores (calculs, mutations, side effects)
- Affichage conditionnel des éléments selon les permissions (tester plusieurs rôles)
- Comportements des formulaires (validation, états de chargement, erreurs)

---

## 7. REVUES PÉRIODIQUES (entre modules)

Ces actions ne sont pas liées à un module précis, mais se font à intervalles réguliers :

**Déclenchement de `/handoff` — conditions :**

```
├── Fin de session normale
├── Fin d'un module complet
├── Contexte de conversation proche de la limite (~75% estimé)
└── Avant tout changement de sujet majeur
```

Le handoff doit être assez précis pour qu'une nouvelle conversation reprenne **sans que tu aies à réexpliquer quoi que ce soit**.

**Après 3 modules ou lors d'une friction ressentie :**

- Invoquer `/improve-codebase-architecture` : chercher les modules trop superficiels, les couplages non-voulus, les opportunités de refactoring guidées par `context.md` et les ADRs
- Invoquer `/engineering:tech-debt` : classifier et prioriser la dette technique accumulée

**En cas d'incident en production :**

- Invoquer `/engineering:incident-response` : triage, communication, postmortem blameless

---

## 8. VARIATIONS DE STACK TECHNIQUE

### Stack PHP / Laravel / Vue.js

**Back-end :**

- Architecture en modules/domaines isolés (co-location)
- Tests : Pest/PHPUnit
- Validation : FormRequests
- Logique métier : Actions + Services + Observers
- API : Laravel API Resources
- Multi-tenancy : Eloquent Global Scopes (`BelongsToTenant` Trait)
- Style : Laravel Pint + PHPStan ≥ niveau 6
- i18n : fichiers `lang/` natifs Laravel

**Front-end :**

- SPA Vue 3 avec Vite
- État global : Pinia
- Style : Tailwind CSS
- i18n : vue-i18n
- Tests : Vitest + Vue Test Utils

### Stack TypeScript / Elysia / Next.js

**Back-end :**

- Architecture par domaines sous Elysia
- Tests : Vitest ou Bun.test
- Validation : schémas stricts Zod ou TypeBox sur toutes les requêtes et réponses
- Multi-tenancy : middleware ORM (Prisma/Drizzle) de filtrage automatique
- Style : ESLint + Prettier, `strict: true` dans tsconfig
- i18n : i18next avec namespaces par module

**Front-end :**

- Next.js App Router
- Server Components pour tout ce qui n'est pas dynamique côté permission
- Client Components isolés pour l'interactivité
- i18n : next-intl
- Tests : Jest ou Vitest + React Testing Library

---

## 9. CHECKLIST DE FIN DE MODULE

Avant de passer au module suivant, valider chaque point :

```
BACK-END
[ ] Migrations additives, nullables, avec down() symétrique
[ ] migrate:fresh --seed tourne sans erreur
[ ] Index explicites (dont composites tenant_id + ...)
[ ] Factories et Seeders fonctionnels
[ ] DeletionGuard implémentée sur toutes les suppressions
[ ] Politique soft/hard delete documentée dans context.md
[ ] Toutes les relations eager-loadées (zéro N+1)
[ ] Routes sous /api/v1/, rate limiting configuré
[ ] Toutes les listes paginées (per_page=20 par défaut)
[ ] Enveloppe de réponse { success, data, meta } respectée
[ ] Toutes les réponses API passent par les Resources/DTOs
[ ] Aucune chaîne hardcodée dans les réponses (tout passe par i18n)
[ ] Dates stockées en UTC, retournées en ISO 8601
[ ] Isolation tenant vérifiée (test cross-tenant 404 vert)
[ ] Jobs longs/externes en queue, pas inline
[ ] Tests unitaires + feature verts (CI vert)
[ ] Tests isolés : chaque test crée son propre tenant
[ ] Code review validée (/engineering:code-review)
[ ] Doc API mise à jour (/engineering:documentation)

FRONT-END
[ ] Affichage 100% piloté par les permissions back-end
[ ] i18n : toutes les chaînes traduites en EN + FR
[ ] Dates affichées dans le timezone de l'utilisateur
[ ] Accessibilité WCAG 2.1 AA (/impeccable audit)
[ ] Animations validées (/review-animations si applicable)
[ ] États vides, d'erreur et de chargement implémentés
[ ] Core Web Vitals dans les cibles (LCP<2.5s, CLS<0.1, INP<200ms)
[ ] Code splitting lazy par module
[ ] Tests store + composants conditionnels verts

GLOBAL
[ ] /handoff exécuté, handoff.md mis à jour
[ ] Aucun secret dans le code ou le git history
[ ] CI vert sur la branche
[ ] context.md mis à jour si nouveau concept validé
```

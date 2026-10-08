# ADR-002 — Authentification & Autorisation

**Status :** Accepted
**Date :** Session 4
**Décideurs :** Équipe projet

---

## Contexte

L'application est une SPA Vue 3 (`frontend/`) qui consomme une API Laravel (`backend/`). Il faut choisir comment :

1. Authentifier l'utilisateur et maintenir sa session
2. Transporter l'identité du tenant sur chaque requête API
3. Vérifier les permissions sur chaque endpoint

Deux guards sont nécessaires : un pour les utilisateurs tenant (`web`/`api`), un pour les SuperAdmins (`super_admin`).

## Décision

### Authentification SPA — Laravel Sanctum (cookie-based)

Sanctum en mode SPA : le backend émet un cookie de session httpOnly après `/api/v1/login`. Le frontend n'accède jamais au token directement. La protection CSRF est assurée via le endpoint `/sanctum/csrf-cookie`.

```
Frontend (app.tonsaas.com) ──CORS──► Backend (api.tonsaas.com)
                                         ↓
                                  session cookie httpOnly
                                  + XSRF-TOKEN cookie (lisible JS)
```

Avantage vs API tokens stateless : le `tenant_id` est résolu une fois à la connexion et stocké en session Laravel. Chaque requête suivante lit `session('tenant_id')` sans requête DB supplémentaire.

### Résolution du tenant

À la connexion :
```php
// AuthController::login()
$user = User::with('organisation')->firstWhere('email', $request->email);
Auth::login($user);
session(['tenant_id' => $user->organisation_id]);
```

Middleware `SetTenantContext` (exécuté sur chaque requête API authentifiée) :
```php
app()->instance('tenant_id', session('tenant_id'));
```

### Autorisation — permissions atomiques

Pas de packages tiers (Spatie, etc.). Système maison minimal :

```php
// Gate défini dans AppServiceProvider
Gate::before(function (User $user, string $ability) {
    return $user->hasPermission($ability) ?: null;
});

// User::hasPermission()
public function hasPermission(string $permission): bool
{
    return $this->role->permissions->contains('name', $permission);
}
```

Les permissions sont chargées en eager load à la connexion et mise en cache dans la session pour éviter N+1 sur chaque requête.

### Guard SuperAdmin

Guard séparé `super_admin` pointant sur le modèle `SuperAdmin` (table `super_admins`). Accessible uniquement depuis des IPs whitelistées (configurable via `.env`). N'a accès à aucun endpoint tenant.

## Alternatives écartées

**JWT stateless :** nécessite une bibliothèque tierce (tymon/jwt-auth), complique la révocation de token, et le `tenant_id` doit être embarqué dans le payload JWT (risque si compromis). La session Laravel est plus simple et tout aussi sécurisée pour une SPA same-origin.

**Spatie Laravel-Permission :** package robuste mais ajoute une couche de complexité (rôles morphables, cache Spatie) pour des besoins couverts par 30 lignes de code maison. Le système de permissions est assez simple (liste plate, pas d'héritage runtime) pour ne pas nécessiter de package.

**Politique multi-guard sur le même modèle :** confus, impossible à tester proprement. Deux modèles distincts (`User` vs `SuperAdmin`) avec deux guards distincts est plus lisible.

## Conséquences

**Positives :**
- Zero dépendance externe pour l'autorisation
- `tenant_id` en session → résolution O(1) sur chaque requête
- Cookie httpOnly → XSS ne peut pas voler la session
- Guard `super_admin` totalement isolé

**Négatives / risques :**
- Requiert configuration CORS stricte (`allowed_origins`, `supports_credentials: true`)
- Pas de JWT → pas d'authentification mobile native simple (acceptable en v1, SPA seulement)
- Permissions en session → déconnexion requise si les permissions d'un rôle sont modifiées en live. Mitigation : émettre un événement `RolePermissionsChanged` qui invalide les sessions actives des utilisateurs concernés via `Auth::logoutOtherDevices()`

## Routes d'authentification

```
POST   /sanctum/csrf-cookie          → génère le XSRF-TOKEN
POST   /api/v1/auth/login            → authentifie, retourne User + permissions
POST   /api/v1/auth/logout           → détruit la session
GET    /api/v1/auth/me               → retourne User courant + role + permissions
POST   /api/v1/super/auth/login      → guard super_admin (IP whitelist middleware)
```

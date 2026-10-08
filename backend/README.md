# mini-store-erp — Backend

API REST Laravel 13 alimentant le micro-ERP SaaS pour les petites entreprises d'Afrique de l'Ouest.

## Rôle de ce service

Ce backend expose l'ensemble des endpoints métier : gestion des articles, du stock, de la facturation, des tiers (clients/fournisseurs), des utilisateurs et des permissions. Il produit également les documents imprimables (tickets thermiques, factures PDF).

## Stack

- **Framework** : Laravel 13 (PHP ≥ 8.3)
- **Base de données** : SQLite (dev) — MySQL / PostgreSQL (prod)
- **Queue** : Database (dev) — Redis (prod recommandé)
- **Cache** : Database
- **Sessions** : Database
- **Tests** : PHPUnit 12

## Installation

```bash
php artisan migrate:fresh
composer run setup
```

Cette commande installe les dépendances, génère la clé d'application, exécute les migrations et compile les assets.

## Démarrage

```bash
composer run dev
```

Lance en parallèle : serveur HTTP, queue worker, log viewer (Pail) et Vite.

## Tests

```bash
composer run test
```

## Structure notable

```
backend/
├── app/
│   ├── Http/Controllers/   # Contrôleurs API
│   ├── Models/             # Modèles Eloquent
│   └── Providers/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── web.php
│   └── console.php
└── config/
```

## Conformité comptable

Le moteur de facturation prend en charge les systèmes comptables en vigueur en Afrique de l'Ouest (SYSCOHADA), avec paramétrage de l'année comptable et des règles de calcul par zone géographique.

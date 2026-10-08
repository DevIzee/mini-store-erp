# mini-store-erp

Micro-ERP SaaS léger conçu pour les petites entreprises (boutiques, supérettes, grossistes) en Afrique de l'Ouest, en commençant par le Togo.

## Problème résolu

Les ERP traditionnels sont trop lourds, trop chers et inadaptés aux réalités locales (connectivité intermittente, impression thermique 80mm, comptabilité SYSCOHADA). `mini-store-erp` offre les fonctionnalités essentielles d'un ERP sans la complexité.

## Fonctionnalités principales

- **Gestion des articles** — produits matériels et immatériels, prix d'achat/vente, groupes, codes-barres
- **Gestion du stock** — mouvements, bons de livraison, bons d'achat, inventaires, réconciliation
- **Facturation** — factures, proformas, tickets thermiques (80mm), factures A4 PDF, compatibilité SYSCOHADA
- **Tiers** — clients, fournisseurs avec fiches complètes
- **Entrepôts** — gestion multi-entrepôts avec notifications de stock
- **Analytiques** — statistiques de vente, analyses de stock, traçabilité des actions
- **Paramétrage** — organisation, utilisateurs, rôles, permissions, abonnements

## Interface adaptative

L'affichage et les fonctionnalités changent dynamiquement selon le rôle de l'utilisateur :

- Ticket thermique 80mm → Facture A4 PDF
- Vente rapide (caissier) → Facturation détaillée (comptable) → Tableaux de bord (manager)

## Stack technique

| Couche          | Technologie                               |
| --------------- | ----------------------------------------- |
| Backend         | Laravel 13 (PHP 8.3)                      |
| Frontend        | Vue 3 + Vite                              |
| Base de données | SQLite (dev) / MySQL ou PostgreSQL (prod) |
| Queue           | Database / Redis                          |
| CSS             | Tailwind CSS v4                           |

## Structure du projet

```
mini-store-erp/
├── backend/    # API Laravel
└── frontend/   # SPA Vue 3
```

## Démarrage rapide

```bash
# Backend
cd backend
php artisan migrate:fresh
composer run setup

# Frontend
cd frontend
pnpm install
pnpm dev
```

## Cible marché

PME en Afrique de l'Ouest : boutiques, supérettes, grossistes, pharmacies, quincailleries.

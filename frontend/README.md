# mini-store-erp — Frontend

Interface Vue 3 du micro-ERP SaaS. L'UI s'adapte dynamiquement au rôle de l'utilisateur connecté.

## Rôle de ce service

SPA (Single Page Application) qui consomme l'API Laravel. L'affichage et les fonctionnalités disponibles changent selon le profil actif — un caissier voit un écran de vente rapide avec impression thermique 80mm, un comptable accède à la facturation A4 et aux proformas, un manager consulte les tableaux de bord analytiques.

## Stack

- **Framework** : Vue 3 (Composition API + `<script setup>`)
- **Bundler** : Vite 8 + Rolldown
- **Gestionnaire de paquets** : pnpm

## Installation

```bash
pnpm install
```

## Démarrage

```bash
pnpm dev
```

## Build production

```bash
pnpm build
```

## Modules UI prévus

| Module       | Description                                      |
| ------------ | ------------------------------------------------ |
| Vente rapide | Caisse avec scan code-barres et ticket thermique |
| Facturation  | Factures, proformas, export PDF A4               |
| Stock        | Mouvements, inventaires, alertes entrepôt        |
| Articles     | Catalogue, groupes, unités de mesure             |
| Tiers        | Clients et fournisseurs                          |
| Analytiques  | Statistiques, traçabilité, rapports              |
| Paramétrage  | Organisation, utilisateurs, rôles, abonnements   |

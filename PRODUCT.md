# PRODUCT.md — mini-store-erp

> Document de référence produit. Toute décision UX/UI doit être cohérente avec ce fichier.

---

## Identité

**Nom de code :** mini-store-erp
**Tagline :** L'ERP qui tient dans votre boutique.
**Positionnement :** Micro-ERP SaaS pour PME en Afrique de l'Ouest — simple, rapide, adapté au terrain.

---

## Problème résolu

Les ERP du marché (Sage, Odoo, ERPNext) sont conçus pour des entreprises occidentales avec des équipes IT, une connexion stable et des budgets élevés. Une supérette à Lomé ou un grossiste à Cotonou n'a besoin que de :

- Vendre vite (ticket thermique en 3 clics)
- Suivre son stock en temps réel
- Savoir ce qu'il a gagné dans la journée
- Imprimer une facture SYSCOHADA si demandé

`mini-store-erp` couvre exactement ces besoins, pas plus. Chaque fonctionnalité qui n'est pas dans ce périmètre est un refus délibéré.

---

## Utilisateurs types

### Operator — Caissier / Agent de vente
**Contexte :** Debout derrière un comptoir, mobile ou tablette d'entrée de gamme, flux de clients continu, pas le temps de lire.
**Besoins clés :**
- Créer une vente en moins de 30 secondes
- Scanner un code-barres ou rechercher un article par nom
- Imprimer le ticket thermique 80mm immédiatement
- Créer un proforma rapidement sans bloquer la caisse

**Ce qu'il ne fait pas :** stock, achats, rapports, paramètres.

---

### Lead_Operator — Responsable de dépôt / Chef de rayon
**Contexte :** Gère les réceptions de stock, les retours clients, surveille les ruptures. Souvent en mouvement dans l'entrepôt.
**Besoins clés :**
- Enregistrer une réception de marchandise (bon d'entrée)
- Voir les alertes de stock bas de son warehouse
- Créer des proformas pour les clients grossistes
- Convertir un proforma en vente

**Ce qu'il ne fait pas :** transferts inter-warehouses, réconciliation, rapports financiers.

---

### Manager — Responsable multi-sites / Directeur d'exploitation
**Contexte :** Accède depuis un bureau ou en déplacement, lit des chiffres, prend des décisions opérationnelles.
**Besoins clés :**
- Voir les ventes du jour / semaine / mois sur tous les warehouses
- Surveiller les niveaux de stock globaux
- Créer des bons de commande fournisseurs
- Valider les réconciliations d'inventaire

**Ce qu'il ne fait pas :** créer des utilisateurs, modifier les paramètres organisation.

---

### Admin — Propriétaire / Dirigeant
**Contexte :** A la vision complète. Configure, contrôle, analyse.
**Besoins clés :**
- Paramétrer l'organisation (entête facture, taxes, numérotation)
- Créer et gérer les utilisateurs et leurs rôles
- Accéder à tous les rapports
- Gérer l'abonnement et les warehouses

---

## Contraintes terrain (non-négociables)

| Contrainte | Impact sur le produit |
|---|---|
| **Mobile first** | Toutes les vues critiques (vente, stock) doivent fonctionner sur écran 360px |
| **Connectivité intermittente** | Les actions critiques ne doivent pas échouer silencieusement — feedback clair si offline |
| **Écrans bas de gamme** | Contraste minimum WCAG AA. Pas d'effets visuels lourds |
| **Impression thermique 80mm** | Le ticket de caisse doit être imprimable depuis tout navigateur mobile via `window.print()` |
| **Impression A4** | La facture SYSCOHADA doit s'imprimer proprement sur imprimante standard |
| **Langue française** | Interface 100% en français. Zéro terme anglais visible à l'utilisateur |

---

## Principes UX non-négociables

### 1. L'interface suit les permissions, jamais le rôle
Un bouton est visible parce que l'utilisateur a la permission `sales.create`, pas parce qu'il est "Operator". Si un Admin retire cette permission à un rôle, le bouton disparaît immédiatement à la prochaine connexion.

### 2. Zéro ambiguïté sur les actions irréversibles
Toute action irréversible (validation d'une vente, validation d'une réconciliation) doit :
- Afficher un dialogue de confirmation explicite
- Lister clairement ce qui va changer
- Être impossible à annuler — l'UI le dit avant, pas après

### 3. La caisse ne doit jamais bloquer
Le flux de vente (Operator) est le chemin critique. Aucun composant non-essentiel (widget analytics, alertes secondaires) ne doit charger sur cet écran. Temps de réponse cible : < 500ms sur 3G.

### 4. Feedback immédiat
Chaque action déclenche un état visuel (loading, success, error) dans les 100ms. Pas de bouton qui "disparaît" sans confirmation.

### 5. Densité d'information adaptée au rôle
- **Operator :** UI épurée, grandes touches, une seule action principale par écran
- **Manager/Admin :** tableaux denses, filtres multiples, vues comparatives

### 6. Impression = partie intégrante du produit
Le ticket 80mm et la facture A4 ne sont pas des exports annexes — ce sont des fonctionnalités de première classe, testées à chaque release.

---

## Ce que le produit ne fait pas (v1)

Ces fonctionnalités sont **explicitement hors périmètre**. Ne pas les implémenter, ne pas prévoir d'extension facile vers elles.

- ❌ Comptabilité double-entrée (journaux, bilans, comptes de résultat)
- ❌ Gestion de la paie et des RH
- ❌ CRM avancé (pipeline commercial, opportunités)
- ❌ E-commerce ou vente en ligne
- ❌ Mode offline-first / sync hors-ligne
- ❌ Application mobile native (iOS/Android)
- ❌ Multi-devises actives (XOF uniquement en v1)
- ❌ Intégration bancaire ou paiement mobile money automatisé
- ❌ Factures électroniques B2G (OTR Togo non implémenté)
- ❌ Gestion de la production / nomenclatures (BOM)

---

## Périmètre v1 — ce qui EST dans le produit

- ✓ Ventes avec ticket thermique 80mm
- ✓ Proformas + conversion en vente
- ✓ Factures A4 PDF (SYSCOHADA-compatible)
- ✓ Bons de livraison
- ✓ Gestion des articles, groupes, taxes, unités
- ✓ Clients et fournisseurs
- ✓ Bons de commande fournisseur
- ✓ Mouvements de stock (entrée, sortie, transfert)
- ✓ Réconciliation d'inventaire
- ✓ Alertes stock bas (in-app)
- ✓ Import/Export CSV (articles, clients, fournisseurs)
- ✓ Analytiques (8 rapports définis dans `context.md`)
- ✓ Multi-warehouses
- ✓ Gestion des utilisateurs, rôles et permissions
- ✓ Exercices fiscaux
- ✓ Numérotation automatique des documents

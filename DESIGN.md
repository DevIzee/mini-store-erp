# DESIGN.md — Système de Design mini-store-erp

> Référence unique pour toutes les décisions visuelles. Les tokens CSS définis ici
> alimentent directement `frontend/src/style.css` et la config Tailwind v4.

---

## 1. Principes visuels

- **Clarté avant esthétique** — chaque pixel doit servir la lisibilité sur un écran d'entrée de gamme
- **Contraste élevé** — minimum WCAG AA (ratio 4.5:1) pour tout texte, 3:1 pour les éléments UI
- **Densité contextuelle** — compact pour les tableaux de gestion, aéré pour les flux de caisse
- **Impression native** — le design doit être aussi propre sur papier que sur écran

---

## 2. Palette de couleurs

### Couleur primaire — Bleu professionnel

| Token | Valeur | Usage |
|---|---|---|
| `--color-primary-950` | `#0D1B4B` | Texte sur fond clair (rare) |
| `--color-primary-900` | `#1E3A8A` | Titres forts, en-têtes |
| `--color-primary-800` | `#1E40AF` | — |
| `--color-primary-700` | `#1D4ED8` | Hover sur bouton primaire |
| `--color-primary-600` | `#2563EB` | **Couleur primaire principale** — boutons, liens actifs |
| `--color-primary-500` | `#3B82F6` | Icônes actives |
| `--color-primary-400` | `#60A5FA` | — |
| `--color-primary-100` | `#DBEAFE` | Fond badge info, sélection de ligne |
| `--color-primary-50`  | `#EFF6FF` | Fond subtle — highlight, hover de menu |

### Neutrals — Slate (base chaude)

| Token | Valeur | Usage |
|---|---|---|
| `--color-neutral-950` | `#0F172A` | Texte principal (dark mode) |
| `--color-neutral-900` | `#1E293B` | Sidebar, headers sombres |
| `--color-neutral-800` | `#334155` | Texte principal (light mode) |
| `--color-neutral-700` | `#475569` | Texte secondaire |
| `--color-neutral-600` | `#64748B` | Labels, placeholders |
| `--color-neutral-500` | `#94A3B8` | Texte désactivé, bordures légères |
| `--color-neutral-400` | `#CBD5E1` | Bordures |
| `--color-neutral-300` | `#E2E8F0` | Bordures subtiles, séparateurs |
| `--color-neutral-200` | `#F1F5F9` | Fond alterné dans les tableaux |
| `--color-neutral-100` | `#F8FAFC` | Fond de page, fond de carte |
| `--color-neutral-50`  | `#FFFFFF` | Fond de surface |

### États sémantiques

| Token | Valeur | Usage |
|---|---|---|
| `--color-success-700` | `#15803D` | Texte success |
| `--color-success-600` | `#16A34A` | Icône, bordure success |
| `--color-success-100` | `#DCFCE7` | Fond badge success |
| `--color-success-50`  | `#F0FDF4` | Fond alerte success |
| `--color-warning-700` | `#B45309` | Texte warning |
| `--color-warning-600` | `#D97706` | Icône, bordure warning |
| `--color-warning-100` | `#FEF3C7` | Fond badge warning |
| `--color-warning-50`  | `#FFFBEB` | Fond alerte warning |
| `--color-error-700`   | `#B91C1C` | Texte erreur |
| `--color-error-600`   | `#DC2626` | Icône, bordure erreur, bouton destructif |
| `--color-error-100`   | `#FEE2E2` | Fond badge erreur |
| `--color-error-50`    | `#FEF2F2` | Fond alerte erreur |

### Couleur d'accentuation — Teal (stock, mouvements)

| Token | Valeur | Usage |
|---|---|---|
| `--color-accent-600` | `#0D9488` | Badges stock, mouvements |
| `--color-accent-100` | `#CCFBF1` | Fond badge stock |
| `--color-accent-50`  | `#F0FDFA` | Fond highlight stock |

---

## 3. Typographie

### Familles

```css
--font-sans:  'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
--font-mono:  'JetBrains Mono', 'Fira Code', ui-monospace, monospace;
--font-print: 'Courier New', Courier, monospace; /* tickets thermiques */
```

Inter est chargée via `@fontsource/inter` (auto-hébergé, pas de dépendance Google Fonts).

### Échelle typographique

| Token | Taille | Line-height | Usage |
|---|---|---|---|
| `--text-xs`  | `11px` | `16px` | Labels de formulaire, metadata |
| `--text-sm`  | `13px` | `20px` | Texte de tableau, badges |
| `--text-base`| `15px` | `24px` | **Corps de texte par défaut** |
| `--text-md`  | `17px` | `28px` | Texte mis en valeur |
| `--text-lg`  | `19px` | `28px` | Titres de section, sous-titres |
| `--text-xl`  | `22px` | `32px` | Titres de page |
| `--text-2xl` | `28px` | `36px` | KPI, totaux importants |
| `--text-3xl` | `36px` | `44px` | Grands chiffres dashboard |

### Poids

| Token | Valeur | Usage |
|---|---|---|
| `--font-normal`   | `400` | Corps de texte |
| `--font-medium`   | `500` | Labels actifs, en-têtes de colonne |
| `--font-semibold` | `600` | Titres, boutons, totaux |
| `--font-bold`     | `700` | KPI critiques, alertes |

---

## 4. Espacements

Base unit : **4px**. Toute valeur d'espacement est un multiple de 4.

| Token | Valeur | Usage typique |
|---|---|---|
| `--space-1`  | `4px`   | Gap interne icône/texte |
| `--space-2`  | `8px`   | Padding badge, gap compact |
| `--space-3`  | `12px`  | Padding bouton vertical |
| `--space-4`  | `16px`  | Padding cellule tableau, gap standard |
| `--space-5`  | `20px`  | Padding card compact |
| `--space-6`  | `24px`  | Padding section |
| `--space-8`  | `32px`  | Gap entre blocs |
| `--space-10` | `40px`  | Padding card spacieux |
| `--space-12` | `48px`  | Espace entre sections majeures |
| `--space-16` | `64px`  | Padding page |

---

## 5. Grille & Layout

```
Breakpoints :
  mobile   : < 640px   (caisse, opérateur terrain)
  tablet   : 640–1024px
  desktop  : > 1024px  (manager, admin)

Layout principal :
  Sidebar fixe gauche  : 240px (desktop) | bottombar (mobile)
  Zone de contenu      : flex-1, padding 24px desktop / 16px mobile
  Max-width contenu    : 1280px centré

Sidebar (desktop) :
  Fond : --color-neutral-900
  Texte: --color-neutral-400 (inactif) / --color-neutral-50 (actif)
  Item actif : fond --color-primary-600, texte blanc
```

---

## 6. Composants clés

### Boutons

| Variante | Fond | Texte | Bordure | Usage |
|---|---|---|---|---|
| Primary | `--color-primary-600` | blanc | — | Action principale |
| Secondary | blanc | `--color-primary-600` | `--color-primary-600` | Action secondaire |
| Ghost | transparent | `--color-neutral-700` | `--color-neutral-300` | Annuler, actions tertiaires |
| Danger | `--color-error-600` | blanc | — | Supprimer, actions destructives |
| Disabled | `--color-neutral-200` | `--color-neutral-400` | — | Toujours visible mais inactif |

Taille standard : `height: 36px`, `padding: 0 16px`, `border-radius: 6px`, `font-weight: 500`, `font-size: 14px`
Taille large (caisse) : `height: 48px`, `padding: 0 24px`, `font-size: 16px`

### Badges de statut

Forme : `border-radius: 4px`, `padding: 2px 8px`, `font-size: 12px`, `font-weight: 500`

| Statut | Fond | Texte |
|---|---|---|
| Actif / Validé | `--color-success-100` | `--color-success-700` |
| En attente / Draft | `--color-warning-100` | `--color-warning-700` |
| Erreur / Refusé | `--color-error-100` | `--color-error-700` |
| Info | `--color-primary-100` | `--color-primary-700` |
| Stock (entrée) | `--color-accent-100` | `--color-accent-600` |

### Tableaux

```
En-tête : fond --color-neutral-100, texte --color-neutral-600, font-weight 500, font-size 12px UPPERCASE
Ligne pair : fond --color-neutral-50
Ligne impair : fond blanc
Ligne hover : fond --color-primary-50
Bordure : 1px solid --color-neutral-200 (horizontale uniquement)
Cellule padding : 12px 16px
```

Colonne montant : toujours alignée à droite, `font-variant-numeric: tabular-nums`.

### Champs de formulaire

```
Height : 36px
Padding : 0 12px
Border : 1px solid --color-neutral-300
Border-radius : 6px
Font-size : 14px
Couleur texte : --color-neutral-800
Fond : blanc
Focus : border --color-primary-500, box-shadow 0 0 0 3px --color-primary-50
Erreur : border --color-error-500, fond --color-error-50
Disabled : fond --color-neutral-100, texte --color-neutral-400
```

### Cards

```
Fond : blanc
Border : 1px solid --color-neutral-200
Border-radius : 8px
Shadow : 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)
Padding : 20px (compact) | 24px (standard)
```

### KPI Cards (Dashboard)

```
Layout : icône + valeur + label + delta
Valeur principale : --text-2xl, --font-bold, --color-neutral-900
Label : --text-sm, --color-neutral-600
Delta positif : --color-success-600 ↑
Delta négatif : --color-error-600 ↓
```

---

## 7. Icônes

Bibliothèque : **Heroicons v2** (outline par défaut, solid pour états actifs).
Taille standard : `20px` (inline) / `24px` (boutons et navigation).
Couleur : héritée du texte parent sauf exception.

---

## 8. Règles d'impression

### Ticket thermique 80mm

```
Largeur imprimable : 58mm (marges thermiques standard)
Police : --font-print (Courier New, monospace)
Taille corps : 10pt
Titre magasin : 12pt, centré, bold
Séparateur : ligne de tirets (--------------------------------)
Totaux : alignés à droite avec tabulation fixe
Logo : uniquement si printer_logo activé dans les paramètres org
Pas de couleur, pas d'image sauf logo monochrome
Media query : @media print { body { width: 58mm; font-family: var(--font-print); } }
```

Structure d'un ticket :
```
         NOM DU MAGASIN
         Adresse, Tél
         ================================
         Date : 30/07/2026   Ref: FAC-001
         --------------------------------
         Article 1         2 x 500    1000
         Article 2         1 x 1500   1500
         --------------------------------
         TOTAL HT                    2500
         TVA 18%                      450
         TOTAL TTC                   2950
         ================================
         Merci de votre visite !
```

### Facture A4

```
Marges : 20mm haut/bas, 15mm gauche/droite
En-tête : logo (si disponible) + identité organisation + numéro + date
Tableau lignes : colonnes fixes, bordures légères
Pied : totaux HT / TVA / TTC dans un bloc right-aligned
Mentions légales : pied de page, font-size 8pt, --color-neutral-500
Couleur primaire autorisée pour les en-têtes de tableau
Font : Inter (si disponible en PDF) ou système
```

---

## 9. Tokens CSS — fichier de référence

À placer dans `frontend/src/tokens.css`, importé en tête de `style.css` :

```css
:root {
  /* Primaire */
  --color-primary-50:  #EFF6FF;
  --color-primary-100: #DBEAFE;
  --color-primary-500: #3B82F6;
  --color-primary-600: #2563EB;
  --color-primary-700: #1D4ED8;
  --color-primary-900: #1E3A8A;

  /* Neutrals */
  --color-neutral-50:  #FFFFFF;
  --color-neutral-100: #F8FAFC;
  --color-neutral-200: #F1F5F9;
  --color-neutral-300: #E2E8F0;
  --color-neutral-400: #CBD5E1;
  --color-neutral-500: #94A3B8;
  --color-neutral-600: #64748B;
  --color-neutral-700: #475569;
  --color-neutral-800: #334155;
  --color-neutral-900: #1E293B;
  --color-neutral-950: #0F172A;

  /* États */
  --color-success-50:  #F0FDF4;
  --color-success-100: #DCFCE7;
  --color-success-600: #16A34A;
  --color-success-700: #15803D;

  --color-warning-50:  #FFFBEB;
  --color-warning-100: #FEF3C7;
  --color-warning-600: #D97706;
  --color-warning-700: #B45309;

  --color-error-50:    #FEF2F2;
  --color-error-100:   #FEE2E2;
  --color-error-600:   #DC2626;
  --color-error-700:   #B91C1C;

  --color-accent-50:   #F0FDFA;
  --color-accent-100:  #CCFBF1;
  --color-accent-600:  #0D9488;

  /* Typographie */
  --font-sans:  'Inter', ui-sans-serif, system-ui, sans-serif;
  --font-mono:  'JetBrains Mono', ui-monospace, monospace;
  --font-print: 'Courier New', Courier, monospace;

  /* Espacements */
  --space-1:  4px;
  --space-2:  8px;
  --space-3:  12px;
  --space-4:  16px;
  --space-5:  20px;
  --space-6:  24px;
  --space-8:  32px;
  --space-10: 40px;
  --space-12: 48px;
  --space-16: 64px;

  /* Radius */
  --radius-sm: 4px;
  --radius-md: 6px;
  --radius-lg: 8px;
  --radius-xl: 12px;

  /* Shadows */
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
  --shadow-md: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-lg: 0 4px 6px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.04);
}
```

# KORA Design System

Source : maquettes HTML/CSS fournies par le client (voir `docs/maquettes/`).

## Design Tokens

### Couleurs

| Token | Valeur | Usage |
|-------|--------|-------|
| `--kora` / `--navy` | `#001D4E` | Couleur primaire (fond sombre, texte sur or, sidebar, headers) |
| `--gold` | `#F6B000` | Couleur secondaire (CTA, accents, badges, focus) |
| `--white` | `#FFFFFF` | Fond clair, texte sur navy |
| `--digital` | `#0057B8` | Liens, actions secondaires, focus rings, tendances positives |
| `--mist` | `#F4F6F8` | Fond neutre, surfaces secondaires, inputs |
| `--graphite` | `#111827` | Texte principal |
| `--muted` | `rgba(17, 24, 39, .6)` | Texte secondaire, labels, descriptions |
| `--line` | `rgba(0, 29, 78, .10)` | Bordures, separateurs |

### Ombres

| Token | Valeur | Usage |
|-------|--------|-------|
| `--shadow` | `0 24px 60px rgba(0, 29, 78, .14)` | Cartes, modales (app passenger) |
| `--shadow` | `0 18px 45px rgba(0, 29, 78, .09)` | Cartes, panels (ops web) |

### Border-radius

| Token | Valeur | Usage |
|-------|--------|-------|
| `--radius` | `18px` | Cards, panels (ops web) |
| (direct) | `14px` | Boutons, brand lockup |
| (direct) | `12px` | Inputs, controls, nav items |
| (direct) | `11px` | Boutons (ops web) |
| (direct) | `10px` | Avatars, icon badges |
| (direct) | `9px` | Small buttons |
| (direct) | `999px` | Pills, meta tags |

### Typographie

**Font principale : Inter** (partout, web + mobile)

> Note : la maquette app passenger utilise Aptos (font Microsoft 365, proprietaire).
> Inter est utilisee a la place pour des raisons de licence. Visuellement tres proche.

| Element | Famille | Taille | Poids |
|---------|---------|--------|-------|
| H1 (app) | Inter | `clamp(34px, 4vw, 58px)` | 700 |
| H1 (web) | Inter | `clamp(38px, 4.7vw, 72px)` | 700 |
| H1 (page) | Inter | `29px` | 700 |
| H2 (card) | Inter | `34px` | 700 |
| Body | Inter | `13-17px` | 400 |
| Label | Inter | `12-13px` | 800 |
| Caption | Inter | `10-11px` | 750-900 |
| Eyebrow | Inter | `12px` | 900, letter-spacing `.15em` |

**Installation :**
- Web : `@fontsource/inter` (npm, self-hosted, pas de CDN Google)
- Flutter : `google_fonts` package (Inter disponible)

### Espacements (deduits des maquettes)

| Token | Valeur | Usage |
|-------|--------|-------|
| `xs` | `8px` | Gaps entre petits elements |
| `sm` | `12px` | Padding interne, gaps |
| `md` | `15-17px` | Padding champs, gaps |
| `lg` | `20-22px` | Padding cards |
| `xl` | `28-30px` | Padding content |
| `2xl` | `44-64px` | Padding sections |

### Effets

| Token | Valeur | Usage |
|-------|--------|-------|
| `--ease` | `cubic-bezier(.2, .8, .2, 1)` | Transitions |
| Focus ring | `3px solid rgba(0, 87, 184, .3)` | App passenger |
| Focus ring | `3px solid rgba(246, 176, 0, .55)` | Ops web |
| Transition | `0.18s` | Boutons hover |

## Composants identifiés dans les maquettes

### Boutons

| Variante | Style | Usage |
|----------|-------|-------|
| `.btn-primary` | Fond gold, texte navy, shadow gold | CTA principal |
| `.btn-navy` | Fond navy, texte blanc | Action secondaire |
| `.btn-ghost` | Bordure line, fond blanc, texte navy | Action tertiaire |
| `.btn-full` | Width 100%, min-height 52px | CTA plein largeur |
| `.panel-action` | Fond gold, texte navy, radius 14px, shadow | CTA hero panel |

### Inputs

| Element | Style |
|---------|-------|
| `.control` | Min-height 50px, border 1px line, radius 12px, bg white |
| `.control:hover/focus` | Border digital |
| `.field label` | Color navy, 13px, font-weight 800 |
| `.search` | Height 42px, padding-left 40px (icon), bg mist |

### Cards

| Element | Style |
|---------|-------|
| `.kpi` | Padding 19px, border 1px line, radius 18px, bg white, shadow |
| `.panel` | Padding 20px, border 1px line, radius 18px, bg white, shadow |

### Navigation

| Element | Style |
|---------|-------|
| `.sidebar` | Width 264px, bg navy, texte blanc |
| `.nav-item` | Min-height 43px, radius 11px, hover bg rgba(255,255,255,.09) |
| `.nav-item.active` | Box-shadow inset 3px gold (left border) |
| `.nav-icon` | 21x21px, radius 7px, color gold, bg rgba(246,176,0,.1) |

### Badges / Pills

| Element | Style |
|---------|-------|
| `.meta-pill` | Padding 7px 10px, radius 999px, border 1px rgba(255,255,255,.17) |
| `.avatar` | 36x36px, radius 10px, bg gold, color navy, font-weight 900 |

## Correspondance Web ↔ Mobile

Les tokens sont identiques entre les deux maquettes. La seule difference de nommage :

| Web (ops web) | Mobile (app passenger) | Valeur |
|---------------|------------------------|--------|
| `--navy` | `--kora` | `#001D4E` |
| `--gold` | `--gold` | `#F6B000` |
| `--digital` | `--digital` | `#0057B8` |
| `--mist` | `--mist` | `#F4F6F8` |
| `--graphite` | `--graphite` | `#111827` |
| `--muted` | `--muted` | `rgba(17,24,39,.6)` |
| `--line` | `--line` | `rgba(0,29,78,.10)` |
| `--shadow` | `--shadow` | legere variation (web plus doux) |
| `--radius` | — | `18px` (web seulement) |

## Dark mode

Structure preparee (CSS variables) mais non implementee. Les maquettes du client sont en light mode uniquement. Le dark mode sera ajoute si le client le demande explicitement.

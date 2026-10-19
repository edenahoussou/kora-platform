# ADR 0013 — Supervision de caisse et sessions non clôturées

## Statut

Accepté — 14 octobre 2026

## Contexte

La page `/cash` ne chargeait que la session du jour de l'utilisateur connecté. Un responsable d'agence ne pouvait ni voir la session de ses agents, ni consulter l'historique, ni connaître les soldes avant l'ouverture du jour. Une session laissée ouverte la veille devenait invisible tout en bloquant sa caisse — deadlock opérationnel sans issue par l'interface.

## Décisions

### Périmètre de consultation

- La visibilité suit la cascade établie par le module Dépenses : `cash.view-network` → `cash.view-agency` → session personnelle (`cash.view` sur ses propres sessions). `AgencyManager` reçoit `cash.view-agency` ; `OperationsDirector` et l'administration réseau reçoivent `cash.view-network`.
- Deux écrans dédiés : `GET /cash/sessions` (liste filtrable : période, agent, caisse, statut, écart, agence) et `GET /cash/sessions/{uuid}` (détail complet : sous-journaux, soldes, preuves photo d'ouverture/clôture, journal des mouvements, cycle de vie).
- Les preuves restent servies par `cash/media/{uuid}` qui impose déjà l'appartenance à l'agence et `cash.media.view`.
- La supervision permet les actions de contrôle sur la session consultée : approbation d'écart (`cash.approve-variance`) et approbation/rejet des mouvements en attente (`cash.transfer.approve`), sous les policies existantes.

### Soldes persistants

- `/cash` expose en permanence `agencyBalances`, calculé par `CashRegisterBalance` (dernière ouverture + mouvements publiés) par caisse et par compte Mobile Money, même sans session ouverte.
- Ces soldes sont affichés comme **théoriques** et ne pré-remplissent jamais les montants d'ouverture : le comptage avec photo live reste la source de vérité.

### Sessions non clôturées

- Aucune clôture automatique : un comptage réel est un acte de contrôle, pas une donnée calculable.
- `cash:flag-unclosed-sessions` (planifié chaque matin à 06:30) détecte les sessions encore ouvertes passée leur `business_date` et alerte les détenteurs de `cash.approve-variance` de l'agence.
- `CashJournalQuery::session()` retourne la session encore ouverte même si sa date est passée, afin que l'agent puisse réaliser une **clôture tardive** (photos prises à l'instant, badge « clôture tardive » dérivé de `closed_at > business_date`).
- La **clôture forcée** (`ForceCloseCashSessionAction`, permission `cash.approve-variance` sur la même agence) ferme une session restée ouverte avec motif obligatoire, rejette ses mouvements `pending`, laisse les soldes constatés « non contrôlés », renseigne `force_closed_by`/`force_closed_at` et journalise `session_force_closed`. L'agent et le contrôle sont notifiés.

## Conséquences

Deux permissions et deux colonnes (`force_closed_by`, `force_closed_at`) sont ajoutées ; une migration additive est requise. La confidentialité inter-guichets est préservée : un agent ne voit que ses sessions, l'étendue agence reste réservée aux profils de contrôle. Les sessions zombies deviennent visibles et résolubles par l'interface, et leur traitement reste audité.

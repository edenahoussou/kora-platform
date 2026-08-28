# [OPS Web] Tarification dynamique : CRUD tarifs O/D, règles de pricing, taxes

Statut : implémentation complète, **tests écrits mais non exécutés** (voir Limites en bas). À valider en environnement PHP 8.4 avant merge.

---

## Ce qui existait déjà (rien à refaire)

- **Tarifs (Fare)** : CRUD complet, conforme au CDC (draft par défaut, aucune rétroactivité, historique horodaté).
- **`PricingRule`** (modèle) et la table `pricing_rules` : existaient, mais **aucune interface de gestion** ne les pilotait — `CalculateQuoteAction` lisait la table sans jamais évaluer les conditions.
- **Table `tax_rules`** : existait en base, aucun code applicatif dessus.

## Ce qui a été construit

### 1. CRUD `PricingRule` (`/pricing-rules`)
- Policy, Requests (Store/Update/ChangeStatus), Actions, Controller.
- Conditions **structurées** (pas de JSON libre) : `fill_rate_min`, `fill_rate_max`, `hours_before_departure_min`, `hours_before_departure_max` — couvre les deux exemples du CDC §3.1.3.4 ("remplissage > 80% et départ < 24h" / "départ > 7 jours et remplissage < 30%").
- Page frontend avec formulaire + panneau **plancher/plafond global** intégré.

### 2. CRUD `TaxRule` (`/tax-rules`) — construit de zéro
- Modèle, enums (`TaxRuleStatus`, `TaxServiceType`), Policy, Requests, Actions, Controller, page frontend.
- `validated_by` renseigné automatiquement au passage en `active` (jamais depuis l'input client).
- **Volontairement non câblé** au calcul de devis (`CalculateQuoteAction`) — conforme au "hors périmètre : aucun changement du contrat public de devis". Alimentera à terme la facturation MECeF (Fiscalization), hors périmètre de ce ticket.

### 3. Plancher/plafond global (`pricing_settings`)
- Nouvelle table à ligne unique. CDC MVP §9.2 : *"Plancher/plafond : Paramétrables par la Direction Commerciale."*
- Aucun réglage enregistré = aucun bornage (comportement par défaut sûr).

### 4. Moteur de calcul corrigé — `CalculateQuoteAction`
**Le point le plus important de ce ticket.** Avant : la règle la plus prioritaire était appliquée aveuglément, sans jamais lire `conditions`. Maintenant :
- Calcule le taux de remplissage réel du segment O/D demandé (via `BuildSeatAvailabilityAction`, sièges vendables uniquement).
- Calcule le délai avant départ (via `Departure::scheduledDepartureAt()`, déjà présent sur le modèle).
- Parcourt les règles actives par ordre de priorité et retient la **première dont les conditions matchent réellement** (une règle sans conditions s'applique inconditionnellement).
- Applique le plancher/plafond global au coefficient final.
- Snapshot enrichi (`pricing_rule_uuid`, `fill_rate_at_quote`, `hours_before_departure_at_quote`) pour traçabilité support — non exposé sur le contrat public.
- **Rétrocompatible** : le test existant `BookingQuotesAndHoldsTest.php` (`coefficient=1.1`) utilise une règle sans conditions (`conditions: []`), donc inchangé par cette réécriture.

## Bug pré-existant trouvé (non corrigé, hors périmètre)

`StoreFareRequest::passedValidation()` ajoute des erreurs à `$this->getValidatorInstance()->errors()` après coup — vérifié dans le code source Laravel (`ValidatesWhenResolvedTrait`) : `passedValidation()` ne s'exécute qu'une fois la validation déjà réussie, donc ces erreurs ajoutées après coup **ne bloquent rien**. C'est un no-op silencieux : les vérifications ligne/origine/destination de `StoreFareRequest` ne fonctionnent pas réellement. J'ai évité ce piège dans mon propre code (`UpdatePricingSettingRequest` lance une vraie `ValidationException`). À corriger séparément.

## Fichiers créés/modifiés

**Backend**
- `database/migrations/2026_08_27_090000_create_pricing_settings_table.php`
- `app/Modules/DynamicPricing/Data/{TaxRuleStatus,TaxServiceType}.php`
- `app/Modules/DynamicPricing/Models/{PricingSetting,TaxRule}.php`
- `app/Policies/{PricingRulePolicy,TaxRulePolicy}.php`
- `app/Modules/DynamicPricing/Http/Requests/{Store,Update,Change}{PricingRule,TaxRule}*Request.php`, `UpdatePricingSettingRequest.php`
- `app/Modules/DynamicPricing/Actions/{Create,Update,Change}{PricingRule,TaxRule}*Action.php`, `UpdatePricingSettingAction.php`
- `app/Modules/DynamicPricing/Http/Controllers/{PricingRuleController,TaxRuleController}.php`
- `app/Modules/DynamicPricing/Actions/CalculateQuoteAction.php` (réécrit)
- `routes/web.php`

**Frontend**
- `resources/js/pages/{pricing-rules,tax-rules}.tsx`
- `resources/js/components/pricing/{pricing-rule-form-dialog,tax-rule-form-dialog}.tsx`
- `resources/js/routes/{pricing-rules,tax-rules}/index.ts`
- `resources/js/components/kora-sidebar.tsx`

**Tests**
- `tests/Feature/KoraBus/PricingRuleManagementTest.php`
- `tests/Feature/KoraBus/TaxRuleManagementTest.php`
- `tests/Feature/KoraBus/DynamicPricingEngineTest.php` (8 cas : fill-rate, délai, priorité entre règles concurrentes, plancher, plafond)

## Vérifications effectuées

- `php -l` sur tous les fichiers PHP touchés → 0 erreur.
- `npx tsc --noEmit` sur tout le projet → 0 erreur.
- `npx eslint .` sur tout le projet → 0 erreur (2 trouvées et corrigées).
- `npx prettier --check` → conforme après `--write`.
- **Build Vite complet réussi** (`pricing-rules-*.js` 21.2 kB, `tax-rules-*.js` 15.0 kB générés sans erreur), après neutralisation temporaire du plugin Wayfinder et de la police distante (réseau sandbox restreint) — `vite.config.ts` restauré à l'identique ensuite.

## Non vérifié — à faire avant merge

**`php artisan test` n'a jamais pu être exécuté** dans cet environnement (PHP 8.4 requis, sandbox limité à 8.3). Priorité :
```bash
php artisan migrate
php artisan test --filter=PricingRuleManagementTest
php artisan test --filter=TaxRuleManagementTest
php artisan test --filter=DynamicPricingEngineTest
php artisan test --filter=BookingQuotesAndHoldsTest  # non-régression
```
Puis côté frontend : `php artisan wayfinder:generate --with-form` pour régénérer proprement les fichiers de routes écrits à la main.

## Décisions prises sans confirmation explicite (à valider)

1. **Conditions à 4 champs structurés** plutôt que les 3 du CDC — ajout de `fill_rate_max` en plus de `fill_rate_min` pour couvrir symétriquement les deux exemples du CDC.
2. **Plancher/plafond global** (une seule valeur pour toute la plateforme) plutôt que par règle — le CDC parle de bornes "×1,0 min / ×4,0 max" comme limites globales.
3. **Permissions `pricing.view`/`pricing.manage` réutilisées pour `TaxRule`** — le CDC suggère que les paramètres fiscaux DGI/MECeF pourraient relever d'une permission plus restreinte (Finance/DAF), mais le ticket regroupe les 3 volets sous un même ticket "Tarification dynamique", donc j'ai gardé la même permission plutôt que d'inventer une nouvelle taxonomie sans confirmation.

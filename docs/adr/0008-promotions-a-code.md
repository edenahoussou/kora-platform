# ADR 0008 — Promotions commerciales à code

## Statut

Accepté — 18 septembre 2026

## Contexte

Le réseau commercial doit piloter des campagnes de remise ciblées (code saisi par le voyageur ou l’agent) distinctes de la tarification dynamique, qui ajuste les tarifs automatiquement sans action de l’utilisateur, et de la remise groupe, qui récompense le volume de passagers. MEZ-73 exige un périmètre d’application extensible (réseau, canal, ligne, segment O/D), un cycle de vie administrable, la détection des conflits de période et de priorité, et un montant de remise cohérent avec le moteur de tarification.

## Décision

KORA conserve un modèle `Promotion` dédié dans le module DynamicPricing. Une promotion ne s’applique que lorsqu’un `promo_code` est saisi au moment du devis ; le code est obligatoire, unique parmi les promotions non supprimées et normalisé en majuscules. Un seul code peut être appliqué par devis.

L’avantage est soit un pourcentage, soit un montant fixe en FCFA. Le pourcentage s’applique à chaque jambe éligible de l’itinéraire ; le montant fixe est déduit une seule fois du total de la réservation, jamais par jambe.

L’ordre de calcul du devis est : tarif de base → ajustement de tarification dynamique → remise promotion → remise groupe. La remise groupe se calcule donc sur le sous-total post-promotion et les deux mécanismes restent cumulables.

Le périmètre `channel` couvre les familles de canaux de vente : `app` regroupe `mobile` et `online`, `counter` regroupe `counter` et `ops`.

Les conflits — même périmètre, même priorité, périodes qui se chevauchent — sont rapportés avant enregistrement comme un avertissement confirmable, jamais comme un blocage : des chevauchements légitimes existent (promotion réseau coexistant avec une promotion ligne).

Le devis tarifaire fige la promotion appliquée et son montant (`promotion_id`, `promotion_discount_amount` et le snapshot `promotions` du breakdown). La rédemption n’est enregistrée que lorsque la réservation passe à l’état payée, jamais au devis ni au blocage de siège ; elle est idempotente par couple promotion/réservation et décrémente la capacité `max_uses` sous verrou de ligne.

## Conséquences

- Le contrat de devis expose `promotions[]` et `promotionDiscountAmount` dans `PricingBreakdownData` ; les clients API/guichet lisent le montant déjà calculé, sans recalcul.
- Le champ `promo_code` est accepté par le devis itinéraire API et par le flux de vente guichet ; l’interface passager Flutter reste hors périmètre de ce ticket.
- Les permissions `pricing.view` / `pricing.manage` contrôlent respectivement la consultation et l’administration des promotions.
- Les états « planifiée » et « expirée » sont dérivés de la période à l’affichage ; aucune transition d’état programmée n’est nécessaire.

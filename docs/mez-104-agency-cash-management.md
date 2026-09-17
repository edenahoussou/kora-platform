# MEZ-104 — Gestion structurée des caisses d’agence

## Modèle

Une agence possède un ou plusieurs `CashRegister` de type `counter` et un
`CashRegister` de type `vault` créé automatiquement. Une caisse guichet est le
tiroir physique stable d’un guichet ; une session est un cycle d’ouverture et
de clôture rattaché à ce tiroir. Le coffre local a son propre journal, mais ne
possède pas de session de guichet permanente.

Le total espèces agence est la somme des soldes publiés des caisses guichet et
du coffre local. Un transfert guichet → coffre produit deux écritures liées et
ne change pas ce total. Un reversement vers KORA Direction produit une sortie
externe et diminue le total. Un financement Direction → coffre produit une
entrée externe et augmente le total.

Les comptes MTN MoMo, Moov Flooz et Celtiis Cash restent des comptes agence
partagés. Leur solde ne doit être additionné qu’une fois par compte, même si
plusieurs sessions ont utilisé le compte comme sous-journal d’audit.

## Écritures et transferts

`CashMovement` est le journal permanent. `cash_session_id` et
`cash_ledger_id` sont facultatifs pour permettre les opérations du coffre ; une
vente guichet conserve ces références, ainsi que `cash_register_id`.

`CashTransfer` est l’agrégat d’intention et de validation. Tant qu’un transfert
est `pending`, il ne modifie pas le solde publié mais réserve le montant sur la
caisse source. À l’approbation, les écritures liées sont créées dans une seule
transaction. Les sorties sont refusées lorsque le disponible est insuffisant
et les transferts guichet → guichet sont interdits.

Les champs financiers d’une écriture publiée (`posted` ou `approved`) ne sont
pas modifiables. Une correction doit être représentée par une contrepassation
ou un ajustement approuvé. La désactivation est refusée pour une caisse avec
une session ouverte, un solde non régularisé ou une réservation en attente.

## Routes web

- `GET /agencies/{agency}` ouvre la fiche agence par UUID.
- `POST /agencies/{agency}/cash-registers` crée un guichet et ses affectations.
- `PUT /cash-registers/{cashRegister}` modifie son libellé et les agents autorisés.
- `PATCH /cash-registers/{cashRegister}/status` active ou désactive une caisse.
- `POST /cash/open` ouvre une session sur une caisse autorisée via
  `cash_register_uuid`.
- `POST /cash/transfers` crée une opération en attente.
- `POST /cash/transfers/{cashTransfer}/approve` publie ses écritures.

Les autorisations sont séparées : `agencies.view` + `cash.view` pour la
supervision, `agencies.update` pour l’administration des caisses,
`cash.open`/`cash.close` pour les sessions et
`cash.transfer.create`/`cash.transfer.approve` pour les transferts.

## Compatibilité des données

Les migrations ajoutent les références de caisse sans supprimer les données
existantes. Les sessions historiques sans caisse reçoivent une caisse
historique inactive ; les agences existantes reçoivent un coffre local. Une
ancienne requête d’ouverture sans UUID de caisse conserve un chemin de
compatibilité vers une caisse historique, tandis que l’interface actuelle
sélectionne toujours une caisse guichet explicite.

## Validation locale

La couverture MEZ-104 est volontairement sans Chromium ni rendu PDF :

```bash
php artisan migrate:fresh --seed
php artisan test --compact tests/Feature/KoraBus/AgencyCashManagementTest.php
php artisan test --compact tests/Feature/KoraBus/CashManagementTest.php
php artisan test --compact tests/Feature/KoraBus/CounterSalePaymentTest.php
vendor/bin/pint --dirty --format agent
composer test
npm run types:check
npm run lint:check
npm run build
```

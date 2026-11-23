# Recette MTN MoMo — application passager (sandbox)

Validation du parcours de paiement MTN MoMo depuis **KORA App Passenger**,
en complément de la recette back-end
[MEZ-174](https://linear.app/mezabodev/issue/MEZ-174/paiements-finaliser-et-valider-lintegration-directe-mtn-momo-en).

La validation back-end ne suffit pas : elle dit que l'API répond, pas que
le client voit un état juste au bon moment. Cette fiche couvre ce que le
back ne peut pas montrer — ce qui s'affiche, quand, et ce qui se passe
quand le voyageur ferme l'app au milieu.

| | |
|---|---|
| Portée | App passager uniquement, environnement **sandbox** |
| Interdit | Tout paiement de production, tout secret recopié ici |
| Dépend de | MEZ-174 (back-end), compte sandbox MTN MoMo |

---

## 1. Ce que fait l'app avec MTN MoMo

À connaître avant de tester : l'app ne décide de rien, elle suit le
catalogue que le serveur lui envoie.

**Le catalogue** — `GET /payments/methods` annonce `mtn_momo` seulement
si le back le juge disponible : réglage activé **et** les trois
identifiants renseignés (clé d'abonnement, identifiant d'API, clé d'API).
Si l'un manque, la passerelle ne s'affiche pas dans l'app. Un pavé absent
est donc d'abord une question de configuration, pas un défaut applicatif.

**Le pavé** — MTN MoMo arrive avec `interaction: push` et
`requires: [phone, mobile_money_network]`. L'app ouvre donc l'écran
*Mobile Money*, qui réclame l'opérateur et le numéro **avant** l'appel,
contrairement à KKiaPay qui les recueille dans sa propre feuille. Le seul
réseau annoncé pour cette passerelle est `mtn_momo` : une seule vignette.

**L'attente** — après `POST /payments/initiate`, l'app pousse l'écran
d'attente et interroge le serveur toutes les **10 s**
(`polling_interval_seconds`). Elle ne conclut jamais d'elle-même : c'est
`POST /payments/{uuid}/verify` qui tranche. Les deux boutons
(*Actualiser*, *Signaler*) n'apparaissent qu'une fois le délai de
l'opérateur dépassé.

**L'abandon** — le bouton *Abandonner* appelle
`POST /payments/{uuid}/cancel`. Un abandon qui échoue ne retient pas le
voyageur : l'app le laisse repartir et le serveur fera le ménage.

**La reprise** — au lancement, le parcours redemande au serveur l'état du
paiement laissé en attente. Un filtre vérifie que ce paiement concerne
bien la réservation en cours : sans lui, un vieux paiement pilotait
l'écran d'une nouvelle réservation.

---

## 2. Vérifications préalables

À faire **avant** de lancer l'app, avec le responsable back-end. Trois
pièges connus, lus dans le code côté serveur.

### 2.1 Le ciblage d'environnement

`mtnMomoEnvironment` vaut `sandbox` par défaut — correct. Mais
`mtnMomoTargetEnvironment` vaut **`mtnbenin`** par défaut, et c'est cette
valeur qui part dans l'en-tête `X-Target-Environment`. Le bac à sable de
MTN attend `sandbox`. Avec le défaut, `requestToPay` échoue avant même
d'atteindre un portefeuille de test.

> **À confirmer** : `X-Target-Environment = sandbox` tant que
> `mtnMomoEnvironment = sandbox`.

### 2.2 La devise et le montant

En sandbox la devise est **EUR**, en production **XOF** — c'est une
exigence du protocole MTN, pas un taux de change. Le back **ne convertit
pas** : il envoie le même nombre dans l'autre devise. Un trajet à
9 000 XOF part donc comme « 9000 EUR » vers le bac à sable.

C'est délibéré et documenté dans le code. À vérifier malgré tout que
l'app affiche bien **9 000 FCFA** au voyageur du début à la fin : le
montant d'affichage et le montant protocolaire ne doivent jamais se
croiser.

### 2.3 Le rappel (webhook)

MTN ne signe pas ses rappels. Le back les authentifie par un jeton
partagé dans l'en-tête `X-Kora-Webhook-Token`, configuré côté MTN. Si le
secret n'est pas renseigné, le back journalise un avertissement et
**ignore le rappel** — le parcours ne tient alors que sur la relecture
toutes les 10 s.

> **À confirmer** : secret de rappel renseigné, et URL de rappel
> joignable depuis l'extérieur. Sinon, consigner que la recette s'est
> faite en relecture seule.

### 2.4 « Omobile »

Le mot **n'apparaît nulle part dans le dépôt** — ni dans l'app, ni dans
le back, ni dans la configuration. Le code ne connaît que
`mtn_momo` et l'API Collection de MTN en direct.

> **À clarifier avec le responsable back-end avant la recette** : Omobile
> désigne-t-il le compte marchand MTN, un agrégateur en amont, ou le
> canal de test opérateur ? La réponse change ce qu'on vérifie, et ce
> qu'on saura conclure d'un refus.

---

## 3. Préparation de l'appareil

| | |
|---|---|
| Appareil | Infinix X688B — Android 11 |
| Build | `app-release.apk`, `flutter build apk --release -t lib/main_staging.dart` |
| Version app | `1.0.0+13` |
| Environnement | staging — `staging.koraexpress.app` |
| Date / heure | 2026-11-21, 13:11 |
| Testeur | NASSARA Kévine |

Vérifier avant de commencer que le pavé **MTN MoMo** apparaît bien sur
l'écran des moyens de paiement. S'il est absent, s'arrêter là : c'est la
configuration back (§ 2) qu'il faut régler d'abord.

### 3.1 Constat du 21/11/2026 — recette bloquée à cette étape

**MTN MoMo n'est pas proposé par le staging.** Le catalogue
`GET /payments/methods` n'annonce que trois moyens :

| Moyen | Encaisseur |
|---|---|
| Mobile Money | FedaPay |
| Mobile Money | KKiaPay |
| Carte bancaire | KKiaPay |

Relevé depuis l'app, écran *Moyen de recharge* (Wallet → Recharger →
2 000 FCFA), qui lit le même catalogue que l'achat d'un billet. L'écran
n'applique aucun filtre qui lui soit propre : il affiche les encaisseurs
que le serveur marque actifs. L'absence vient donc du serveur, pas de
l'app.

**Ce que l'app ne peut pas dire** : le back n'annonce `mtn_momo` que si
le réglage est activé **et** que les trois identifiants sont renseignés —
clé d'abonnement, identifiant d'API, clé d'API. Un seul manquant suffit à
faire disparaître la passerelle, exactement comme une désactivation. Il
faut la page **Administration → Paramètres → Intégrations** pour
trancher entre les deux.

**Demandé au responsable back-end** (MEZ-174) :

1. Activer MTN MoMo sur le staging avec les identifiants sandbox.
2. Mettre `X-Target-Environment` sur `sandbox` (§ 2.1) — le défaut
   `mtnbenin` ferait échouer `requestToPay` avant tout portefeuille de
   test.
3. Renseigner le secret de rappel, ou prévenir que la recette se fera en
   relecture seule (§ 2.3).
4. Dire ce que désigne « Omobile » (§ 2.4).
5. Communiquer les numéros de test qui forcent succès / refus /
   expiration (§ 4).

Tant que le pavé n'apparaît pas, **aucun scénario du § 4 n'est
exécutable**. Ce n'est pas un défaut applicatif : rien dans l'app ne
reste à corriger pour que la passerelle s'affiche.

---

## 4. Scénarios

Le bac à sable MTN ne fait apparaître **aucune demande sur un téléphone** :
il n'y a pas de fenêtre opérateur à valider. Le sort de la transaction est
décidé par le **numéro du payeur** envoyé dans `requestToPay`. C'est la
différence la plus importante avec la production, et elle doit être dite
dans le compte rendu : « confirmer sur le canal opérateur de test » n'a
pas d'équivalent ici.

Les numéros de test qui forcent chaque issue sont à **relever lors de la
première exécution** et à faire confirmer par le responsable back-end :
les sources publiques se contredisent, et un jeu de numéros inventé
donnerait une recette fausse. Noter le numéro utilisé et le statut obtenu
dans le tableau ci-dessous — c'est cette table qui fera référence
ensuite.

### 4.1 Nominal — paiement confirmé

1. Composer une réservation jusqu'à l'écran des moyens de paiement.
2. Choisir **MTN MoMo**, puis l'opérateur MTN et un numéro de test.
3. Appuyer sur *Payer*.
4. Rester sur l'écran d'attente.

À vérifier :

- [ ] L'app passe à l'attente sans erreur, montant **en FCFA** correct.
- [ ] L'état final arrive **tout seul**, sans appuyer sur *Actualiser*.
- [ ] L'app affiche le succès, et le billet/la réservation existe.
- [ ] **Une seule** mise à jour métier : un seul billet, un seul
      mouvement, pas de double débit côté back.
- [ ] Le statut app, le statut back et le statut opérateur concordent.

### 4.2 Refus

- [ ] L'app affiche l'échec, pas une attente qui ne finit jamais.
- [ ] Le message distingue un refus du payeur d'une panne.
- [ ] La réservation reste reprenable : on peut repayer sans tout refaire.
- [ ] **Point de vigilance** : le back traduit `REJECTED`, `TIMEOUT` et
      `CANCELLED` en états distincts, mais MTN ne renvoie peut-être que
      `SUCCESSFUL`, `FAILED` ou `PENDING` dans `status`, le détail
      partant dans `reason`. Si c'est le cas, une annulation sera
      rapportée comme un échec. **Relever le JSON exact et le remonter à
      MEZ-174.**

### 4.3 Annulation depuis l'app

- [ ] *Abandonner* appelle bien `cancel` et rend la main.
- [ ] Le paiement abandonné ne revient pas piloter un écran plus tard.
- [ ] Un nouveau paiement sur la même réservation repart proprement.

### 4.4 Attente prolongée

- [ ] Les boutons *Actualiser* / *Signaler* n'apparaissent **qu'après**
      le délai, pas pendant les vingt secondes ordinaires.
- [ ] *Actualiser* interroge réellement l'opérateur et ne décide rien
      seul.
- [ ] Le texte rassure sur le siège retenu au lieu de laisser croire à
      une perte.

### 4.5 Fermeture et réouverture

1. Lancer un paiement, puis **tuer l'app** pendant l'attente.
2. La rouvrir.

- [ ] L'app retrouve le paiement en cours et reprend le suivi.
- [ ] Elle ne relance **pas** un second paiement.
- [ ] Si le paiement a abouti entre-temps, elle affiche le succès.
- [ ] Un paiement repris d'**une autre** réservation ne pilote pas
      l'écran en cours.

---

## 5. Relevé

| # | Scénario | Numéro de test | Statut MTN | Statut back | Écran app | Verdict |
|---|---|---|---|---|---|---|
| 1 | Nominal | | | | | |
| 2 | Refus | | | | | |
| 3 | Annulation app | | | | | |
| 4 | Attente prolongée | | | | | |
| 5 | Fermeture/reprise | | | | | |

**Anomalies constatées** — une par entrée : ce qu'on attendait, ce qu'on a
eu, les étapes exactes pour le reproduire, et de quel côté se situe
l'écart (app / back / compte MTN).

| # | Ce qu'on attendait | Ce qu'on a eu | Reproduction | Côté |
|---|---|---|---|---|
| | | | | |

**Limites du sandbox rencontrées** — tout scénario que le bac à sable ne
permet pas de jouer se consigne ici plutôt que de rester en blanc dans le
tableau.

---

## 6. Règles de compte rendu

- Aucun secret : ni clé d'abonnement, ni identifiant d'API, ni clé
  d'API, ni jeton de rappel — pas même tronqués.
- Aucun numéro personnel. Seuls les numéros de test MTN sont cités.
- Aucune donnée de paiement réelle, aucun identifiant de transaction de
  production.
- Les captures d'écran sont relues avant d'être jointes : l'app affiche
  le numéro du payeur sur l'écran Mobile Money.
- Un écart entre l'app, le back et l'opérateur se signale
  **immédiatement** sur MEZ-174, sans attendre la fin de la recette.

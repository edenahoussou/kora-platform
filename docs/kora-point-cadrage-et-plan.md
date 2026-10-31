# KORA POINT — analyse produit et préparation du plan d’implémentation

Date : 31 octobre 2026.

Statut : plan backend approuvé, exécution suivie par [MEZ-156](https://linear.app/mezabodev/issue/MEZ-156), assigné à Eden Ahoussou. Branche `edenahoussou/mez-156-backend-kora-points`. Les décisions définitives ci-dessous remplacent les propositions et questions encore ouvertes dans l'analyse historique des sections suivantes. Aucun frontend ni déploiement n'est inclus.

## Décisions définitives et contrat de livraison MEZ-156

- Point autonome par défaut ; organisation partenaire facultative. Commercial/Admin habilité crée l'organisation et ses Points dans une transaction ; l'affectation des utilisateurs personnels exige aussi `users.manage` et remplace le rôle actif.
- Profil `KoraPoint` associé à une `Agency` de type `partner`. L'agence interne reste distincte ; l'implantation ne devient ni un arrêt ni le propriétaire d'un départ. Réutilisation de la vente, de la caisse d'encaissement, de l'inventaire et de l'émission de billets Bus.
- Héritage Point → organisation → exploitation pour commission, plafond et calendrier. Valeurs contractuelles initialement non renseignées, ventes refusées tant que les trois conditions nécessaires ne sont pas configurées. Saisie du taux en points de base : 300 = 3 %, 0 explicite, null = héritage. Calendriers quotidien, hebdomadaire ou mensuel avec heure et fuseau ; jour mensuel 1 à 28 pour une échéance présente chaque mois. Délai de grâce d'exploitation initial à zéro, administrable.
- Commission sur le prix payé après remises, arrondi moitié supérieure en FCFA ; conditions et attribution figées au paiement. Encours net par Point, contrôle sous verrou, égalité au plafond admise, dépassement refusé, aucun effacement mensuel.
- Responsable organisation en lecture seule ; trésorier pour les bordereaux groupés. Répartition explicite par vente entière. Préparation, preuve privée, soumission et contrôle distincts ; seule réception validée Finance libère l'encours. Écart à rapprocher et interdiction de valider son propre bordereau.
- Annulation autorisée centralement, restitution par le Point avant règlement ou par KORA après. Une vente affectée à un bordereau est verrouillée contre un remboursement jusqu'au rapprochement/rejet. Preuve, référence, bénéficiaire initial et moyen initial exigés. Confirmation séparée ; correction proportionnelle de la commission, reprise après règlement dans le compte partenaire. Pas de simulation Wallet. Les annulations partielles utilisent les allocations tarifaires gelées.
- Le journal partenaire est append-only. Il suit la dette de reversement, distincte des journaux d'encaissement guichet et d'un relevé bancaire. Historique financier et affectations de vente ne sont pas recalculés après changement d'organisation.
- GPS facultatif ; pièces et convention privées consultables sans condition documentaire obligatoire d'activation. Les métadonnées de convention (référence, signature, expiration) sont portées par les documents de type `agreement` ; aucune table de contrats supplémentaire sans comportement propre.
- Routage web dédié `routes/kora-points.php`, session/CSRF, UUID, FormRequests, redirections et flash Inertia. Pas de nouvelle API REST. Les réponses de consultation définissent les contrats de props pour les futurs composants `kora-points/*`. Ces écrans sont volontairement absents de ce lot backend et les pages ne sont pas navigables avant le lot frontend.
- Rappels par job après commit, dédupliqués par vente/jour et livraison persistée. Blocage financier calculé à chaque paiement, distinct des suspensions manuelles. Les rappels n'écrivent aucun mouvement financier.
- Affectation d'un utilisateur à un Point ou une organisation : remplace le rôle actif unique, mais refuse tout compte porteur d'un rôle interne non-partenaire tant qu'il n'a pas été désaffecté explicitement.
- La vente porte un statut lisible `open` (encours non soldé), `settled` (soldée) ou `reopened` (dette rouverte après règlement) ; `settled_at` reste le marqueur fonctionnel « a été réglée » pour le choix de l'exécuteur.
- Une autorisation de remboursement erronée peut être rejetée depuis l'état `authorized` : la demande est clôturée, l'obligation `PaymentRefund` est soldée en échec, l'annulation de la réservation reste acquise et la vente redevient réglable. Aucun rejet après restitution déclarée ou confirmée.
- Le bénéficiaire d'une restitution est dérivé du paiement source (`payer_phone`/`payer_name`), jamais saisi par l'exécutant ; seuls le moyen initial et la référence sont déclarés. Une réservation partenaire ne peut pas être confirmée par un callback de provider : elle se règle au guichet.

### Mise en service

Appliquer les migrations applicatives et Settings, synchroniser les rôles avec `php artisan db:seed --class=KoraBusRolePermissionSeeder --force`, configurer les conditions contractuelles, créer les Points/organisations et affecter les comptes via IAM et les routes du module. Maintenir scheduler et workers. Raccorder ensuite les écrans et effectuer la recette matérielle et opérationnelle avant toute ouverture aux partenaires. Aucun seed n'active automatiquement La Poste ni de taux contractuel par défaut.

### Validation attendue

Tests backend financiers et HTTP sur SQLite, migrations/seeders et invariants sur PostgreSQL, test dédié de deux ventes concurrentes sous le même plafond dans une base temporaire `kora_test_*`, puis gates du dépôt. Le test de concurrence est `tests/Unit/KoraPointPostgresConcurrencyTest.php` et se lance via `bash scripts/test-pgsql.sh tests/Unit/KoraPointPostgresConcurrencyTest.php` avec les identifiants de test fournis par l'environnement. Il ne s'exécute pas sur une base ordinaire.

---

Analyse historique et sources du cadrage :


## 1. Avis produit

Le besoin exprimé pour La Poste du Togo concorde avec la vocation de **KORA POINT VOYAGE** : distribuer les billets KORA au public depuis un réseau de lieux partenaires, au moyen de KORA OPS et du même inventaire Bus.

Il s’agit d’une extension du périmètre initial du MVP, explicitement exclue du cahier MVP Bus. L’avance annoncée sur le calendrier peut permettre d’envisager cette extension ; elle ne démontre pas que le module complet peut être livré et qualifié avant le démarrage de l’activité annoncé dans trois jours.

Le principal écart entre la capture et le cahier complet est la dimension institutionnelle et multi-sites du partenaire. La Poste du Togo est une organisation ; ses bureaux sont des lieux de vente ; leurs agents sont des utilisateurs. Le cahier complet décrit surtout un partenaire/point physique avec sa commission et son solde. Il ne définit pas explicitement une tête de réseau partenaire supervisant plusieurs points.

Décision confirmée : préparer une **V1 KORA POINT VOYAGE exploitable en production**, avec un partenaire, ses bureaux et des comptes personnels. Le périmètre comprend l'isolation des accès, la supervision partenaire en lecture seule et une chaîne financière vérifiable. La liste des bureaux à activer reste à fournir ; aucune limitation à un nombre de bureaux n'est présumée. La recette en sandbox prépare une mise en service réelle.

## 2. Sources examinées et portée des conclusions

| Référence | Source | Apport |
| --- | --- | --- |
| S1 | `docs/adr/FULL CDC KORA.docx`, lu intégralement | Vision globale ; profils §2 ; gestion §4.3 ; KORA POINT §5 ; zones §6 ; colis §7 ; terminal §10B ; finance/rentabilité §14 ; données §15. |
| S2 | `docs/adr/KORA_CDC_MVP_BUS_V1_1.docx`, lu intégralement | Exclusion explicite de KORA POINT au §2.2 ; règles du socle Bus ; phase 2 au §18. |
| S3 | `docs/adr/KORA_PLAN_REALISATION_MVP_V1_1.docx`, lu intégralement | Gouvernance des changements au §9 ; KORA POINT en phase 2 au §14. |
| S4 | Capture WhatsApp fournie dans le chat | Demande du client : créer des KORA Points, utiliser l’application web dans plusieurs bureaux de La Poste du Togo pour vendre des billets ; demande de documentation/formation. |
| S5 | `CONTEXT.md`, ADR 0003, ADR 0005 et ADR 0015 | Vocabulaire actuel, périmètre initial Bus, agence commerciale distincte des arrêts/départs, attribution historique des ventes, commissions Point non livrées dans la source rentabilité. |
| S6 | Code de KORA OPS Web et tests existants examinés en lecture seule | Faisabilité à partir du socle actuel ; absences et adaptations nécessaires. |

S1 présente des incohérences de version internes : titre « version 6.0 », fiche « version 5.0 ». S3 porte un titre et une baseline Bus + Colis + Wallet Lite, en référence à un CDC 1.2, contrairement à S2 centré Bus. S3 ne doit donc pas être lu comme une description parfaitement synchronisée du périmètre exécuté. Sur KORA POINT, S2, le tableau de trajectoire de S3 et l’ADR 0003 convergent toutefois vers un report hors socle initial. S3 mentionne aussi une éventuelle activation Point dans le flux Colis, conditionnelle et non équivalente à un engagement de réalisation du réseau partenaire.

Les extensions acceptées depuis le cadrage initial doivent être lues dans leurs décisions propres : par exemple, l’exclusion initiale du Wallet a ensuite évolué. Cette analyse ne rétablit pas les anciennes exclusions sur ces autres sujets.

La présence en sandbox et production ainsi que l’échéance d’activité sont des informations déclarées par le porteur du projet. L’analyse n’est pas une vérification de l’environnement de production. Les accords de partenariat n’ont pas été fournis parmi les pièces examinées ; leurs modalités financières restent à établir avec le client. Les instructions et règles des documents sont traitées comme des exigences à analyser, sans exécution des actions qu’ils décrivent.

## 3. Vision, objectif et portée dans le cahier complet

### 3.1 Vision

KORA POINT est un réseau de distribution partenaire. Il élargit la présence commerciale de KORA en permettant à des boutiques ou agences partenaires de vendre ses services. Il permet notamment d’acheter un billet sans utiliser personnellement l’application passager, avec impression ou code court.

Un Point utilise les tarifs et le stock de sièges communs au réseau. Il ne possède pas son propre stock indépendant et ne définit pas librement les prix du transport.

### 3.2 Deux offres distinctes

| Offre | Périmètre du cahier complet | Conséquence pour le besoin actuel |
| --- | --- | --- |
| KORA POINT VOYAGE | Billets, réservations, informations et code court ; présence régulière et connexion internet. | Correspond à la vente de billets demandée par La Poste. |
| KORA POINT COLIS | Tout VOYAGE, plus réception, stockage et remise de colis ; exigences matérielles, horaires et conditions géographiques. | Ne découle pas de la capture. Nécessiterait le module Colis et ses responsabilités propres. |

Les règles d’implantation liées à un arrêt KORA ou à une RNIE concernent explicitement le Point COLIS. Elles ne doivent pas devenir automatiquement une restriction de vente de billets pour les bureaux postaux. La désignation d’un « Point principal » par ville/ligne apparaît dans le même ensemble de règles et doit être précisée si son usage est souhaité pour VOYAGE.

### 3.3 Fonctionnement commercial et financier prévu

- Fiche du point : identité, ville, pays, responsable, contacts, type, zone, statut, commission et plafond.
- Vente depuis KORA OPS ; le web figure dans les profils, le mobile/P501 figure dans les parcours et l’équipement.
- Commission billets indiquée à 3 %, paramétrable par point.
- Plafond initial indiqué à 150 000 FCFA, révisable selon l’historique du partenaire.
- Consultation des ventes propres, commissions et sommes à reverser.
- Reversements, alertes, blocage de nouvelles ventes et suspension selon l’encours et les retards.
- Pilotage commercial du réseau et suivi financier par les rôles KORA autorisés.
- Accompagnement par un responsable de zone : visites, formation sur place, contrôle du point, PLV et prospection.

Les valeurs du cahier sont des références de conception à confronter à l’accord La Poste. Elles ne sont pas des modalités contractuelles établies pour ce partenaire.

### 3.4 Droits différenciés

Le profil KORA POINT peut vendre, imprimer et consulter ses ventes, sa commission et son solde. Le cahier lui interdit la modification des tarifs, la consultation des autres points ou des données globales et l’annulation des billets.

Le porteur du projet confirme un responsable La Poste en lecture seule sur les bureaux de son organisation dès la V1. Il s'agit d'une extension explicite à la visibilité par point du cahier complet. Ce rôle ne donne aucun accès aux autres partenaires, aux données globales KORA ou à la modification des conditions commerciales.

## 4. Différence avec une agence KORA

| Dimension | Agence KORA actuelle | KORA Point dans la vision cible |
| --- | --- | --- |
| Nature | Entité commerciale interne KORA. | Lieu de distribution exploité par un partenaire externe. |
| Vente Bus | Vente sur le stock et aux tarifs KORA. | Même stock et mêmes tarifs KORA. |
| Acteurs | Agents et responsables internes. | Agents du partenaire ; supervision KORA et responsable partenaire en lecture seule confirmé pour la V1. |
| Argent | Sessions de caisse, comptes d’agence, contrôles internes. | Encaissement et règlement dépendant de l’accord ; possibilité d’une dette du partenaire envers KORA. |
| Rémunération | Le socle de l’agence ne calcule pas une commission de revendeur. | Commission contractuelle et suivi de son règlement. |
| Contrôle | Périmètre personnel, agence ou réseau KORA selon les droits. | Périmètre Point ; consolidation du seul partenaire à concevoir si nécessaire. |
| Relation commerciale | Organisation interne. | Convention, plafond d’exposition, échéances, suspension et clôture partenaire. |

Les deux partagent un parcours de vente ; leur statut commercial et leur relation financière diffèrent. Réutiliser des mécanismes d’agence est envisageable, mais ouvrir simplement des comptes avec les rôles internes actuels ne couvre pas le besoin partenaire.

Une agence ou un Point est un lieu commercial. Un arrêt est un lieu d’embarquement. Le bureau où le client achète son billet n’est pas automatiquement son lieu de départ. Aucun bus, ligne ou départ ne doit devenir la propriété structurelle d’un bureau postal.

Le compte entreprise existant répond à un autre besoin : acheter des voyages pour une organisation et ses voyageurs, éventuellement à crédit. La Poste distribuant des billets au public joue le rôle de revendeur ; le portail entreprise ne doit pas être détourné pour cela.

## 5. Concordance avec la demande du client

| Élément de la capture | Lecture produit | Concordance |
| --- | --- | --- |
| Créer des KORA Points | Activer le réseau de revendeurs. | Conforme à la vision globale, extension du MVP initial. |
| Utiliser le web | Fournir un accès KORA OPS aux vendeurs partenaires. | Conforme aux profils du cahier ; le périmètre exact d’impression reste à choisir. |
| Vendre depuis plusieurs bureaux de La Poste du Togo | Distribuer les billets dans un réseau existant. | Conforme à l’objectif, avec une dimension multi-sites supplémentaire à cadrer. |
| Relier les bureaux comme utilisateurs à un KORA Point La Poste | Représenter une organisation, plusieurs lieux et leurs opérateurs. | Intention compréhensible ; modélisation trop ambiguë pour l’attribution et les droits. |
| Formation/documentation web | Permettre une exploitation réelle par de nouveaux vendeurs. | Besoin opérationnel nécessaire ; le cahier prévoit de la formation terrain, pas explicitement une plateforme complète d’apprentissage. |

La capture ne fixe ni le taux de commission, ni le détenteur des encaissements, ni les comptes de collecte, ni la fréquence des règlements, ni le nombre de bureaux, ni un droit de supervision consolidée. Elle ne demande pas explicitement Colis, visites terrain ou application partenaire distincte.

## 6. Modèle métier confirmé et vocabulaire restant à préciser

```text
Partenaire : La Poste du Togo
  ├── KORA Point VOYAGE : bureau A
  │     ├── Utilisateur : agent A1
  │     └── Utilisateur : agent A2
  └── KORA Point VOYAGE : bureau B
        └── Utilisateur : agent B1

Responsable partenaire → lecture seule des bureaux de La Poste
Supervision KORA → périmètre attribué aux rôles internes autorisés
```

Vocabulaire confirmé pour l'organisation de la V1 :

- **Partenaire distributeur** : organisation avec laquelle KORA convient de la distribution de ses billets.
- **KORA Point** : lieu physique habilité de ce partenaire, identifié et localisé.
- **Utilisateur partenaire** : personne identifiée autorisée à opérer pour un Point.
- **Responsable partenaire** : personne habilitée à consulter en lecture seule les Points de son organisation.
- **Encours à reverser d'un Point** : dette de règlement issue des encaissements détenus par le partenaire et des commissions, règlements et corrections justifiés ; distincte de la recette nette.

Vocabulaire encore proposé pour les décisions suivantes :

- **Commission acquise** : rémunération calculée selon la convention et son événement d’acquisition, conservée avec la vente.

Les définitions des acteurs et de l'encours par Point sont intégrées à `CONTEXT.md`. Les règles de changement de site, les champs visibles dans la supervision et les modalités financières restent à préciser. Cette validation du modèle métier ne choisit pas encore sa représentation en base.

## 7. Ambiguïtés du cahier à arbitrer

| Sujet | Ambiguïté constatée | Recommandation à discuter |
| --- | --- | --- |
| Plafond | Intitulé « plafond vente mensuel », mais blocage déclenché par le solde à reverser, libéré après paiement. | Plafond d'encours par Point confirmé ; montant à configurer. La dette reste due au changement de mois. |
| Seuil de blocage | §4.3 parle de dépassement ; §5.3 annonce le blocage à 100 %. | Définir si une nouvelle vente est refusée lorsqu’elle conduirait à dépasser la limite, et le traitement du montant exactement égal. |
| Commission | Net à reverser = ventes − commissions ; autre champ décrit la réception hebdomadaire des commissions. | Commission retenue sur les recettes confirmée. Taux configurable ; hiérarchie et corrections à préciser ; aucun second versement de cette même commission. |
| Retards | J+2, J+5 et J+15 sans origine temporelle suffisamment précise. | Définir une échéance de règlement et calculer le retard depuis cette échéance. |
| Gouvernance | Direction commerciale autorisée à modifier les commissions dans les profils ; ADMIN désigné ailleurs. | Fixer qui prépare, approuve et active les conditions. |
| Point multi-sites | Pas de structure explicite organisation → bureaux → agents. | Modèle partenaire → Points physiques → agents personnels confirmé. La représentation technique reste à choisir. |
| Clôture | Clôture automatique à J+15 ; clôture manuelle attribuée au commercial ailleurs. | Séparer blocage des ventes, suspension et fin de relation ; conserver dettes et historique dans tous les cas. |
| Annulation | Interdite au Point, sans détail de correction de commission et de règlement. | Centraliser la décision KORA et définir les écritures compensatrices, avant et après reversement. |

## 8. Faisabilité à partir du code actuel

Constats issus d’une lecture statique ; les tests existants ont été examinés, sans nouvelle exécution dans ce travail de cadrage.

| Élément | État observé | Implication |
| --- | --- | --- |
| Vente Bus, sièges, prix, billets | Modules existants. | Réutilisation du parcours et des invariants ; aucun second moteur spécial Point. |
| Agence et opérateur | `Agency` représente un lieu commercial ; `User` possède un `agency_id` unique. | Pas de structure partenaire multi-sites actuelle. |
| Attribution historique | `RecordCounterSalePaymentAction` fige `selling_agency_id`. | Conserver ce principe pour le point et le partenaire, sans attribution rétroactive par l’affectation courante. |
| Canal | `ReservationChannel` ne contient pas de canal Point. | Rendre la distribution partenaire identifiable dans les rapports ; décider la représentation sans confondre canal et moyen de paiement. |
| Caisse | Une session du jour et une agence sont requises pour les encaissements guichet espèces/MM. | Vérifier la compatibilité avec les caisses et comptes réellement utilisés par La Poste. |
| Billets | `TicketVisibility` filtre par agence vendeuse historique. | Socle utile, mais pas de périmètre consolidé partenaire. |
| Rôles | `SystemRole` contient les rôles agence et centraux, aucun rôle partenaire. | Créer des droits adaptés, sans reprendre intégralement les rôles internes. |
| Réservations | `ReservationPolicy::view` accepte `bookings.view` sans condition d’agence. | Un profil externe doit être filtré côté serveur sur toutes les consultations et téléchargements. |
| Annulations | `ReservationPolicy::cancel` accepte notamment le créateur de la réservation. | Retirer une permission du rôle ne suffit pas à garantir l’interdiction d’annulation pour le vendeur partenaire. |
| Assistance | Les agents habilités voient la file réseau non attribuée. | Ne pas ouvrir automatiquement cette file aux partenaires. |
| Rapports | Consolidation KORA et agrégation par agence existantes. | Ajouter des lectures limitées au partenaire/Point avant d’en ouvrir l’accès externe. |
| Commissions | Poste KORA POINT en attente, montant non renseigné dans la rentabilité. | Calcul et suivi des commissions restent à implémenter. |
| Remboursements personnels | `CancelReservationAction` crée un `PaymentRefund` de destination `wallet_cash`, statut `pending`. Aucun ledger Wallet opérationnel ni décaissement personnel n'a été trouvé. | Un remboursement enregistré ne prouve pas une restitution effectuée. La V1 Point doit traiter explicitement l'exécution du remboursement et la dette du partenaire. |
| Reversements | Transferts de caisse internes existants. | Ils ne constituent pas à eux seuls un règlement contractuel partenaire. |
| Togo | Réseaux de paiement togolais présents dans les données du code. | Aide technique ; ne prouve ni l’activation des comptes réels ni la validation des modalités de facturation. |

Points de lecture : `app/Models/User.php`, `app/Modules/IdentityAccess/Models/Agency.php`, `app/Modules/IdentityAccess/Data/SystemRole.php`, `app/Policies/ReservationPolicy.php`, `app/Modules/Booking/Data/ReservationChannel.php`, `app/Modules/Payments/Actions/RecordCounterSalePaymentAction.php`, `app/Modules/Ticketing/Services/TicketVisibility.php`, `app/Modules/Cash/Services/CashJournalQuery.php`, `app/Modules/Finance/Services/ConsolidatedRevenueReport.php`, `app/Modules/Profitability/Services/ProfitabilityReport.php`.

## 9. Circuit financier retenu et alternatives de référence

### A. La Poste encaisse et reverse le net

Circuit confirmé par le porteur du projet pour la V1 en production.

Le client paie le partenaire. La vente confirmée crée une somme due à KORA, diminuée de la commission retenue selon l’accord. Les reversements reçus et validés diminuent cette dette.

Exemple purement illustratif : 10 000 FCFA encaissés, commission contractuelle de 3 % = 300 FCFA, dette initiale de 9 700 FCFA. Après un reversement reçu de 5 000 FCFA, l’encours restant est 4 700 FCFA. Une déclaration de paiement non encore vérifiée ne suffit pas à réduire l’encours disponible.

Formule du cas simple : encaissements éligibles détenus par le partenaire − commissions retenues − reversements validés ± ajustements justifiés. Les remboursements et frais doivent suivre des règles explicites : qui rembourse, depuis quel compte, et avec quel effet sur la commission ?

### B. KORA encaisse directement et paie une commission

Alternative non retenue pour la V1, conservée ici pour expliquer les différences de conception.

La vente est attribuée au partenaire, mais les fonds arrivent chez KORA. Cette vente ne doit pas créer une dette de reversement du partenaire. Elle peut créer une commission payable par KORA, selon l’accord.

Ce circuit ne doit pas être assimilé à une vente guichet encaissée dans une caisse externe. Sa compatibilité avec le parcours actuel doit être vérifiée avant estimation.

### C. Circuit mixte

Alternative non retenue pour la V1.

Chaque vente doit conserver son détenteur de fonds et sa règle de règlement. Une vente réglée directement à KORA ne doit pas augmenter simultanément le solde dû par La Poste. Le circuit mixte constituerait une extension explicite du circuit choisi.

Dans tous les cas : la vente, la commission, le règlement et le remboursement sont des mouvements distincts et traçables. Un reversement n’est pas une deuxième vente ; une commission n’est pas une remise sur le prix passager. Les calculs doivent préserver les références des opérations et résister aux rejouements.

### D. Paramètres : décisions et proposition de résolution

Décisions confirmées : la commission est administrable dans les paramètres d'exploitation ; le besoin de surcharge partenaire/Point est exprimé ; la périodicité des reversements est configurable ; le plafond porte sur chaque Point, avec montant à préciser.

Proposition pour la commission : taux explicite du Point, sinon taux explicite du partenaire, sinon taux par défaut d'exploitation. Une valeur « hériter » est distincte d'un taux explicite de 0 %. L'écran affiche le taux effectif et son origine. La vente conserve le taux appliqué, l'assiette, le montant, l'origine et la version/date de ses conditions. Un changement ultérieur ne recalcule pas les commissions historiques ; il doit respecter les conditions déjà convenues.

Proposition commune à valider : la même priorité Point → partenaire → exploitation s'applique au taux de commission, au montant du plafond et au calendrier de reversement. Les bureaux de La Poste héritent normalement du partenaire, qui porte la convention de règlement ; une surcharge explicite du Point couvre une exception convenue. Même si plusieurs Points héritent du même montant, chacun conserve son propre plafond d'encours. La configuration des valeurs ne crée aucun plafond global partenaire implicite.

Le calendrier définit la fréquence, son ancrage (jour/heure et fuseau), la période couverte, la première échéance et les règles de retard. Il est hérité ou remplacé comme un ensemble cohérent ; il ne combine pas arbitrairement un jour d'un niveau et une fréquence incompatible d'un autre. Une nouvelle configuration ne décale pas silencieusement les échéances déjà constituées.

Le périmètre du règlement est distinct du périmètre du plafond. Même avec un plafond par Point, La Poste peut souhaiter un versement groupé. Dans ce cas, le versement doit être réparti explicitement entre les encours des bureaux ; une réception centrale ne libère pas indistinctement tous les plafonds. Le choix entre reversement par bureau et versement groupé reste ouvert.

Le montant numérique de plafond et le taux par défaut ne doivent pas être codés comme des constantes métier. La configuration nécessaire sera renseignée avant l'activation du Point ; le plan peut être finalisé sur leur caractère administrable sans inventer les valeurs contractuelles.

La proposition de calcul soumise à validation est une commission sur le prix des billets après remises, enregistrée au paiement avec les conditions applicables et corrigée au prorata d'un remboursement. L'alternative d'acquisition après voyage, ainsi que le maintien contractuel de la commission après remboursement, changeraient les écritures et doivent rester distingués d'un simple choix de pourcentage.

### E. Remboursement client et dette partenaire : opérations distinctes

MEZ-49 §5 et l'ADR 0004 fixent la destination cible des remboursements personnels au Wallet cash du payeur. Ils ne prouvent pas sa disponibilité : le code examiné enregistre une obligation en attente, sans crédit de Wallet ni sortie de caisse. Il faut donc déterminer la procédure de remboursement réellement disponible pour cette V1 et la traiter comme une dépendance de mise en service, sans annoncer un Wallet opérationnel.

Exemple illustratif, sans pénalité, commission entièrement reprise et taux de 3 % : un bureau encaisse 10 000 FCFA ; il doit reverser 9 700 FCFA et conserve 300 FCFA. Si KORA rembourse intégralement le payeur alors que le bureau détient encore les 10 000 FCFA, ce bureau doit toujours transférer ces fonds à KORA ; après reprise de la commission, sa dette atteint 10 000 FCFA. Annuler le billet ne lui permet pas de conserver l'encaissement.

Si le bureau rembourse lui-même les 10 000 FCFA avec autorisation et preuve, il ne détient plus ces fonds ; une écriture vérifiée peut solder la dette de cette vente et reprendre la commission. Si le net de 9 700 FCFA a déjà été reversé et KORA rembourse le client, la reprise des 300 FCFA de commission doit figurer dans le règlement partenaire suivant ou dans un remboursement séparé. Ces scénarios sont proposés pour la décision métier, pas décrits comme implémentés.

La correction de commission en cas de remboursement, la responsabilité d'exécution et les preuves requises restent ouvertes. Une annulation ou un remboursement seulement enregistré ne diminue jamais à lui seul l'encours du Point sans tenir compte de qui a réellement rendu les fonds. Le barème client reste celui approuvé pour Bus ; son application doit être vérifiée, car l'action d'annulation générale examinée utilise actuellement le total de la réservation sans pénalité temporelle.

## 10. Orientation de conception avec codebase-design

Le nouveau **Module** doit concentrer l’habilitation commerciale et le suivi partenaire derrière une **Interface** réduite : déterminer si un Point peut vendre, attribuer la vente confirmée, produire sa situation et enregistrer/valider son règlement.

Cette Interface comprend aussi les invariants : contexte de vente déterminé côté serveur, historique figé, plafond vérifié de façon concurrente, commission calculée une fois selon des conditions conservées et impossibilité d’accéder aux autres partenaires.

La **seam** avec les modules existants se situe dans le contexte commercial de vente et sa confirmation, ainsi que les lectures autorisées. L’**Implementation** du Module prend en charge les règles du partenaire ; les modules de stock, prix, paiement, billet et fiscalisation gardent la responsabilité de leurs invariants. Aucun nouvel **Adapter** n’est justifié uniquement par l’idée qu’une intégration postale pourrait exister un jour.

Deux options de stockage doivent être confrontées avant la décision technique :

| Option | Intérêt | Coût/risque |
| --- | --- | --- |
| Réutiliser le lieu commercial `Agency`, en distinguant les lieux partenaires et leur organisation | Réutilise les rattachements actuels de caisse, vente et terminal ; peut limiter les évolutions de la V1. | Exige d’examiner chaque hypothèse « agence = interne », les droits, rapports et validations. Une nouvelle étiquette seule serait insuffisante. |
| Créer un lieu partenaire distinct et faire évoluer le contexte commercial partagé | Modèle explicite du domaine partenaire. | Adaptations plus larges de vente, caisse, billets, attribution historique et rapports ; risque accru à quelques jours du lancement. |

Recommandation provisoire : étudier d’abord la réutilisation du lieu commercial existant, avec un partenaire explicite et des accès propres, en tenant compte du circuit de reversement net retenu. Éviter une refonte générale des agences comme préalable à la V1. La **Depth** attendue est la cohérence des règles accessibles par une petite Interface ; la **Locality** doit permettre de corriger ces règles au même endroit pour le web et les autres parcours autorisés.

## 11. Périmètre proposé

### V1 VOYAGE en production

- Administration KORA des partenaires et de leurs bureaux nommés, contacts, statuts et conditions validées ; La Poste du Togo est le premier cas d'usage identifié.
- Comptes personnels des agents, rattachés à un bureau ; aucune connexion partagée au nom d’un bureau.
- Vente Bus sur le stock et les tarifs actuels ; origine/destination distinctes du bureau de vente.
- Attribution historique partenaire, Point et vendeur sur la vente confirmée.
- Billet et remise au client par les moyens retenus et effectivement testés.
- Consultation limitée aux ventes du Point ; responsable partenaire en lecture seule sur les ventes et soldes de ses bureaux.
- Commission configurable et règles d'héritage explicites ; plafond d'encours par Point ; calendrier de reversement configurable, avec niveaux à confirmer.
- Situation financière adaptée au circuit retenu, commissions traçables et rapprochement minimal exploitable.
- Suspension empêchant les nouvelles ventes, sans invalider les billets émis ni effacer les obligations.
- Documentation courte : connexion, vente, encaissement, remise du billet, règlement, incident et support ; formation avec les vrais agents.

### Lots suivants

Supervision partenaire enrichie, automatisation des rappels et règlements, intégrations supplémentaires, responsable de zone, visites géolocalisées, PLV, prospection et éventuellement P501 selon l’équipement. Colis reste un chantier distinct, avec son cycle logistique et ses responsabilités.

Si la réception des règlements est rapprochée manuellement en V1, elle comporte une déclaration, une preuve, une validation autorisée et un historique ; le solde n'est pas éditable librement. Les contrôles nécessaires au circuit retenu font partie de la mise en service réelle.

## 12. Plan d’implémentation conditionnel

Les lots constituent un ordre d’exécution proposé pour le circuit A retenu. Leur charge et leur date ne sont pas établies. Les lots dépendant des décisions ouvertes ne sont pas engagés.

| Lot | Travaux | Condition de sortie |
| --- | --- | --- |
| 0 — Accord sur la V1 | Partir du partenaire multi-sites et du reversement net retenus ; préciser hiérarchie de configuration, responsabilité des remboursements, règlement par bureau ou groupé, périmètre des sites et recette. | Fiche de périmètre compréhensible et validée par le décideur client ; valeurs d'activation à fournir identifiées. |
| 1 — Conception ciblée | Choisir la représentation des lieux ; définir l’Interface du Module et les invariants de confirmation, attribution et règlement ; relever les impacts exacts. | Modèle et plan de migration examinés ; estimation fondée sur le périmètre choisi. |
| 2 — Données et habilitations | Partenaires, sites, affectations, configuration et héritage audités, rôles agent et responsable partenaire, filtrage serveur ; création/désactivation depuis OPS. | Un agent du site A ne peut consulter ou agir hors de son périmètre ; le responsable lit uniquement les bureaux de son partenaire, y compris par accès direct. |
| 3 — Vente et attribution | Réutiliser devis, blocage, passagers, confirmation et billet ; vérifier statut et plafond selon le circuit ; conserver les références commerciales. | Vente complète sans double siège et sans attribution ambiguë ; refus avant d’accepter un encaissement interdit. |
| 4 — Commission et règlement | Conditions conservées à la vente, journal des obligations, échéances, déclaration/validation des règlements et affectations aux bureaux, corrections de commission et remboursement effectif selon la procédure retenue. | Relevé recalculable ; reprises sans doublons ; paiement déclaré non validé sans effet libératoire ; annulation sans sortie de fonds ne supprimant pas la dette. |
| 5 — Vues et exploitation | Ventes Point, situation financière, vues KORA, consolidation partenaire en lecture seule ; guide et formation. | Un agent exécute le parcours sans intervention technique, le responsable suit ses bureaux et chacun sait traiter un incident. |
| 6 — Recette et activation | Tests ciblés, non-régression du socle, recette en sandbox avec acteurs réels ; migration et activation progressive. | Accord Go du client sur les scénarios convenus et aucun défaut critique du périmètre. |
| 7 — Suivi de production | Suivre ventes, encours, échéances et incidents ; accompagner les bureaux et les partenaires activés. | Droits, ventes et rapprochements vérifiés ; responsabilités de support appliquées. |

Les paramètres opérationnels généraux — seuils, tolérances, échéances et automatisations — suivent les groupes Settings persistés, administrables et audités du projet. La représentation des conditions propres à chaque partenaire/Point doit respecter ces conventions et être précisée pendant la conception. Aucune règle opérationnelle n’est placée dans un `.env`.

## 13. Recette minimale

1. Un agent postal vend un billet, reçoit la confirmation et remet un billet utilisable par le parcours d’embarquement existant.
2. Deux bureaux et l’application passager demandent le même siège sur des segments chevauchants : une seule vente réussit.
3. Deux agents consomment simultanément le même disponible de plafond : l’encours autorisé ne peut pas être dépassé par concurrence.
4. Un agent du Point A tente d’accéder à une réservation, un billet, un PDF, un export ou un relevé du Point B : refus selon le périmètre retenu. Les autres partenaires restent toujours exclus.
5. Le responsable partenaire voit les sites et données autorisés de son organisation ; toute mutation, accès à un autre partenaire ou donnée globale KORA est refusé.
6. Le vendeur partenaire ne peut pas annuler un billet via un accès direct, même s’il a créé la réservation ; la procédure de demande d’assistance est connue.
7. Une confirmation ou validation de règlement répétée ne double ni vente, ni commission, ni réduction d’encours.
8. Une modification de commission, une mutation d’agent ou une réorganisation du partenaire ne réécrit pas les ventes historiques.
9. Une annulation avant ou après reversement produit les corrections prévues et conserve toutes les opérations sources.
10. Une suspension interdit les nouvelles ventes mais laisse les billets existants valides et les obligations consultables par les personnes autorisées.
11. Une vente payée directement à KORA, si ce circuit est retenu, ne crée pas une dette du partenaire.
12. Une perte de connexion ou une réponse perdue permet de retrouver l’état réel de la vente ; aucune nouvelle vente hors ligne n’est improvisée.
13. Un Point avec surcharge utilise celle-ci ; sans surcharge il hérite du partenaire puis du défaut d'exploitation. Un 0 % explicite reste distinct de « hériter ». Ces niveaux restent soumis à la validation de la proposition de hiérarchie.
14. Une annulation remboursée par KORA avant reversement conserve les fonds encore dus par le Point et corrige la commission selon l'accord ; un remboursement seulement en attente ne vaut pas décaissement.
15. Un reversement groupé, s'il est retenu, ne libère que les plafonds des Points auxquels une réception vérifiée a été affectée.
16. Un changement de calendrier conserve les échéances déjà constituées et applique les nouvelles conditions à leur date d'effet.

Pour l’implémentation : tests ciblés pertinents, puis gates du dépôt (`composer test`, `composer test:pgsql` pour les invariants PostgreSQL concernés, contrôles frontend et build). Le contrôle d’image de production s’ajoute si l’image ou la chaîne de déploiement est affectée. Cette note documentaire ne nécessite ni migration ni exécution de la suite applicative.

## 14. Protection de la production existante

Prévoir des migrations additives et compatibles avec les ventes existantes. Ne pas réinitialiser la base ni requalifier arbitrairement les anciennes ventes d’agence comme ventes partenaires. Une attribution partenaire inconnue reste inconnue.

Tester la migration sur une copie assainie représentative. L’activation doit être limitée au partenaire et aux Points retenus ; le repli doit permettre d’arrêter leurs nouvelles ventes sans supprimer billets, paiements ou règlements. Une sauvegarde restaurable et les responsabilités de support doivent être établies avant activation.

Les montants de vente, la dette partenaire et les commissions doivent être présentés comme des notions distinctes dans les rapports. Le traitement de facturation applicable au montage réel, ainsi que l’émetteur des justificatifs, doivent être confirmés par les responsables habilités ; la seule localisation togolaise et les valeurs du vieux CDC ne constituent pas cette validation.

## 15. Décisions confirmées et suite de la discussion

Décisions en vigueur confirmées par le porteur du projet dans le chat du 31 octobre 2026 :

- **D1 — Engagement de lancement** : module live, exploité réellement en production. Aucun statut de pilote ni restriction du nombre de bureaux n'est retenu ; sites et recette restent à définir.
- **D2 — Hiérarchie métier** : un partenaire La Poste → plusieurs Points physiques → comptes personnels des agents.
- **D3 — Circuit d’argent** : La Poste encaisse, retient sa commission et reverse le net à KORA.
- **D4 — Commission** : configurable dans les paramètres d'exploitation ; surcharges partenaire/Point demandées comme piste. Hiérarchie exacte, assiette, acquisition, correction et valeurs à confirmer.
- **D5 — Plafond** : plafond d'encours par Point ; montant à préciser avant activation.
- **D6 — Calendrier** : périodicité configurable ; périmètre partenaire ou Point, ancrage et moyens à préciser.
- **D7 — Supervision partenaire** : responsable La Poste en lecture seule sur les ventes et soldes de ses bureaux dès la V1.

Troisième tour ouvert : **Q10**, hiérarchie Point → partenaire → exploitation pour commission, plafond et calendrier ; **Q11**, assiette, acquisition au paiement et correction de commission au prorata d'un remboursement ; **Q12**, versement groupé réparti entre bureaux ou versement distinct par bureau ; **Q13**, remboursement toujours traité par KORA ou exécution par le bureau avant reversement. Ces propositions ne sont pas encore acceptées.

Les taux, montants et calendriers précis restent des valeurs administrables à renseigner avant activation et ne sont pas inventés pour terminer le plan. Les bureaux, agents, paiements et équipements à activer restent des données de préparation du lancement. Les droits de modification/validation des conditions et règlements, les arrondis, les changements d'affectation et le contenu exact du périmètre de consultation devront ensuite être figés à partir des réponses.

Les termes effectivement résolus sont ajoutés à `CONTEXT.md`. Un ADR ne sera nécessaire que pour un choix structurant avec de vraies alternatives, par exemple la réutilisation du lieu commercial actuel et ses conséquences. Les recommandations encore ouvertes ne sont pas enregistrées comme décisions acceptées.

## 16. Position sur l’échéance annoncée

Le cas d'usage tient la route et s'inscrit dans la vision KORA. Le socle existant est réutilisable, mais la cible demandée est un module exploitable en production, comprenant les droits externes et les obligations financières de sa V1. L'analyse ne permet pas de garantir une réalisation et une qualification en trois jours.

La condition de lancement est un parcours complet vérifié : agent habilité → vente → paiement cohérent → billet → attribution au bon bureau → situation financière rapprochable. Si les décisions, la recette ou l’isolement des droits ne sont pas prêts à l’échéance, conserver le démarrage Bus existant et différer l’ouverture partenaire, avec éventuellement un relais commercial vers une vente exécutée par KORA et une procédure explicitement convenue. Aucun partage de compte interne ou émission parallèle de billets ne doit servir de raccourci.

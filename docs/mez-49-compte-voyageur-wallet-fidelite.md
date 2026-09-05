# Note de décision — Compte voyageur, Wallet et fidélité KORA BUS (MEZ-49)

> Ticket : [MEZ-49 — Décider le modèle compte voyageur, payeur et Wallet](https://linear.app/mezabodev/issue/MEZ-49/produit-decider-le-modele-compte-voyageur-payeur-et-wallet)
> Statut : décisions produit validées le 4 septembre 2026 ; implémentation à planifier. Les hypothèses de rédaction sont isolées en section 9 et les validations externes nécessaires au lancement du cash en section 10.
> Public : direction, produit, exploitation, finance et équipes de développement.

## 1. Direction retenue et portée

Un client peut acheter au guichet, voyager et bénéficier de son Wallet sans installer l'application. Son compte est créé ou retrouvé à partir de son téléphone personnel ; l'activation ultérieure lui ouvre l'accès au même compte, aux billets qui le concernent et aux soldes déjà acquis.

La première version Bus inclut désormais **un Wallet cash et des points de fidélité distincts**. Cette évolution de périmètre est actée par [l'ADR 0004](adr/0004-wallet-fidelite-bus.md). Elle remplace l'exclusion initiale du Wallet et la destination hors Wallet des remboursements personnels dans [l'ADR 0003](adr/0003-mvp-bus-only.md), sans effacer cette décision historique. Les exclusions Ride, Colis, KORA POINT, KORA Driver, comptabilité complète, paie et flotte avancée restent inchangées.

Cette tâche formalise une direction métier. Elle ne crée aucun module applicatif, route, migration ou intégration de paiement. Les comportements décrits ci-dessous sont la cible validée, pas une déclaration de disponibilité actuelle.

Le [glossaire partagé](../CONTEXT.md) fixe les termes compte voyageur, fiche voyageur, activation de l'accès, payeur, Wallet cash et points de fidélité. La jambe désigne ici la portion du billet effectuée sur un seul départ, selon la [note MEZ-51](multi-line-itineraries-design.md).

## 2. Compte, téléphone et droits d'accès

- Lors de l'achat ou de l'émission du billet, retrouver le compte par téléphone personnel normalisé, ou créer le compte et ses deux soldes s'il n'existe pas. Un même achat ne crée pas plusieurs comptes pour un même numéro.
- Le téléphone personnel du payeur est obligatoire, même au guichet et même s'il ne voyage pas. Le compte d'un payeur qui achète uniquement pour autrui existe lui aussi sans activation de l'application.
- Distinguer le créateur de la réservation, le payeur personnel ou entreprise et chaque voyageur. L'agent qui enregistre une vente n'est pas le payeur du client.
- Un numéro personnel correspond à un seul compte. Un voyageur sans numéro personnel conserve une fiche et un billet, mais ne bénéficie pas de fidélité. Le numéro d'un parent ou d'un tiers peut servir de contact sans créer un compte au nom de l'enfant ni attribuer ses points au tiers.
- L'activation de l'accès utilise un code SMS puis la définition d'un PIN, sur le compte existant. Elle ne recrée ni le compte ni les soldes et ne conditionne pas l'achat, le voyage ou l'acquisition de points.
- Le payeur consulte l'achat et ses justificatifs de paiement. Chaque voyageur consulte son propre billet et son propre Wallet ; il n'accède pas aux soldes ni à l'ensemble des billets des autres voyageurs du groupe.

**Conséquence sur l'existant :** MEZ-57 prévoit actuellement un téléphone obligatoire dans la saisie des voyageurs groupe/entreprise. Les travaux d'identité et de formulaires devront distinguer téléphone personnel et contact tiers pour permettre les fiches sans compte décidées ici. Réutiliser le numéro d'un adulte comme identité personnelle de plusieurs voyageurs ne constitue pas une solution.

## 3. Wallet cash et utilisation au guichet

### Cash et paiements pour autrui

Le Wallet contient un solde cash en FCFA et un solde points. Les points ne sont pas convertibles en cash. Le cash n'expire pas selon les règles de fidélité.

Les recharges passent uniquement par Mobile Money ou carte, après confirmation du paiement. Une remise d'espèces à l'agent ne recharge pas le Wallet ; les espèces restent acceptées pour acheter directement un billet au guichet.

Le titulaire peut utiliser son cash et ses points pour ses propres billets ou ceux d'un tiers. Il autorise la dépense de son Wallet ; le voyageur reste le bénéficiaire du billet et de la fidélité acquise par son voyage. Payer pour autrui ne transfère pas le solde du Wallet à cette personne.

Le cash sert aux achats KORA BUS. Aucun transfert entre utilisateurs ni retrait courant n'est prévu. Une restitution exceptionnelle de cash inutilisé, notamment pour fermeture de compte ou litige, suit une demande, un contrôle du titulaire, une validation par un responsable habilité, un versement traçable et le débit correspondant du Wallet. Les droits applicables restent soumis à la validation décrite en section 10.

### Débit au guichet sans application

1. Le titulaire est présent et donne son accord sur le montant à dépenser, y compris lorsqu'il paie pour un tiers.
2. Un agent habilité recherche le compte et réalise le contrôle visuel d'identité. La connaissance d'un nom et d'un téléphone ne remplace pas l'accord du titulaire.
3. Avant confirmation, KORA présente le titulaire, le billet concerné, le montant total et la répartition points/cash. L'agent confirme le paiement sous sa responsabilité.
4. Le débit conserve sa référence, l'agent, l'agence, le billet et l'horodatage. Le titulaire reçoit une notification comportant le montant et les références nécessaires pour contester.

Aucune checklist, photographie de pièce ou validation systématique par un supérieur n'est imposée dans ce parcours. Il n'impose pas non plus l'activation de l'application ou un code SMS pour chaque débit au guichet ; le SMS reste requis pour l'activation de l'accès numérique.

**Risque accepté :** le contrôle visuel repose sur l'agent, sans preuve automatique que le contrôle a été correctement réalisé. L'audit permet de retrouver l'auteur de l'opération ; la notification informe après le débit et ne constitue pas une autorisation préalable. Ce choix produit ne vaut pas validation externe du dispositif cash.

## 4. Fidélité : attribution, utilisation et bonus

### Points liés au voyage

Les points reviennent à chaque voyageur disposant d'un compte associé à son numéro personnel, même lorsqu'un tiers ou un compte entreprise paie. Ils ne sont pas regroupés automatiquement sur le compte du payeur.

Le crédit intervient **après chaque jambe effectuée**, avec embarquement enregistré et départ clôturé. Il représente **2 % de la part financée en argent**, calculée sur l'allocation tarifaire figée de la jambe après remises. La part financée en points ne produit pas de nouveaux points. Une jambe annulée ou non effectuée ne donne aucun point.

Exemple : un billet de 10 000 FCFA payé avec 2 000 points et 8 000 FCFA donne **160 nouveaux points**, hors bonus, après réalisation complète. Pour deux jambes allouées à 6 000 et 4 000 FCFA et financées à 20 % en points, les bases monétaires sont respectivement 4 800 et 3 200 FCFA : 96 puis 64 points. Si seule la première jambe est effectuée, seuls 96 points sont acquis.

### Valeur et utilisation

- **1 point = 1 FCFA** de valeur d'usage ; minimum d'utilisation : **100 points**.
- Paiement partiel ou total d'un billet, avec complément par cash Wallet, espèces au guichet ou Mobile Money.
- Aucun retrait, transfert de points entre comptes ou conversion des points en cash.
- Le titulaire peut utiliser ses points pour le billet d'un tiers, selon les mêmes règles d'autorisation que son cash.

### Bonus conservés du cahier des charges

| Bonus | Montant | Déclencheur retenu |
|---|---|---|
| Bienvenue | 500 points | Une seule fois par compte, après sa première jambe effectuée, même sans activation de l'application. |
| Parrainage | 200 points | Au parrain lorsque le filleul utilise son premier billet. |
| Anniversaire | 100 points | À l'anniversaire de la création du compte. |
| Cinquième utilisation | 50 points | À la cinquième utilisation du mois calendaire. |

Un billet compte une seule fois dès sa première jambe effectuée. Les autres jambes et les recharges ne multiplient pas les utilisations donnant droit aux bonus. Une recharge ne génère ni points proportionnels ni bonus d'utilisation.

### Expiration

Les points expirent globalement après six mois sans transaction KORA qualifiante, avec rappels à J-30, J-7 et J-1. Une activité qualifiante prolonge l'ensemble des points du compte ; l'expiration n'est pas calculée point par point. La restitution de points après annulation redémarre le délai global de six mois.

L'activation tardive de l'application ne réinitialise pas le délai et ne ressuscite pas des points déjà expirés. La définition des activités qualifiantes et le calendrier de référence sont consignés en section 9.

## 5. Remboursements et corrections de fidélité

Cette note fixe la **destination** du remboursement. Les barèmes d'annulation existants continuent de déterminer le **montant remboursable** ; leur taux et leurs conditions ne sont pas modifiés ici.

| Financement initial | Destination du montant remboursable |
|---|---|
| Paiement personnel en espèces, Mobile Money ou carte | Wallet cash du payeur, par défaut, même si son accès à l'application n'est pas activé. |
| Wallet cash personnel | Même Wallet cash du payeur. |
| Points de fidélité | Solde points qui a financé l'achat, avec redémarrage du délai global de six mois. |
| Points et argent | Restitution au prorata du financement initial, points en points et argent en cash au payeur. |
| Crédit entreprise | Crédit entreprise, conformément au [contrat MEZ-57](mez-57-group-purchases-company-accounts-contract.md). |

Les allocations tarifaires figées déterminent la part de chaque voyageur et jambe. L'annulation individuelle ne recalcule pas le prix des autres voyageurs ni leur remise groupe. Les appels répétés ne produisent pas plusieurs remboursements.

Exemple : Paul finance le billet de Boris à hauteur de 2 000 points et 8 000 FCFA. Si le barème rend 5 000 FCFA remboursables, Paul récupère **1 000 points et 4 000 FCFA** dans les soldes correspondants. Boris ne reçoit pas ce remboursement. Si le remboursement est intégral, Paul retrouve 2 000 points et 8 000 FCFA.

### Remboursement tardif après acquisition de points

Une réduction du montant qui avait donné droit à des points entraîne une correction de la fidélité du voyageur bénéficiaire, distincte du remboursement au payeur. Les points devenus indus sont repris sur les points encore disponibles. Le reliquat est conservé comme compensation à déduire des prochains gains de fidélité : il ne débite pas le cash et ne réduit pas le remboursement dû.

Exemple hors bonus : 160 points avaient été attribués, mais un remboursement tardif ramène le droit à 80 points. Il faut reprendre 80 points. Si 30 restent disponibles, ils sont repris et les **50 points restants** seront compensés sur les futurs gains. Un gain suivant de 100 points laisse alors 50 points disponibles ; aucun FCFA n'est prélevé.

## 6. Responsabilités des modules et interfaces attendues

Ces responsabilités cadrent les tickets suivants. Aucune nouvelle route, méthode publique ou structure de base de données n'est imposée par cette tâche documentaire.

| Module | Responsabilité et interface attendue |
|---|---|
| IdentityAccess / MEZ-55 | Retrouver ou créer le compte, garantir le téléphone unique, activer l'accès et traiter la récupération. L'identité stable du compte est conservée lors d'un changement de numéro. Booking utilise ce module sans dupliquer l'inscription. |
| Booking et Ticketing | Séparer créateur, payeur et voyageurs ; distinguer contact tiers et téléphone personnel ; conserver les allocations tarifaires et le financement initial ; limiter les billets visibles au périmètre de chaque personne. |
| Wallet | Consulter séparément cash et points ; réserver les fonds pour un paiement ; débiter, libérer ou restituer ; conserver l'historique des mouvements et les compensations de fidélité. |
| Payments et crédit entreprise | Confirmer les recharges, financer les achats, rapprocher les montants et orienter les remboursements vers leur titulaire ou le crédit entreprise. |
| Fidélité et Boarding | Constater les jambes effectuées, attribuer une seule fois les points et bonus, traiter l'expiration et les corrections. |
| Notifications et audit | Notifier durablement le titulaire, reprendre les livraisons échouées et tracer les opérations automatiques ou réalisées par un agent. |

Les interfaces publiques exposent des UUID ou codes métier, jamais les identifiants numériques internes. Les soldes et montants sont calculés côté serveur à partir des allocations et confirmations conservées. Un appel répété, un paiement simultané ou une synchronisation tardive ne doit provoquer ni double dépense, ni double attribution, ni double restitution. Les reprises et corrections sont des mouvements traçables ; elles n'effacent pas les opérations d'origine.

Les impacts doivent être repris dans les travaux [MEZ-55 — identité par téléphone](https://linear.app/mezabodev/issue/MEZ-55/identity-utiliser-le-telephone-comme-cle-principale-du-compte), MEZ-57 pour les contrats existants, et les tickets suivants de Wallet, fidélité, paiement et guichet. La présente tâche ne crée ni ne clôture de tickets Linear.

## 7. Scénarios de validation

| N° | Situation | Résultat attendu |
|---|---|---|
| 1 | Achat au guichet sans application, voyage, puis activation ultérieure. | Même compte et mêmes soldes ; points acquis après les jambes effectuées ; bonus de bienvenue sans obligation d'activation. Les points expirés ne réapparaissent pas. |
| 2 | Paul paie pour trois voyageurs ayant chacun un numéro personnel. | Billets individuels et points individuels ; achat et justificatifs accessibles à Paul ; remboursement personnel à Paul. Aucun voyageur ne voit les Wallets des autres. |
| 3 | Un parent paie pour lui-même et deux enfants sans téléphone personnel. | Fiches et billets des enfants conservés ; aucun point ni bonus attribué pour eux ou transféré au parent. Le parent gagne seulement la fidélité de son propre voyage. |
| 4 | Billet de 10 000 FCFA payé avec 2 000 points et 8 000 FCFA. | 160 nouveaux points après réalisation complète, hors bonus ; aucune acquisition sur les 2 000 points utilisés. |
| 5 | Correspondance à deux jambes, seule la première effectuée. | Points sur la seule base monétaire de la première jambe ; un seul billet utilisé pour le compteur des bonus. |
| 6 | Annulation d'un achat mixte avec 5 000 FCFA remboursables sur 10 000 financés à 20 % en points. | 1 000 points et 4 000 FCFA rendus au payeur ; délai points relancé de six mois ; aucune conversion en cash ni double remboursement. |
| 7 | Remboursement tardif : 80 points à reprendre, 30 encore disponibles. | Reprise de 30 points puis compensation de 50 sur les futurs gains ; cash du voyageur et remboursement du payeur préservés. |
| 8 | Agent habilité utilisant un Wallet au guichet. | Titulaire présent et d'accord ; contrôle visuel ; confirmation du montant ; référence, agent, agence, billet et heure audités ; notification au titulaire et référence de contestation. |
| 9 | Callback de recharge, scan, clôture ou traitement de bonus répété ; débits concurrents. | Une seule écriture économique par opération logique, aucune utilisation multiple des mêmes fonds. Une synchronisation tardive ne donne les points qu'une fois les conditions de réalisation connues. |
| 10 | Achat sur crédit entreprise, puis remboursement individuel. | Écriture compensatrice au crédit entreprise ; aucun cash remboursé au Wallet du salarié ; allocations des autres voyageurs inchangées. |
| 11 | Cinquième billet utilisé dans le mois, avec plusieurs correspondances ; recharge supplémentaire. | Bonus de 50 points une seule fois sur la cinquième utilisation ; ni correspondance ni recharge n'ajoute une utilisation. |
| 12 | Échec de livraison d'une notification de débit. | Opération conservée et auditable ; livraison à reprendre sans rejouer le débit. La notification n'est pas présentée comme preuve d'autorisation. |
| 13 | Restitution exceptionnelle du cash inutilisé. | Demande et contrôle du titulaire, approbation du responsable, versement traçable et débit correspondant ; aucun retrait courant ou transfert entre utilisateurs ajouté. |

La validation documentaire vérifie la cohérence des résultats, les exemples chiffrés et les références. Les tests applicatifs de ces scénarios seront définis et exécutés dans les tickets d'implémentation ; cette note ne constitue pas un résultat de test du Wallet.

## 8. Écarts au cahier des charges et décisions antérieures

| Sujet | Source antérieure | Direction MEZ-49 |
|---|---|---|
| Calendrier | Le tableau du cahier des charges place les points en phase 3 / mois 7 et le cash en phase 4 / année 2 ; d'autres calendriers du document divergent. ADR 0003 exclut le Wallet du MVP Bus. | Points et cash inclus au périmètre de la première version Bus, avec lancement du cash conditionné aux validations externes. |
| Déclenchement Bus | Points au passage du billet au statut PAYÉ. | Points après chaque jambe effectuée, embarquement enregistré et départ clôturé. |
| Assiette | Taux Bus de 2 %, sans règle explicite pour la part financée en points. | 2 % de la part monétaire des allocations figées après remises ; aucun gain sur la part financée en points. |
| Compte et activation | La réunion évoque un compte créé ou retrouvé par téléphone et laisse le modèle à arbitrer. | Compte et Wallet automatiques ; accès numérique facultatif, activable plus tard. |
| Plusieurs voyageurs | La réunion laisse ouverts le rattachement du paiement et les remboursements. | Payeur identifié séparément ; fidélité de chaque voyageur éligible ; remboursement au payeur ou au crédit entreprise. |
| Bonus | Inscription + première transaction, parrainage, anniversaire et cinquième transaction. | Montants conservés ; bienvenue sans activation, premier billet utilisé pour parrainage, billet compté une fois et aucune recharge dans le compteur d'utilisation. |
| Expiration et annulation | Expiration globale à six mois ; durée des points restitués non explicitée. | Mécanisme global conservé ; restitution de points relançant six mois, reprise des gains indus par compensation future. |

Le Wallet reste limité au Bus dans cette version ; les usages Ride, Colis et Premium cités dans le cahier des charges général n'élargissent pas ce périmètre.

## 9. Hypothèses de rédaction retenues

Ces conventions complètent les décisions produit pour la rédaction et doivent rester identifiables lors de la préparation des tickets techniques.

- Utiliser `Africa/Porto-Novo` pour les mois calendaires et anniversaires. La création automatique du compte tient lieu de date d'inscription pour le bonus anniversaire.
- Les achats confirmés et recharges confirmées sont des activités qualifiantes pour l'expiration. Les bonus, connexions et notifications ne prolongent pas le solde. Une restitution de points relance explicitement six mois. Une recharge peut donc prolonger les points existants sans produire de points ni compter pour les bonus.
- Conserver WhatsApp avec SMS de secours pour les notifications, sans exiger l'installation de l'application. La livraison doit pouvoir être reprise indépendamment de l'écriture économique.
- Traiter dans les tickets d'identité le changement de téléphone, la récupération d'accès et les numéros réattribués. Une simple déclaration de numéro ne peut ni fusionner des comptes ni transférer leurs soldes. L'activation d'un compte existant ne crée pas un second droit au bonus de bienvenue.

## 10. Validations externes et préparation du lancement cash

La décision produit autorise le travail de conception du Wallet cash. Son lancement nécessite la validation du montage exact avec le prestataire de paiement et le conseil local : qualification du dispositif, entité portant les fonds et la dette envers le client, protection et rapprochement des fonds, plafonds applicables, vérification du titulaire et droits de restitution.

L'article 3 de l'[instruction BCEAO n°008-05-2015](https://www.bceao.int/sites/default/files/inline-files/instruction_no008_05_2015_intranet.pdf) prévoit une exception pour certains usages limités aux filiales de l'émetteur ou à des biens et services prédéterminés auprès de lui. Cette disposition ne qualifie pas automatiquement KORA : il faut examiner les entités, les accepteurs et le rôle effectif du prestataire. Si le régime de monnaie électronique s'applique, les dispositions de restitution, notamment l'article 35, doivent être prises en compte.

Le remboursement personnel par défaut au Wallet et l'absence de retrait courant ne suppriment pas les droits de restitution éventuellement applicables. La validité des conditions de remboursement, notamment après une annulation par KORA ou pour un client sans accès numérique, doit être confirmée au regard du montage et du droit local, dont la [loi béninoise n°2007-21 portant protection du consommateur](https://sgg.gouv.bj/doc/loi-2007-21/download). La lecture ciblée de ces sources n'est ni une qualification juridique définitive ni une autorisation de lancement.

La préparation des tickets devra également préciser les interfaces des prestataires, les arrondis et répartitions déterministes des points et remboursements, les preuves d'embarquement synchronisées et le traitement des contestations. Ces choix techniques ne doivent pas modifier silencieusement les règles métier de cette note.

## 11. Sources et références

- [MEZ-49 — Décider le modèle compte voyageur, payeur et Wallet](https://linear.app/mezabodev/issue/MEZ-49/produit-decider-le-modele-compte-voyageur-payeur-et-wallet) : objectif de cadrage ; arbitrages validés dans la session du 4 septembre 2026.
- [Compte rendu KORA OPS du 29 août 2026](https://docs.google.com/document/d/1h38lyF27UGy4yf-yltz40shdFilkrXOX80H2nxagYaU/edit), section 3.6 : compte voyageur, Wallet, paiement et décisions encore ouvertes à cette date.
- [Kora_App_CDC_Metier_V7.docx](https://drive.google.com/file/d/1d1LtmtQNpodeXrccM1yCzvw6olzwrTDW/view), chapitre « KORA WALLET — POINTS FIDÉLITÉ », règles 7.1 à 7.5 et tableaux associés : structure cash/points, taux, bonus et expiration ; sections sur annulations et notifications pour les remboursements Wallet.
- [MEZ-51 — Itinéraires multi-lignes](multi-line-itineraries-design.md) : modèle de note, jambes et billets individuels ; son exclusion historique du Wallet est remplacée par MEZ-49 sur ce seul point.
- [Contrat MEZ-57](mez-57-group-purchases-company-accounts-contract.md) : payeur, crédit entreprise, allocations tarifaires et annulation individuelle.
- [MEZ-55 — Téléphone comme clé principale du compte](https://linear.app/mezabodev/issue/MEZ-55/identity-utiliser-le-telephone-comme-cle-principale-du-compte) : travaux d'identité et de rapprochement des comptes.
- [ADR 0003](adr/0003-mvp-bus-only.md) et [ADR 0004](adr/0004-wallet-fidelite-bus.md) : historique et évolution explicite du périmètre.

En cas de divergence sur les sujets tranchés ici, la présente décision produit fait référence pour les travaux MEZ-49 et suivants. Les hypothèses de la section 9 et les validations externes de la section 10 restent distinguées des décisions validées dans les sections 2 à 5.

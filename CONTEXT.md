# KORA BUS

KORA BUS est le contexte du MVP valide par le client. Ce glossaire fixe le vocabulaire commun entre le client, le pilotage projet et l'equipe technique.

## Paramétrage runtime

**Paramètre administrable**:
Valeur métier du MVP modifiable par un utilisateur OPS autorisé, persistée dans la base et appliquée aux requêtes et jobs suivants. Elle appartient à un groupe de paramètres et ses mutations sont auditées.

**Paramètre de plateforme**:
Valeur nécessaire au fonctionnement de l'infrastructure (base de données, cache, file, logs, stockage, URL et clé d'application, mailer, Chromium/PDF). Elle reste fournie par l'environnement de déploiement et n'est pas administrée dans KORA OPS.

**Secret d'intégration**:
Credential d'un fournisseur métier (e-MECeF, paiement, cartographie, Genuka/WAHA ou OAuth) stocké chiffré en base. Une lecture de snapshot expose uniquement sa présence ; une valeur vide conserve le secret et sa suppression est une action explicite et auditée.

**Groupe de paramètres**:
Ensemble transactionnel et verrouillable de paramètres partageant une autorisation et un audit : `general`, `operations` ou `integrations`.

**Regle**:
Tout paramètre lié à l'exploitation ou au suivi (seuils, délais, tolérances, limites, fenêtres d'ingestion) est un paramètre administrable : il vit dans un groupe de paramètres persisté en base et modifiable depuis l'écran Paramètres de KORA OPS, jamais en dur dans un fichier `config/*.php` ou une variable d'environnement. Un changement de comportement métier ne doit jamais exiger un accès au code ou un redéploiement.

## Language

**Ecosysteme KORA cible**:
A terme, KORA comprend une application client multi-service, une application conducteurs Ride, une application operationnelle mobile Bus/terrain et un ERP web interne.
_Avoid_: MVP super-app

**MVP KORA BUS**:
Plateforme Bus exploitable de bout en bout en 12 semaines, couvrant vente, paiement, billet, fiscalisation, exploitation, embarquement hors ligne et revente de segments liberes. Le perimetre produit inclut desormais le Wallet et la fidelite Bus selon [MEZ-49](docs/mez-49-compte-voyageur-wallet-fidelite.md), sans signifier que ces fonctions sont deja implementees.
_Avoid_: MVP super-app, MVP Colis, portefeuille multi-service generaliste

**KORA App**:
Application mobile client cible. A terme elle portera Bus, Ride, Colis et Wallet. Dans le MVP, elle porte le parcours passager Bus et l'acces au Wallet Bus defini par MEZ-49.
_Avoid_: Creer Ride ou Colis dans le MVP

**KORA App Passager Bus**:
Perimetre MVP de KORA App : rechercher un depart, reserver, payer, consulter un billet, suivre le bus et acceder a son compte et a son Wallet Bus.
_Avoid_: App client generique

**KORA OPS Web**:
ERP interne web utilise pour les referentiels Bus, departs, ventes guichet, tarifs, caisses, fiscalisation, supervision, rapports simples et audit.
_Avoid_: Back-office generique

**KORA OPS Mobile/P501**:
Application mobile interne Android/P501 pour chauffeurs Bus, convoyeurs, agences et operations terrain, incluant planning, manifeste, scan, impression, descente anticipee et mode hors ligne.
_Avoid_: KORA Driver

**Terminal P501**:
Materiel P501 physique enregistre dans KORA OPS et identifie par un numero de serie unique. Il peut ouvrir une session OPS Mobile uniquement lorsqu'il est autorise, affecte selon les regles du domaine et controle par le serveur.
_Avoid_: Appareil de confiance d'un utilisateur, telephone de secours, B501

**Statut d'autorisation d'un terminal**:
Etat de cycle de vie `active`, `inactive`, `maintenance` ou `lost`. Il determine si le terminal peut etre utilise ; il est distinct de sa sante de connexion.
_Avoid_: Confondre terminal autorise et terminal actuellement connecte

**Sante de connexion d'un terminal**:
Etat technique derive des sessions OPS et de `last_seen_at` : `online` jusqu'a 15 minutes, `monitor` entre 15 minutes et 24 heures, `offline` au-dela ou sans presence connue. Cette information ne change jamais le statut d'autorisation.
_Avoid_: Reactiver automatiquement un terminal hors ligne

**Affectation de terminal**:
Rattachement courant d'un terminal a un utilisateur et/ou une agence. Chaque changement ferme l'affectation precedente et conserve une trace immuable ; une desaffectation ne supprime jamais l'historique.
_Avoid_: Affectation implicite par la derniere session, historique mutable

**Terminal en stock**:
Terminal P501 non affecte et desactive, conserve comme materiel disponible pour une future affectation. Le retour au stock est une action explicite et auditee, distincte d'une simple consultation ou d'une recuperation apres perte.
_Avoid_: Terminal actif non affecte utilisable sans controle

**Release applicative**:
Package (APK) versionne d'une application distribuee hors stores, publie via KORA OPS (MEZ-150). Porte une `version` semver, un `build_number`, des notes de version et un flag « mise a jour obligatoire ». Archiver une release la retire du calcul « latest » (rollback).
_Avoid_: Confondre release publiee et version installee sur un terminal

**Mise a jour obligatoire**:
Propriete d'une release applicative. Toute version installee anterieure a la derniere release obligatoire est bloquee sur un ecran de force-update non-dismissible jusqu'a installation ; une release non obligatoire ne declenche qu'un prompt dismissible.
_Avoid_: Prompt fermable pour une MAJ obligatoire

**KORA Driver**:
Application mobile future pour les conducteurs Ride/VTC/moto/keke. Elle est hors MVP Bus.
_Avoid_: Utiliser ce nom pour les chauffeurs de bus

**Depart**:
Rotation planifiee d'un bus sur une ligne, a une date et une heure donnees.
_Avoid_: Voyage, trajet

**Segment O/D**:
Portion de trajet comprise entre un arret d'origine et un arret de destination sur un depart.
_Avoid_: Ligne complete

**Blocage de siege**:
Reservation temporaire empechant une vente concurrente pendant le paiement, avec expiration serveur.
_Avoid_: Siege reserve definitif

**Gabarit de sieges**:
Modele reutilisable de plan de cabine d'un bus (grille de cellules types : siege, allee, vide, porte, conducteur) qui definit la position, le type et la vendabilite structurelle de chaque siege. Un gabarit est rattache a un bus et sert de source unique pour tous les rendus du plan (vente, detail depart, fiche vehicule, editeur). Le statut operationnel d'un siege (maintenance, blocage temporaire) reste propre au bus, pas au gabarit.
_Avoid_: Composition, plan HTML statique par ecran

**Siege hors service**:
Siege indisponible pour cause materielle (degrade, en reparation), distinct du siege equipage qui est reserve au personnel. Un siege hors service n'est jamais vendable et est materialise sur le bus avec le statut `maintenance`, la ou l'equipage donne `blocked`.
_Avoid_: Siege bloque, siege equipage

**Devis tarifaire**:
Prix final calcule cote serveur pour une demande de reservation, fige pendant la duree du blocage de siege.
_Avoid_: Prix indicatif

**Reservation assistee**:
Reservation Bus initiee par un passager puis adressee au support client central par appel ou WhatsApp. Elle conserve la meme reference et le meme itineraire, ne bloque ni siege ni credit pendant l'attente, et entre dans la file reseau jusqu'au claim atomique d'une agence habilitee.
_Avoid_: Agence choisie au depart, deuxieme reservation creee par l'agence, siege bloque dans la file d'assistance, reference WhatsApp utilisee comme autorisation

**Support client centralise**:
Point de contact telephonique ou WhatsApp configure dans KORA vers lequel une demande d'assistance est initialement dirigee. Les deux canaux sont independants et aucune coordonnee d'agence ne sert de fallback.

**File reseau d'assistance**:
Ensemble des dossiers d'assistance `pending` sans agence affectee, visibles par les agences et roles reseau habilites.

**Agence traitante**:
Agence qui a remporte le claim ou qui a recu une reaffectation. Elle est exposee publiquement sous `handling_agency`.

**Claim**:
Prise en charge atomique d'un dossier par un agent rattache a une agence. Le premier claim valide enregistre l'agence, l'agent et le statut `in_progress`.

**Reaffectation**:
Action reseau qui place un dossier `pending` dans la file d'une autre agence, sans choisir d'agent. L'agence cible doit ensuite effectuer un claim.

**Descente anticipee**:
Evenement enregistre par le convoyeur lorsqu'un passager descend avant sa destination initiale, afin de liberer les segments non parcourus.
_Avoid_: Annulation

**MECeF**:
Integration de fiscalisation normalisee associee a chaque vente confirmee, avec reprise idempotente en cas d'indisponibilite.
_Avoid_: Facture billet

**Facture normalisee**:
Facture fiscale KORA associee a une reservation et a un paiement confirmes, dont la reference publique, le statut, les tentatives de synchronisation et l'UID MECeF sont suivis dans OPS Web.
_Avoid_: Réponse fournisseur brute exposée à tous les rôles, facture créée en double

**Facture de test e-MECeF**:
Facture émise pour vérifier un scénario de l'auto-déclaration auprès de la DGI. Elle sert de preuve de conformité fiscale et reste distincte d'une facture normalisée issue d'une vente KORA BUS.
_Avoid_: Vente BUS, recette, facture métier

**Tentative de synchronisation fiscale**:
Enregistrement append-only d'une soumission ou d'une finalisation vers le fournisseur e-MECeF. Le résumé assaini est consultable selon le périmètre Finance ; le payload complet est chiffré et réservé à la DAF et à l'administrateur système.
_Avoid_: Relance sans idempotence, secret fournisseur dans un export

**Dépense**:
Sortie opérationnelle XOF saisie par une agence, rattachée facultativement à une session ou à un ledger de caisse, soumise à validation et publiée dans le module Finance avant son rapprochement.
_Avoid_: CashMovement historique reclassé automatiquement, montant décimal ou devise multiple dans le MVP

**Sortie de caisse**:
Mouvement financier sortant d'un ledger de caisse. Une dépense approuvée peut en créer une, mais les sorties historiques restent distinctes et ne sont jamais comptées deux fois dans les vues Finance.
_Avoid_: Dépense métier, écriture publiée modifiable

**Session de caisse**:
Session journalière d'un agent sur une caisse, portant un sous-journal par compte suivi (espèces + comptes Mobile Money actifs). Ouverture et clôture exigent un comptage déclaré avec photo prise en live ; un écart non nul soumet la session à l'approbation d'un responsable.
_Avoid_: Caisse permanente sans cycle journalier ; solde d'ouverture pré-rempli depuis la veille

**Session non clôturée**:
Session de caisse encore ouverte après sa journée comptable. Elle bloque sa caisse et reste visible pour son agent et la supervision ; le système l'alerte chaque matin sans jamais la clôturer automatiquement, car un comptage réel est exigé.
_Avoid_: Clôture automatique silencieuse ; session oubliée invisible ; écart masqué par un solde fictif

**Clôture forcée**:
Clôture d'office d'une session non clôturée par un responsable d'agence, avec motif obligatoire, sans comptage ni preuve : les soldes constatés restent « non contrôlés » et les mouvements en attente sont rejetés. Tracée par `force_closed_by`/`force_closed_at` et le journal d'audit.
_Avoid_: Clôture ordinaire fabriquée ; suppression de la session ; reprise sans trace

**Clôture tardive**:
Clôture ordinaire réalisée par l'agent après la `business_date` de sa session. Elle conserve la photo prise au moment du comptage et reste signalée « clôture tardive » dans l'historique.
_Avoid_: Clôture re-datée à la journée comptable ; blocage définitif de la caisse

**Solde théorique**:
Solde calculé d'une caisse ou d'un compte — dernière ouverture déclarée plus mouvements publiés — affiché en permanence dans la supervision. Il n'est ni un solde bancaire ni un comptage physique.
_Avoid_: Solde réel opérateur Mobile Money ; solde pré-rempli à l'ouverture

**Supervision de caisse**:
Consultation des sessions de caisse selon le périmètre de l'utilisateur — personnel, agence (`cash.view-agency`) ou réseau (`cash.view-network`) — avec historique complet, preuves privées, écarts et actions de contrôle.
_Avoid_: Visibilité liée au seul pouvoir d'approbation ; consultation limitée à la journée courante

**Contrepassation**:
Écriture inverse auditée d'une dépense publiée. Elle conserve la dépense d'origine, exige un motif et ne supprime ni ne modifie l'écriture historique.
_Avoid_: Suppression de dépense, correction silencieuse d'un ledger

**Rapprochement**:
Association vérifiable entre une dépense publiée et un ledger ou mouvement de caisse. Une dépense non rapprochée reste visible comme telle et ne modifie pas artificiellement le solde.
_Avoid_: Déduction automatique sans preuve, double comptage Expense/CashMovement

**Recette brute**:
Somme des paiements confirmés dont la date de confirmation appartient à la période financière observée. Les billets, sessions et mouvements de caisse servent à contrôler cette somme, jamais à l’augmenter.
_Avoid_: Additionner paiements, billets et caisse ; reconnaître une vente à sa date de réservation

**Déduction d’annulation**:
Obligation de remboursement reconnue à la date de création de l’annulation, quel que soit son statut de versement. Le cumul reconnu est plafonné au montant du paiement confirmé lié ; les obligations sans paiement et les dépassements sont signalés séparément.
_Avoid_: Attendre le versement effectif ; soustraire deux fois le Wallet ou le crédit entreprise ; dépasser le montant encaissé

**Recette nette**:
Recette brute diminuée des déductions d’annulation reconnues sur la même période économique. Une annulation peut donc diminuer une période différente de celle de l’encaissement d’origine.
_Avoid_: Marge ou rentabilité ; encaissement diminué uniquement des remboursements déjà versés

**Canal de vente final**:
Parcours dans lequel la réservation a effectivement été finalisée et payée. Une réservation commencée dans l'application puis finalisée par une agence devient une vente guichet ; une réservation reprise en ligne et confirmée par une intégration reste une vente via l'application passager.
_Avoid_: Classer une vente d'après le canal d'origine de la demande ; appeler une ancienne valeur technique « en ligne » sans preuve de fournisseur intégré

**Source d'encaissement**:
Moyen, fournisseur d'intégration et réseau ayant réellement porté le paiement. Le moyen et le fournisseur décrivent comment l'argent a été reçu ; ils ne constituent pas une seconde recette à additionner au canal de vente.
_Avoid_: Déduire un fournisseur du seul canal ; afficher le réseau Mobile Money d'un guichet comme un fournisseur d'intégration

**Passerelle widget (`sdk_widget`)**:
Fournisseur dont la fenêtre de paiement s'ouvre côté client à partir d'une configuration renvoyée par `initiate` (clé publique, montant, `partnerId = payment.uuid`, méthodes et pays autorisés). Le `transactionId` rendu par le widget est lié au paiement puis vérifié côté serveur ; la réponse du widget seule ne confirme jamais.
_Avoid_: Push serveur vers un numéro ; faire confiance au `transactionId` sans `transactions/status`

**Réseau Mobile Money multi-pays**:
Valeur de `MobileMoneyNetwork` suffixée par pays (`wave_ci`, `tmoney_tg`, …) lorsque le même opérateur existe sur plusieurs marchés. Le pays se déduit du réseau via `country()` ; les réseaux sans suffixe restent béninois.
_Avoid_: Champ pays de paiement indépendant pouvant contredire le réseau ; réseau BJ réutilisé pour un autre pays

**Vente via l'application passager**:
Vente finalisée dans le parcours mobile, dont la source d'encaissement peut être détaillée par fournisseur d'intégration et réseau ou par un moyen mobile reconnu.
_Avoid_: Fusionner l'application passager avec une ligne générique « En ligne » ; rattacher automatiquement une vente mobile à une agence

**Vente portail entreprise**:
Vente Bus finalisée par le référent d’un compte organisationnel, payée sur son crédit mensuel et rattachée au canal `company_portal`. Les voyageurs doivent être des membres actifs du roster de cette organisation.
_Avoid_: Vente guichet ; vente via l’application passager ; vente en ligne générique

**Compte voyageur**:
Identite client KORA retrouvee ou creee automatiquement a partir d'un telephone personnel normalise lors de l'achat ou de l'emission d'un billet. Elle existe avec son Wallet meme sans acces active a l'application ; son titulaire peut voyager, payer pour un tiers ou faire les deux.
_Avoid_: Inscription obligatoire dans l'application, fiche voyageur sans telephone personnel

**Fiche voyageur**:
Informations de la personne transportee associees a son billet, distinctes du payeur. Une personne sans telephone personnel conserve sa fiche et son billet sans disposer d'un compte de fidelite ; un telephone de contact tiers ne prouve pas qu'elle est titulaire de ce compte.
_Avoid_: Compte du parent, compte du payeur, compte partage par plusieurs voyageurs

**Activation de l'acces**:
Ouverture de l'acces a l'application sur un compte existant, apres verification du telephone par OTP WhatsApp (email de repli) et definition d'un PIN.
_Avoid_: Creation du compte, creation du Wallet, condition pour voyager

**Payeur**:
Personne ou compte entreprise qui finance l'achat, distinct du voyageur et de l'agent qui enregistre la vente. Le payeur personnel est identifie par son propre telephone, meme lorsqu'il ne voyage pas.
_Avoid_: Voyageur par defaut, agent de guichet, proprietaire des points gagnes par tous les voyageurs

**Wallet**:
Portefeuille rattache au compte voyageur, compose de deux soldes distincts : cash en FCFA et points de fidelite utilisables pour les achats KORA BUS. Son inclusion au perimetre produit est actee par [l'ADR 0004](docs/adr/0004-wallet-fidelite-bus.md).
_Avoid_: Solde unique melant argent et points, credit entreprise, portefeuille de transfert entre utilisateurs

**Wallet cash**:
Solde monetaire en FCFA du Wallet, alimente par les recharges numeriques et les remboursements personnels, utilisable pour les achats Bus. Il est distinct des points et ne suit pas leur expiration.
_Avoid_: Points convertibles en argent, caisse de l'agent, credit entreprise

**Recharge Wallet**:
Demande du titulaire d'ajouter des fonds au solde cash. Un paiement operateur verifie donne lieu a un seul credit du Wallet.
_Avoid_: Achat de billet, transfert entre Wallets

**Financement du billet**:
Repartition du prix paye entre le cash Wallet, la valeur d'usage des points depenses et l'eventuel complement monetaire. Le taux applique a l'achat fait foi pour les remboursements.
_Avoid_: Nombre de points exprime en FCFA

**Remboursement Wallet exclusif**:
Regle fondamentale du domaine KORA : tout remboursement, restitution ou regularisation de paiement est integralement et obligatoirement credite sur le solde cash du Wallet du voyageur. Aucun remboursement n'est execute en especes ni retrograde via l'API d'un fournisseur Mobile Money.
_Avoid_: Remboursement externe via passerelle Mobile Money, sortie d'especes au guichet pour achat en ligne

**Hold de financement mixte**:
Blocage temporaire de securite pose sur le solde cash ou les points du Wallet lors de l'initiation d'un paiement mixte (Wallet + complement Mobile Money externe). Il garantit la disponibilite de la part Wallet pendant l'execution de l'appel externe et ne doit etre libere que sur echec avere ou expiration formelle du paiement externe.
_Avoid_: Liberation prematuree du hold pendant qu'une intention de paiement externe est encore en cours

**Regularisation d'incident de billetterie**:
Operation financiere automatique ou supervisee intervenant lorsqu'un paiement externe a ete encaisse avec succes mais que le billet ne peut etre emis (siege plus disponible ou solde Wallet disparu). La part externe encaissee est alors immediatement portee au credit du solde cash du Wallet avec le motif comptable `unconfirmed_booking_settlement`.
_Avoid_: Dossier sans suite avec fonds externes captures, annulation silencieuse sans credit Wallet

**Points de fidelite**:
Unites de recompense propres au voyageur eligible, acquises par les voyages effectues et les bonus, utilisables pour payer des billets Bus. Leur valeur d'usage est de 1 point pour 1 FCFA ; ils ne constituent pas du cash retirable ou transferable.
_Avoid_: Argent disponible, remise immediate sur le voyage qui les genere, KORA POINT

**Ecriture du ledger Wallet**:
Enregistrement immuable d'un credit ou d'un debit effectivement passe sur un seul solde cash ou points. Une operation peut produire plusieurs ecritures ; les demandes et autres operations en attente n'apparaissent dans le journal qu'apres leur comptabilisation.
_Avoid_: Solde global, demande en attente, ligne historique modifiable ou supprimee

**Version de regle de fidelite**:
Ensemble publie et immuable des parametres d'acquisition, d'usage, de bonus et d'expiration des points KORA BUS. Une simulation est sans effet de bord ; une version programmee devient active a sa date d'effet. Les mouvements produits ensuite reference cette version, tandis que les echeances deja enregistrees restent inchangees.
_Avoid_: Campagne promotionnelle passager, regle modifiee apres publication, recalcul retroactif des echeances

**KORA POINT**:
Lieu physique de distribution exploite par un partenaire KORA et distinct des personnes qui y travaillent. Autonome par defaut, il peut etre rattache a une organisation partenaire. Son offre VOYAGE permet la vente de billets Bus au public. Le suivi terrain et les commandes PLV font partie de l'administration KORA POINT.
_Avoid_: Partenaire distributeur entier, compte utilisateur, point de fidelite, agence interne KORA

**Partenaire distributeur**:
Personne ou entite externe qui distribue les billets KORA. Dans le cas courant, le Point autonome represente directement ce partenaire. Une organisation partenaire facultative permet de regrouper plusieurs Points, comme les bureaux de La Poste du Togo, sans imposer ce niveau a tous les revendeurs.
_Avoid_: KORA Point unique pour tous les bureaux, compte entreprise achetant pour ses propres voyageurs

**Organisation partenaire KORA POINT**:
Regroupement facultatif de Points physiques d'un même partenaire institutionnel. Son espace web dédié réutilise les écrans KORA POINT de suivi avec des données agrégées par organisation et un filtre pour consulter un Point. Le solde disponible est calculé sur la somme des allocations, tandis que le plafond global non alloué est affiché séparément ; les alertes restent rattachées aux Points concernés. Le superviseur consulte les ventes, billets, commissions et remboursements, gère les coordonnées opérationnelles et le cycle de vie des Points, répartit leurs allocations et prépare/soumet des reversements groupés pour toute l'organisation. Il ne modifie ni les conditions commerciales globales, ni les comptes des membres, ni les rattachements entre organisations. Le trésorier consulte les mêmes données et prépare/soumet les mêmes reversements groupés sans gérer les Points. Seule Finance KORA autorise ou refuse les remboursements et valide les reversements ; les remboursements restent en lecture seule dans l'espace partenaire. Les deux rôles sont cloisonnés à leur organisation. Une organisation suspendue ou clôturée coupe leur accès et ferme leur session à la prochaine requête. KORA OPS ne peut clôturer une organisation que si l'encours de chacun de ses Points est nul ; cette clôture ne change ni le statut ni l'allocation des Points. Ce regroupement est distinct du compte organisationnel acheteur de voyages.
_Avoid_: Organisation obligatoire pour chaque boutique, Point principal COLIS

**Conditions commerciales KORA POINT**:
Pour un Point autonome, la commission, le plafond d'encours et le calendrier proviennent du Point ou, si une valeur est absente, des paramètres d'exploitation. Pour un Point rattaché, la commission et le calendrier sont définis par l'organisation et ne peuvent pas être remplacés au niveau du Point. Le plafond global appartient à l'organisation et les plafonds des Points sont des allocations positives dont la somme ne peut pas le dépasser. Une baisse du plafond global sous les allocations existantes ou une allocation sous l'encours est refusée. Une suspension conserve l'allocation ; la clôture d'un Point ne la libère qu'après solde de son encours. La clôture de l'organisation exige un encours nul sur chaque Point et ne clôture pas automatiquement les Points ni ne libère leurs allocations. Le délai de grâce reste un réglage global KORA. La vente fige ses conditions au paiement et un changement ne recalcule pas l'historique.
Les organisations et Points KORA POINT utilisent un même pays pour le rattachement. Les pays pris en charge sont BJ, TG et CI, en XOF ; le Ghana attend la prise en charge de sa devise GHS. Les organisations portent leurs identifiants légaux et leurs documents. Un Point rattaché n'ajoute pas de documents propres ; un Point autonome conserve son dossier documentaire.
_Avoid_: Taux contractuel implicite tire de la maquette, plafond mensuel de chiffre d'affaires, conditions locales d'un Point rattaché

**Reversement partenaire**:
Reglement net declare pour des ventes entieres, affecte explicitement a chaque Point. Seule la reception validee par Finance KORA diminue l'encours. Le declarant ne valide pas sa propre operation ; une difference reste a rapprocher.
_Avoid_: Transfert de caisse interne, encours libere par une simple preuve

**Restitution KORA POINT**:
Remboursement autorise par KORA, execute par le Point avant reversement ou par KORA apres reversement. L'obligation, la restitution declaree et sa confirmation sont distinctes ; le beneficiaire est le payeur initial derive du paiement, sans saisie. La confirmation corrige la commission au prorata et la dette selon le detenteur des fonds ; aucun credit Wallet fictif n'est cree.
_Avoid_: Annulation partenaire autonome, statut rembourse avant restitution effective

**Utilisateur partenaire**:
Personne identifiee par un compte personnel qui opere pour un KORA Point. Le bureau et le compte de connexion de son agent sont deux concepts distincts.
_Avoid_: Compte partage au nom du bureau, partenaire distributeur, agence postale representee par une personne fictive

**Accès web initial**:
Premiere remise d'un mot de passe provisoire par e-mail, avec SMS de repli si l'e-mail echoue, pour ouvrir KORA OPS Web a un agent, superviseur ou tresorier KORA POINT. Le mot de passe doit etre change a la premiere connexion et expire apres 72 heures s'il n'est pas utilise.
_Avoid_: Activation de l'acces passager mobile, code OTP, mot de passe permanent transmis par KORA

**Superviseur partenaire KORA POINT**:
Membre d'une organisation partenaire qui consulte les ventes, billets, commissions et remboursements agrégés de ses Points, avec filtre et détail par Point. Il crée et gère le cycle de vie des Points rattachés, répartit immédiatement les allocations sous le plafond global et prépare/soumet des reversements groupés pour toute son organisation. Il fournit l'identité et l'e-mail du compte agent lors de la création d'un Point ; les changements ultérieurs de compte et les renvois d'accès restent gérés par KORA OPS. Il ne gère pas les membres de l'organisation, ne change pas les conditions globales, ne transfère pas un Point vers une autre organisation et ne traite pas les décisions Finance sur les remboursements ou reversements.
_Avoid_: Responsable réseau KORA, administrateur partenaire, gestionnaire des comptes membres, éditeur des conditions commerciales globales, validateur Finance

**Trésorier partenaire KORA POINT**:
Membre d'une organisation partenaire qui consulte les mêmes ventes, billets, commissions et remboursements agrégés que son superviseur et prépare/soumet les reversements groupés de l'organisation. Il ne crée ni ne modifie les Points et leurs allocations ; Finance KORA reçoit et valide le reversement.
_Avoid_: Validateur de son propre reversement, gestionnaire des Points, trésorier KORA

**Encours a reverser d'un Point**:
Dette de reglement d'un KORA Point envers KORA, issue des encaissements detenus par le partenaire, des commissions retenues et des mouvements de reglement ou de correction justifies. Elle reste distincte du chiffre d'affaires et du montant des billets annules.
_Avoid_: Plafond mensuel de ventes, caisse physique, dette automatiquement effacee par une annulation

**Visite terrain KORA POINT**:
Intervention d'un utilisateur interne KORA sur un Point, enregistrée avec un type (visite qualité, suivi commercial ou contrôle PLV), une note entière de 0 à 10 et un compte rendu immuable. Des photos facultatives et des actions de suivi peuvent être jointes. Les organisations consultent les visites de leurs Points ; elles ne les créent ni ne les modifient.
_Avoid_: Contrôle effectué ou rédigé par un utilisateur partenaire, visite modifiable après enregistrement

**Action de suivi de visite**:
Tâche créée pendant l'enregistrement d'une visite, avec libellé, responsable et échéance. Le responsable KORA ou l'agent du Point signale sa réalisation ; un utilisateur KORA valide ou renvoie la tâche avec un motif. Une visite avec tâches se clôt lorsque toutes ses actions sont validées ; sans tâche, elle est clôturée à sa création.
_Avoid_: Checklist de contrôle PLV, validation par le responsable de la tâche, clôture à la simple déclaration de réalisation

**Commande PLV KORA POINT**:
Commande de supports de publicité sur le lieu de vente pour un Point : affiches A3, autocollants, bâche ou panneau. Elle porte une quantité, passe de Préparée à Livrée puis Installée et peut être annulée par KORA avec conservation du motif. La V1 ne suit ni prix ni stock ; les partenaires consultent les commandes en lecture seule.
_Avoid_: Inventaire PLV, tarif fournisseur, commande créée ou annulée par l'organisation partenaire

**Agence**:
Entite commerciale KORA ou l'argent circule (vente guichet, caisse, encaissement mobile money, terminal P501). L'agence se rattache aux utilisateurs et aux flux financiers, pas aux ressources d'exploitation. Un bus, une ligne, un arret ou un depart n'appartient pas a une agence — ce sont des ressources du reseau KORA. L'arret (ou le bus s'arrete) et l'agence (ou l'argent change de mains) sont deux concepts distincts qui peuvent coexister geographiquement sans etre lies structurellement.
_Avoid_: Lier un bus ou un depart a une agence; confondre l'arret avec l'agence

**Agence de vente**:
Agence qui réalise commercialement une vente de billets. Elle est conservée dans l’historique, même si le vendeur change ensuite d’agence, et couvre aussi les ventes à crédit entreprise. Les ventes en ligne sans intervention d’une agence et les anciennes ventes dont l’agence ne peut être établie avec certitude restent sans agence de vente connue.
_Avoid_: Agence actuelle du vendeur ; agence du départ ; réaffecter les ventes historiques lors d’une mutation

**Vente hors agence**:
Vente digitale sans agence historique de vente connue. Elle est présentée séparément du classement des agences afin de ne pas faire passer l'absence d'agence pour une agence commerciale.
_Avoid_: Afficher « Sans agence » comme une agence ordinaire ; attribuer une vente passager à l'agence qui a seulement été contactée

**Compte Mobile Money partagé**:
Compte de paiement rattaché à une agence et partagé par ses sessions de caisse. Il porte l’identité financière, les paiements et les journaux Mobile Money de l’agence ; il ne doit pas être recréé à chaque session ni additionné une seconde fois par guichet.
_Avoid_: Compte personnel de l’agent ; compte propre à une session ; additionner le même compte pour chaque guichet

**Compte actif**:
Compte Mobile Money partagé actuellement autorisé pour les nouvelles ouvertures de session et les nouveaux paiements. Une agence ne peut avoir qu’un seul compte actif par réseau.
_Avoid_: Tous les comptes historiques ; compte actif sur plusieurs réseaux ; compte sélectionnable sans session ouverte

**Compte historique**:
Ancien compte Mobile Money partagé conservé pour ses paiements, ses ledgers et son solde. Il reste visible et inclus dans la supervision financière, mais il est exclu des nouvelles sessions et des nouveaux paiements.
_Avoid_: Compte supprimé ; compte disponible pour encaisser ; solde transféré automatiquement vers le compte courant

**Remplacement de compte**:
Opération guidée qui désactive un compte actif et crée ou réactive un autre compte du même réseau. Elle exige une confirmation explicite, est atomique, et est refusée tant qu’une session ouverte utilise le compte sortant.
_Avoid_: Modifier l’identifiant en place ; supprimer l’ancien compte ; changer de compte sans conserver l’historique

**Compte organisationnel**:
Compte entreprise, ONG ou ambassade utilisé comme payeur pour des achats Bus à crédit. Il possède une référence métier KORA, un plafond mensuel et un cycle actif/inactif ; il est distinct des utilisateurs qui peuvent acheter ou référer l’organisation.
_Avoid_: Compte voyageur ; compte personnel d’un agent ; portefeuille Wallet

**Remise négociée**:
Avantage tarifaire défini sur un compte organisationnel, exprimé en pourcentage ou montant fixe et calculé sur le panier entier après une éventuelle promotion. Elle remplace la remise groupe pour l’achat self-service de cette organisation.
_Avoid_: Remise groupe ; promotion à code ; crédit entreprise

**Remise groupe**:
Avantage automatique paramétré par l’exploitation, avec activation, seuil de voyageurs et pourcentage ; elle s’applique aux groupes individuels éligibles, après une éventuelle promotion. L’achat portail entreprise utilise la remise négociée à sa place.
_Avoid_: Remise négociée ; promotion à code ; tarif dynamique

**Référent d’organisation**:
Utilisateur KORA actif, hors personnel interne, désigné comme contact principal d’un compte organisationnel. Affiché « Responsable du compte » dans l’interface. Une seule personne peut être référente active à la fois ; un remplacement conserve l’ancien rattachement comme inactif.
_Avoid_: Propriétaire du compte voyageur ; agent de caisse ; contact externe non authentifié

**Acheteur d’organisation**:
Utilisateur KORA actif, hors personnel interne, autorisé à utiliser un compte organisationnel dans le cadre des achats de groupe. Il peut être membre sans être le référent unique.
_Avoid_: Voyageur automatiquement créé ; référent obligatoire ; payeur personnel

**Voyageur déclaré**:
Personne enregistrée par le référent dans le roster « Membres voyageurs » de son compte organisationnel, sans nécessairement posséder un compte KORA. Sa fiche porte identité, téléphone normalisé (clé de dédoublonnage par compte) et pièce d’identité optionnelle. Seuls les voyageurs déclarés actifs sont éligibles à un achat self-service ; un voyageur retiré reste visible dans le roster et l’historique. Modèle `CompanyTraveler`, distinct du membre du compte (`CompanyAccountMember`) qui est un utilisateur authentifié rattaché à l’organisation.
_Avoid_: Acheteur d’organisation ; compte voyageur ; fiche voyageur d’une réservation

**Plafond de crédit mensuel**:
Montant maximal en FCFA qu’un compte organisationnel peut engager pendant le mois civil courant. Le disponible soustrait les débits confirmés et les réservations actives, ajoute les remboursements et ne se reporte pas automatiquement au mois suivant.
_Avoid_: Solde Wallet ; plafond par agence ; limite annuelle

**Crédit disponible**:
Montant calculé côté serveur qu’un compte organisationnel peut encore engager à crédit pour le mois courant. Il ne peut jamais être négatif et reste inaccessible aux utilisateurs sans permission Finance.
_Avoid_: Montant saisi par l’agent ; montant facturé ; encaissement de caisse

**Promotion à code**:
Offre commerciale déclenchée par la saisie d’un code promo au moment du devis tarifaire. Elle porte un avantage (pourcentage ou montant fixe), un périmètre (réseau, canal de vente, ligne ou segment O/D), une période d’application, une priorité et une limite d’utilisations optionnelle. Elle s’applique après la tarification dynamique et avant la remise groupe, un seul code par devis, et n’est jamais rétroactive sur les ventes confirmées. Son cycle de vie est brouillon → active ⇄ inactive → archivée ; « planifiée » et « expirée » sont des états d’affichage dérivés de la période, jamais stockés. Voir [l’ADR 0008](docs/adr/0008-promotions-a-code.md).
_Avoid_: Réduction automatique sans code (rôle de la règle de tarification dynamique), remise de volume (rôle de la remise groupe), promotion rétroactive

**Rédemption de promotion**:
Enregistrement de l’usage réel d’un code promo lorsque la réservation passe à l’état payée. Une seule rédemption existe par couple promotion/réservation ; elle porte le montant de remise gelé dans le devis et incrémente le compteur d’utilisations de façon atomique.
_Avoid_: Comptage au devis ou au blocage de siège ; double comptage d’une même vente

**Rentabilité**:
Lecture de gestion de la contribution d'un axe opérationnel (bus, ligne, départ, agence) sur une période de service : recette nette de service diminuée des coûts affectés. Elle n'est ni une comptabilité générale ni un résultat fiscal.
_Avoid_: Résultat comptable ; trésorerie ; marge brute de la maquette

**Période de service**:
Période bornée par la date de départ planifiée des départs (`scheduled_at`, fuseau `Africa/Porto-Novo`). Elle rattache une vente anticipée au voyage qu'elle finance, indépendamment de sa date d'encaissement.
_Avoid_: Période d'encaissement ; période de caisse

**Recette nette de service**:
Paiements confirmés en XOF rattachés aux réservations des départs de la période, diminués des annulations reconnues sur ces paiements. Elle peut différer de la recette nette de trésorerie de l'écran Recettes.
_Avoid_: Recette nette de caisse ; chiffre d'affaires fiscal

**Contribution**:
Recette nette de service d'un axe diminuée de ses coûts directs et répartis. Elle mesure ce que l'axe apporte après ses coûts d'exploitation affectés.
_Avoid_: Marge nette réseau ; résultat opérationnel

**Coût affecté**:
Dépense approuvée rattachée à un départ, un bus ou une ligne, directement ou par une règle de répartition déterministe.
_Avoid_: Charge imputée arbitrairement ; coût estimé sans source

**Coût non affecté**:
Dépense approuvée qui ne peut pas être rattachée à l'axe consulté, notamment une dépense d'agence pour un axe opérationnel. Il reste visible au niveau réseau et n'entre jamais dans la marge d'un axe.
_Avoid_: Coût nul ; charge supprimée

**TCO opérationnel**:
Somme des dépenses approuvées affectées à un bus sur la période (carburant, maintenance, péages, assurance, leasing, pneus, autres). Il exclut acquisition, amortissement, financement et paie.
_Avoid_: TCO comptable complet ; valeur du véhicule

**Coût/km**:
TCO opérationnel du bus divisé par ses kilomètres de la période. La distance vient du GPS quand sa couverture est suffisante, sinon de la distance planifiée marquée « estimé ».
_Avoid_: Coût/km théorique sans badge ; distance odomètre

**Seuil de rentabilité**:
Nombre de sièges à vendre pour couvrir les coûts fixes, calculé à partir de la contribution par siège (recette nette moins coûts variables). Il est exprimé en sièges, jamais en recette.
_Avoid_: Seuil en FCFA ; seuil marketing

**Taux de couverture des coûts**:
Part des coûts de la période effectivement affectée aux axes opérationnels. Il rend visible la dépendance à la saisie du centre de coût.
_Avoid_: Taux de remplissage ; couverture GPS

**Résultat opérationnel réseau**:
Recette nette de service du réseau diminuée des coûts affectés et des coûts non affectés. C'est le seul résultat qui intègre les coûts hors axes.
_Avoid_: Somme des contributions d'axes ; résultat net comptable

**Vue filtrée par entité**:
Périmètre du rapport de rentabilité restreint à un bus, une ligne ou une agence de vente, activé en cliquant une carte de contribution et matérialisé par une pastille escamotable. Il reste orthogonal à l'axe de regroupement : filtré sur un bus, l'axe Ligne liste les lignes parcourues par ce bus. Un seul filtre à la fois : une nouvelle carte remplace le filtre actif. Sous ce périmètre, le rapprochement caisse réseau et le TCO (hors filtre bus) sont masqués, et les coûts réseau non affectés restent hors de la vue avec une note explicative.
_Avoid_: Filtre par axe ; cumul de plusieurs entités ; rapprochement caisse d'une entité

**Trajectoire de la période**:
Courbe de rentabilité cumulée sur la période : recette nette de service (datée par départ) face aux coûts engagés (datés par dépense), avec en pointillé la recette cumulée de la période précédente alignée jour par jour. Elle est masquée sous trois points de données et suit la vue filtrée par entité.
_Avoid_: Courbe de trésorerie ; recette par date d'encaissement ; comparaison par dates calendaires

**Jour de bascule**:
Premier jour de la trajectoire où la recette nette cumulée couvre les coûts engagés cumulés. La période passe alors en positif dans le temps, indépendamment de son résultat final.
_Avoid_: Seuil de rentabilité (mesuré en sièges, pas en temps) ; premier jour bénéficiaire d'un départ

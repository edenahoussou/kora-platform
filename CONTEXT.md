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

**Vente via l'application passager**:
Vente finalisée dans le parcours mobile, dont la source d'encaissement peut être détaillée par fournisseur d'intégration et réseau ou par un moyen mobile reconnu.
_Avoid_: Fusionner l'application passager avec une ligne générique « En ligne » ; rattacher automatiquement une vente mobile à une agence

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

**Points de fidelite**:
Unites de recompense propres au voyageur eligible, acquises par les voyages effectues et les bonus, utilisables pour payer des billets Bus. Leur valeur d'usage est de 1 point pour 1 FCFA ; ils ne constituent pas du cash retirable ou transferable.
_Avoid_: Argent disponible, remise immediate sur le voyage qui les genere, KORA POINT

**KORA POINT**:
Point physique ou relais partenaire susceptible de vendre ou supporter les services KORA dans une phase ulterieure. Hors MVP Bus.
_Avoid_: Guichet agence KORA OPS Web du MVP

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

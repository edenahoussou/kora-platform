# KORA BUS

KORA BUS est le contexte du MVP valide par le client. Ce glossaire fixe le vocabulaire commun entre le client, le pilotage projet et l'equipe technique.

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

**Descente anticipee**:
Evenement enregistre par le convoyeur lorsqu'un passager descend avant sa destination initiale, afin de liberer les segments non parcourus.
_Avoid_: Annulation

**MECeF**:
Integration de fiscalisation normalisee associee a chaque vente confirmee, avec reprise idempotente en cas d'indisponibilite.
_Avoid_: Facture billet

**Compte voyageur**:
Identite client KORA retrouvee ou creee automatiquement a partir d'un telephone personnel normalise lors de l'achat ou de l'emission d'un billet. Elle existe avec son Wallet meme sans acces active a l'application ; son titulaire peut voyager, payer pour un tiers ou faire les deux.
_Avoid_: Inscription obligatoire dans l'application, fiche voyageur sans telephone personnel

**Fiche voyageur**:
Informations de la personne transportee associees a son billet, distinctes du payeur. Une personne sans telephone personnel conserve sa fiche et son billet sans disposer d'un compte de fidelite ; un telephone de contact tiers ne prouve pas qu'elle est titulaire de ce compte.
_Avoid_: Compte du parent, compte du payeur, compte partage par plusieurs voyageurs

**Activation de l'acces**:
Ouverture de l'acces a l'application sur un compte existant, apres verification du telephone par SMS et definition d'un PIN.
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

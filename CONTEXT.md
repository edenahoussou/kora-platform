# KORA BUS

KORA BUS est le contexte du MVP valide par le client. Ce glossaire fixe le vocabulaire commun entre le client, le pilotage projet et l'equipe technique.

## Language

**Ecosysteme KORA cible**:
A terme, KORA comprend une application client multi-service, une application conducteurs Ride, une application operationnelle mobile Bus/terrain et un ERP web interne.
_Avoid_: MVP super-app

**MVP KORA BUS**:
Plateforme Bus exploitable de bout en bout en 12 semaines, couvrant vente, paiement, billet, fiscalisation, exploitation, embarquement hors ligne et revente de segments liberes.
_Avoid_: MVP super-app, MVP Colis, MVP Wallet

**KORA App**:
Application mobile client cible. A terme elle portera Bus, Ride, Colis et Wallet. Dans le MVP, elle ne porte que le parcours passager Bus.
_Avoid_: Creer Ride, Colis ou Wallet dans le MVP

**KORA App Passager Bus**:
Perimetre MVP de KORA App : rechercher un depart, reserver, payer, consulter un billet et suivre le bus.
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

**Wallet**:
Solde ou credit interne KORA pour paiements, avoirs et remboursements client. Il est hors MVP Bus.
_Avoid_: Mobile Money, registre de remboursement MVP

**KORA POINT**:
Point physique ou relais partenaire susceptible de vendre ou supporter les services KORA dans une phase ulterieure. Hors MVP Bus.
_Avoid_: Guichet agence KORA OPS Web du MVP

**Agence**:
Entite commerciale KORA ou l'argent circule (vente guichet, caisse, encaissement mobile money, terminal P501). L'agence se rattache aux utilisateurs et aux flux financiers, pas aux ressources d'exploitation. Un bus, une ligne, un arret ou un depart n'appartient pas a une agence — ce sont des ressources du reseau KORA. L'arret (ou le bus s'arrete) et l'agence (ou l'argent change de mains) sont deux concepts distincts qui peuvent coexister geographiquement sans etre lies structurellement.
_Avoid_: Lier un bus ou un depart a une agence; confondre l'arret avec l'agence

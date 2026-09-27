# Catalogue des notifications KORA BUS

Inventaire et cible d’implémentation de MEZ-98. Le catalogue sépare l’audience OPS, l’audience Passager et les messages transactionnels envoyés à un contact. « Inbox » désigne une notification persistée dans l’application; les autres canaux sont des tentatives de livraison complémentaires.

## Règles communes

- Les notifications OPS et Passager partagent la table Laravel `notifications`, mais chaque ligne porte une audience distincte (`ops` ou `passenger`). Les API filtrent par audience, utilisateur authentifié et UUID public. Une personne ne peut ni lire ni marquer comme lue la notification d’un autre compte.
- OPS Web, OPS Mobile et l’app Passager ont des contrats de lecture distincts par audience. OPS Web et OPS Mobile conservent leur contrat OPS existant.
- Le registre `ops_notification_events` conserve l’événement métier, la déduplication, le ciblage et les tentatives OPS. Les classes Laravel `Notification` et leurs channels réalisent les livraisons. Cette couche métier ne remplace donc pas les channels Laravel.
- Le canal database constitue l’inbox durable. Le push est une tentative séparée : il nécessite un appareil inscrit, une préférence de compte active, l’autorisation du navigateur ou de l’OS et une configuration Firebase valide. Un refus, une panne FCM ou un token invalide ne supprime pas l’inbox.
- Une notification in-app OPS Web ou Passager tente le push sur chaque appareil correspondant et inscrit. La criticité de l’événement détermine le niveau d’urgence et la possibilité de désactivation; elle ne détermine pas si l’inbox a droit au push.
- Les résumés push n’embarquent pas de montant, numéro de téléphone, nom de voyageur, référence de réservation, jeton ou note interne. L’app ouvre ensuite le centre ou la ressource après authentification.
- Les profils OPS doivent avoir un compte actif, `notifications.view` et la permission métier requise. Les permissions filtrent les destinataires; les associations au départ, à l’agence et au terminal appliquent le périmètre.

## Canaux et contrats

| Canal | Audience et usage | État |
| --- | --- | --- |
| Inbox database | Centre Web OPS, liste OPS Mobile et centre Passager. États lu/non lu conservés dans la table Laravel `notifications`. | Actif pour les événements catalogués. L’historique OPS est migré en conservant UUID et lecture. |
| Push Web FCM | Notification système du navigateur, y compris lorsque l’application n’est pas au premier plan. Tentée pour les entrées d’inbox OPS Web; permission du navigateur et inscription du navigateur requises. | Actif si le navigateur et Firebase sont configurés. |
| Push Android/iOS FCM | Notification système sur appareils mobiles Passager inscrits après opt-in explicite, accord OS et préférence du compte. | Le code d’inscription et d’envoi est en place; il dépend des App IDs mobiles dans les paramètres, de la configuration native/APNs et de l’inscription effective du terminal. |
| Temps réel OPS | Événement privé `ops-notifications.{userUuid}` pour accélérer l’affichage Web. | Actif si un driver de broadcast est configuré; l’API reste la source fiable. |
| E-mail OPS | Canal additionnel pour les événements dont `sendsMail()` est vrai. Directeurs des opérations en destinataires, administrateurs système en copie; préférences e-mail appliquées. | Actif si le courriel OPS est activé, le destinataire a une adresse et le canal est configuré. La règle e-mail est indépendante du push. |
| WhatsApp transactionnel | OTP et billet PDF envoyé au numéro joignable du payeur. | Actif selon disponibilité Genuka/WAHA et livraison. Ces messages ne sont pas l’inbox. |
| SMS | Aucun fournisseur ni format transactionnel défini dans ce ticket. | Non disponible; recensé comme canal futur. |

## Notifications OPS

Émetteur technique : API et jobs Laravel déclenchés par OPS Web, OPS Mobile/P501 ou les workflows métier. Les notifications sont persistées pour les utilisateurs admissibles. Un email est tenté seulement lorsque « Email » vaut Oui. Chaque notification d’inbox Web éligible tente en plus le push sur les navigateurs inscrits, sans filtre de criticité.

**État :** les types OPS `departure_*`, `manifest_stale`, `terminal_revoked`, `scan_conflict`, `operation_rejected`, `sync_*`, `no_show_closed`, `print_error`, `incident_*`, `fiscalization_failed`, `cash_variance_submitted` et les deux types d’assistance existaient avant ce plan. MEZ-98 migre leur inbox, découple push et email et corrige le routage de l’assistance. `fiscal_credit_note` existait en notification Laravel séparée; MEZ-98 l’intègre à l’inbox OPS.

| Type | Déclencheur et émetteur | Destinataires et périmètre | Criticité / action | Inbox / push Web / email |
| --- | --- | --- | --- | --- |
| `departure_assigned` | `DepartureOpsNotificationObserver`: membre d’équipage affecté à un départ créé ou modifié. | Membre affecté; directeurs des opérations de l’agence du sujet. | Info; ouvrir le départ. | Oui / Oui si opt-in / Non |
| `departure_modified` | Observer: date, heure, bus, ligne, annulation ou retrait d’équipage. | Équipage actuel et membres retirés; directeurs de l’agence du sujet. | Info; ouvrir le départ. | Oui / Oui si opt-in / Non |
| `manifest_stale` | `PublishManifestStaleNotificationJob`: une vente, annulation ou mise à jour change le manifeste. Déduplication par départ et version. | Équipage du départ. | Warning; actualiser le manifeste. | Oui / Oui si opt-in / Non |
| `terminal_revoked` | `RevokeOpsSessionAction`: terminal retiré, perdu, en maintenance ou réaffecté. | Utilisateur de la session concernée; directeurs de son agence; administrateurs système. | Critique; se réauthentifier. | Oui / Oui si opt-in / Oui |
| `scan_conflict` | `ProcessBoardingScanAction`: même billet scanné sur plusieurs terminaux. | Équipage du départ et utilisateur du terminal concerné; directeurs du sujet; administrateurs système. | Critique; examiner le conflit. | Oui / Oui si opt-in / Oui |
| `operation_rejected` | `ProcessSyncBatchAction`: un lot synchronisé contient des opérations rejetées. | Agent ayant soumis le lot; directeurs de son agence; administrateurs système. | Warning; examiner les opérations rejetées. | Oui / Oui si opt-in / Oui |
| `sync_completed` | `ProcessSyncBatchAction`: lot P501 traité. | Agent ayant soumis le lot. | Info; aucune action. | Oui / Oui si opt-in / Non |
| `sync_failed` | `ProcessSyncBatchAction`: traitement du lot échoué. | Agent ayant soumis le lot; directeurs de son agence; administrateurs système. | Warning; réessayer la synchronisation. | Oui / Oui si opt-in / Oui |
| `no_show_closed` | `CloseNoShowsForDepartureAction`: clôture des absences du départ. | Équipage du départ; directeurs de l’agence du sujet. | Info; aucune action. | Oui / Oui si opt-in / Non |
| `print_error` | `TerminalOpsMobileController::activity`: impression en échec (`copy_failed` ou `pdf_failed`). | Auteur de l’activité; directeurs de son agence; administrateurs système. Un départ est joint uniquement si l’auteur y est affecté. | Warning; vérifier l’imprimante. | Oui / Oui si opt-in / Oui |
| `departure_departed` | Observer: départ passe à l’état parti. | Équipage et directeurs de l’agence du sujet. | Info; ouvrir le départ. | Oui / Oui si opt-in / Non |
| `incident_reported` | `ReportIncidentAction`: incident terrain signalé. | Équipage du départ; directeurs de l’agence du sujet; administrateurs système. | Warning; ouvrir le départ. | Oui / Oui si opt-in / Oui |
| `incident_critical` | `ReportIncidentAction`: incident classé accident ou sécurité. | Équipage du départ; directeurs de l’agence du sujet; administrateurs système. | Critique, non désactivable; ouvrir le départ. | Oui / Oui si opt-in / Oui |
| `fiscalization_failed` | `FiscalInvoiceObserver` après échec définitif de fiscalisation. | Comptes avec `fiscalization.view` du périmètre réseau; demandeur ajouté s’il existe. | Critique; accès fiscalisation selon permission. | Oui / Oui si opt-in / Oui |
| `cash_variance_submitted` | `CloseCashSessionAction`: clôture soumise avec écart. | Utilisateurs actifs `cash.approve-variance` de l’agence de la caisse. | Info; approuver/contrôler l’écart. | Oui / Oui si opt-in / Oui |
| `assisted_booking_requested` | `AssistedBookingWorkflow`: nouvelle demande d’assistance non prise en charge. | Si aucune agence n’est affectée: agents ayant `assisted-bookings.claim` de toutes les agences, plus profils `view-network`. Si une agence est définie: agents de cette agence plus réseau. | Info; ouvrir/traiter la demande selon permission. | Oui / Oui si opt-in / Oui |
| `assisted_booking_reassigned` | `AssistedBookingWorkflow::reassign`: demande transférée à une autre agence. | Agents autorisés à la réclamer dans l’agence cible, profils réseau et ancien agent affecté s’il existe encore et peut accéder à l’inbox. | Info; ouvrir la demande selon permission. | Oui / Oui si opt-in / Oui |
| `departure_delayed` | `RecordDepartureDelayAction`: retard opérationnel supérieur à zéro. | Équipage du départ; directeurs de l’agence du sujet; administrateurs système. | Info; ouvrir le départ. | Oui / Oui si opt-in / Oui |
| `fiscal_credit_note` | `IssueFiscalCreditNoteJob`: demande, certification ou échec d’une facture d’avoir. | Demandeur et comptes `fiscalization.credit`. | Info; ouvrir la fiscalisation si autorisé. | Oui / Oui si opt-in / Non |

Les demandes d’assistance n’ont ni équipe Support OPS, ni priorité dans l’application. Les appels et messages WhatsApp sont pris en charge par une équipe externe au workflow OPS. Les agents d’agence titulaires de `assisted-bookings.claim` prennent les demandes; les profils réseau peuvent les consulter et les réaffecter selon leurs permissions.

## Notifications Passager BUS

Déclencheurs backend : actions de réservation, jobs de billetterie, observer des départs, réglages opérationnels et observer des remboursements. L’inbox Passager est isolée des notifications OPS par `audience=passenger` et par le compte utilisateur.

**État :** les notifications Passager du tableau sont ajoutées par MEZ-98. Le message WhatsApp de billet est antérieur et reste un canal transactionnel séparé; les rappels sont configurables (24 h et 2 h par défaut), et le seuil de retard vaut 1 minute par défaut.

| Type | Déclencheur et contenu | Destinataires | Inbox / push mobile / autre canal |
| --- | --- | --- | --- |
| `reservation_confirmed` | Émission réussie du billet après paiement confirmé. Message de confirmation et billet consultable dans l’app. | Payeur si réservation personnelle; sinon utilisateurs rattachés aux passagers. Sans compte correspondant, pas d’inbox. | Oui / Oui si appareil opt-in / le WhatsApp du billet continue d’être envoyé au payeur. |
| `ticket_issuance_failed` | Échec définitif d’émission après les tentatives du job; n’est pas émis pour une erreur qui sera encore réessayée. | Même règle payeur/passagers liés; aucun utilisateur associé signifie pas d’inbox. | Oui / Oui si appareil opt-in / aucun email Passager ajouté. |
| `departure_modified` | Date, heure, bus ou ligne modifiés pour un départ lié à un billet émis. | Payeur; sinon comptes des passagers associés. | Oui / Oui si opt-in. |
| `departure_cancelled` | Départ annulé pour un billet émis. | Payeur; sinon comptes des passagers associés. | Oui / Oui si opt-in. |
| `departure_delayed` | ETA calculé dépasse le seuil configuré (1 minute par défaut); une même ETA n’est notifiée qu’une fois. | Payeur; sinon comptes des passagers associés pour les réservations émises sur ce départ. | Oui / Oui si opt-in. |
| `departure_reminder` | Rappel différé avant le départ, initialisé à 24 h et 2 h. Les jobs sont vérifiés à l’envoi contre le départ actuel et l’état du billet. | Payeur; sinon comptes des passagers associés. | Oui / Oui si opt-in. |
| `refund_pending`, `refund_completed`, `refund_failed` | Création ou changement d’état d’un remboursement lié à une réservation BUS. | Payeur; sinon comptes des passagers liés. | Oui / Oui si opt-in; aucune donnée de montant dans le push. |

L’API Passager est authentifiée et ne renvoie que les entrées de son audience. Elle expose liste paginée, compteur non lu, lecture individuelle et lecture totale. Le clic ouvre la section Voyages après marquage comme lu. Le client n’affiche aucune notification de démonstration. L’opt-in push se fait depuis les préférences avec demande de permission OS; la désactivation et la déconnexion retirent le token de l’appareil courant ou désactivent ceux du compte selon l’action.

Le ticket WhatsApp existant et l’inbox sont deux livraisons du même parcours métier, mais ils ne sont pas interchangeables : WhatsApp livre un document au payeur joignable; l’inbox est attachée aux comptes KORA des bénéficiaires. Le présent ticket n’ajoute pas d’email Passager.

## Messages transactionnels hors inbox

| Message | Émetteur et destinataire | Canaux actifs |
| --- | --- | --- |
| OTP | `IssueOtpChallengeAction`, au compte qui demande l’authentification ou la vérification. | WhatsApp Genuka puis WAHA; repli e-mail lorsque configuré et joignable. |
| Billet PDF | `IssueReservationTicketsJob` après émission, un message par ticket, envoyé au payeur joignable; renvoi possible depuis le billet. | WhatsApp Genuka puis WAHA; suivi dans `whatsapp_deliveries`. |
| Réinitialisation du mot de passe | Notification Laravel `ResetPassword` vers l’adresse du compte. | E-mail. |

## Hors périmètre

- Console globale de configuration des alertes, santé des canaux, journal multicanal, tests et renvois.
- SMS tant qu’aucun fournisseur, modèle, consentement ou coût n’a été défini.
- Inbox Passager pour KORA Wallet, Colis ou RIDE; notifications Wallet, Colis et RIDE de la maquette ne sont pas des événements KORA BUS.
- Nouveaux emails transactionnels Passager.

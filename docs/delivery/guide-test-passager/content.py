# -*- coding: utf-8 -*-
"""Contenu du guide de test de l'application Passager KORA EXPRESS.

Séparé du rendu (`build_guide.py`) pour une seule raison : les captures
d'écran arrivent par vagues. On régénère le PDF sans retoucher au texte,
et les emplacements encore vides se signalent eux-mêmes dans le document.

Chaque libellé cité entre « » est repris mot pour mot de
`apps/kora-app-passenger/assets/languages/fr.json` : un guide de test qui
paraphrase les écrans fait douter le testeur de ce qu'il voit.
"""

APP_VERSION = "1.0.0+7"

# --- Emplacements de capture ------------------------------------------
#
# Clé = nom du fichier attendu dans `screens/`, sans extension.
# Valeur = ce qu'il faut avoir à l'écran au moment du déclenchement.
SHOTS = {
    "01-splash": "L'écran de démarrage, logo KORA centré.",
    "02-onboarding-1": "Onboarding 1/3 — « Trouvez votre départ ».",
    "03-onboarding-2": "Onboarding 2/3 — « Choisissez la place ».",
    "04-onboarding-3": "Onboarding 3/3 — « Embarquez ».",
    "05-welcome": "L'écran d'accueil visiteur, avant tout compte.",
    "06-signup-phone": "Inscription, mode téléphone (celui par défaut).",
    "07-signup-email": "Inscription après bascule sur « Inscription avec votre adresse e-mail ? ».",
    "08-otp": "Saisie du code à 6 chiffres, un chiffre déjà entré.",
    "09-create-pin": "Création du PIN, premier écran (sans aucun chiffre saisi).",
    "10-permissions": "« Presque terminé » — nom et téléphone préremplis, autorisations visibles.",
    "11-login-email": "Connexion en mode e-mail (le mode par défaut).",
    "12-login-phone": "Connexion après bascule sur « Connexion avec votre numéro de téléphone ? ».",
    "13-login-pin": "Clavier PIN de la connexion.",
    "14-new-device": "« Nouvel appareil détecté ».",
    "15-home": "L'accueil connecté, haut de page (salutation + formulaire).",
    "16-home-quick": "L'accueil, bas de page : accès rapides et carte aller-retour.",
    "17-colis-blank": "L'accueil après avoir touché « Colis » dans la "
    "barre du bas : rien ne bouge, l'onglet Accueil reste actif.",
    "18-origin": "Sélection de la ville de départ, liste ouverte.",
    "19-date": "Calendrier « Quand partez-vous ? ».",
    "20-passengers": "« Combien de passagers ? ».",
    "21-departures-loading": "« Recherche en cours ».",
    "22-departures": "Liste des départs trouvés, au moins deux cartes visibles.",
    "23-departures-filters": "Le panneau « Trier et filtrer » ouvert.",
    "24-departures-empty": "« Aucun voyage ce jour-là ».",
    "25-departure-detail": "Le détail d'un départ (horaires, escales, prix).",
    "26-bus-search": "« Réserver un voyage » en mode aller-retour, champs remplis.",
    "27-return-date": "Calendrier « Quand rentrez-vous ? ».",
    "28-return-departures": "Liste des départs retour, bandeau « Retour » visible.",
    "29-return-empty": "« Aucun retour » (aucun départ sur la date retour).",
    "68-signup-delivery-failed": "L'inscription refusée faute de canal : "
    "le message du serveur, au-dessus du bouton.",
    "65-group-seats": "Le plan des sièges d'un groupe : autant de sièges "
    "retenus que de voyageurs, et le compteur qui le confirme.",
    "66-security": "« Sécurité et appareils » : le PIN et la liste des "
    "appareils connectés.",
    "67-change-pin": "« Votre nouveau PIN » : le clavier de saisie du code.",
    "61-group-passengers": "Le sélecteur de passagers poussé au maximum du "
    "groupe, le bouton « + » devenu inactif.",
    "62-group-booking": "« Réservation de groupe » : le nombre de voyageurs "
    "et le total estimé.",
    "63-group-discount": "« Votre remise groupe » : la remise appliquée et "
    "ce qu'elle fait économiser.",
    "64-group-discount-pending": "« Bientôt une remise » : le nombre de "
    "voyageurs qui manquent pour y avoir droit.",
    "30-seats-outbound": "Plan des sièges de l'aller, un siège sélectionné, décompte affiché.",
    "31-seats-inbound": "Plan des sièges du retour.",
    "32-hold-expired": "« Votre réservation a expiré ».",
    "33-passenger-form": "Formulaire voyageur, astérisques visibles sur les champs obligatoires.",
    "34-passenger-form-intl": "Formulaire voyageur d'un trajet transfrontalier, champ fièvre jaune présent.",
    "35-summary": "« Vérifiez votre voyage » — détail du prix et les deux boutons de sortie.",
    "36-summary-promo": "Le bloc « Code promo », code saisi.",
    "37-payment-method": "« Comment souhaitez-vous payer ? ».",
    "38-momo-operators": "Mobile Money, le choix d'opérateur avec les logos.",
    "39-momo-filled": "Mobile Money, opérateur choisi et numéro saisi, encart « Avant de valider » visible.",
    "40-payment-pending": "« Paiement en cours de vérification… ».",
    "41-payment-success": "L'écran de paiement réussi.",
    "42-payment-failed-held": "« Paiement refusé » avec les sièges encore "
    "retenus — écran que le serveur ne produit pas aujourd'hui, voir la "
    "note du chapitre.",
    "43-payment-failed-lost": "« Paiement refusé » après relâchement des sièges.",
    "44-assist-request": "« Réserver avec un agent » — choix WhatsApp / Appel.",
    "45-assist-whatsapp": "La discussion WhatsApp avec le message KORA prérédigé.",
    "46-assist-status": "« Votre demande » — suivi, chip de statut visible.",
    "47-trips-upcoming": "« Mes voyages » : les onglets et leurs "
    "compteurs, avec au moins un billet visible.",
    "48-trips-pending": "« Mes voyages », onglet « En attente ».",
    "49-ticket-detail": "Le détail d'un billet confirmé.",
    "50-ticket-qr": "Le QR Code plein écran.",
    "51-group-tickets": "Les billets d'un groupe : un QR par voyageur.",
    "52-pdf-downloading": "Le téléchargement du billet en cours.",
    "53-pdf-ready": "Le billet enregistré, action « Ouvrir le billet » disponible.",
    "54-pdf-opened": "Le PDF ouvert dans le lecteur du téléphone.",
    "55-share": "La feuille de partage du système, WhatsApp visible.",
    "56-share-whatsapp": "Le billet reçu dans une discussion WhatsApp.",
    "57-offline-ticket": "Le billet consulté en mode avion.",
    "58-offline-qr": "Le QR Code affiché sans réseau.",
    "59-offline-pdf": "Le message « Hors connexion : voici le billet déjà enregistré… ».",
    "60-session-expired": "Le retour automatique sur la connexion après expiration de session.",
}

INTRO = {
    "title": "À propos de ce guide",
    "lead": (
        "Ce document sert à <strong>tester</strong> l'application Passager "
        "KORA EXPRESS, pas à la vendre. Chaque section suit le même ordre : "
        "ce qu'on veut vérifier, les gestes à faire, le résultat attendu, "
        "et les cas d'erreur à provoquer volontairement. Un test qui ne "
        "casse rien ne prouve pas grand-chose."
    ),
    "blocks": [
        (
            "Ce qu'il vous faut avant de commencer",
            [
                "Un téléphone Android avec l'application installée "
                "(version <strong>%s</strong>)." % APP_VERSION,
                "Un numéro de téléphone capable de recevoir un message "
                "WhatsApp, ou une adresse e-mail relevable : le code de "
                "vérification y arrive.",
                "Un accès réseau. Le chapitre « hors ligne » vous demandera "
                "au contraire de le couper.",
                "Les numéros de test Mobile Money ci-dessous. "
                "<strong>N'utilisez jamais un numéro Mobile Money réel</strong> : "
                "l'environnement de test ne débite rien, un numéro réel si.",
            ],
        ),
        (
            "Comment lire les étapes",
            [
                "Les textes entre guillemets — « Recevoir mon code » — sont "
                "ceux de l'application, au mot près. Si vous lisez autre "
                "chose à l'écran, c'est déjà un écart à signaler.",
                "Un encadré doré annonce le <strong>résultat attendu</strong>. "
                "C'est lui qui décide si le test passe ou non.",
                "Un encadré bordé annonce un <strong>cas à provoquer</strong> : "
                "une panne volontaire, pour vérifier que l'application s'en "
                "sort proprement.",
            ],
        ),
    ],
    "testdata": [
        ("Paiement Mobile Money — succès", "+229 01 66 00 00 01"),
        ("Paiement Mobile Money — échec", "+229 01 66 00 00 00"),
        ("Opérateurs attendus", "MTN · Moov · Celtiis · Wave"),
        ("Délai de rétention des sièges", "visible en haut du plan des sièges"),
    ],
}

CHAPTERS = [
    # ------------------------------------------------------------------ 1
    {
        "eyebrow": "Premier contact",
        "title": "Installer et ouvrir l'application",
        "intro": (
            "Le tout premier lancement n'arrive qu'une fois. Si vous avez "
            "déjà ouvert l'application sur ce téléphone, désinstallez-la "
            "puis réinstallez-la pour retrouver cette séquence — sinon "
            "l'onboarding ne reviendra plus, et c'est voulu."
        ),
        "blocks": [
            ("step", "Ouvrir l'application", (
                "Touchez l'icône KORA EXPRESS. L'écran de démarrage affiche "
                "le logo pendant que l'application décide où vous envoyer : "
                "onboarding au premier lancement, accueil si une session "
                "est encore valide, connexion sinon."
            )),
            ("shots", [("01-splash", "Écran de démarrage"),
                       ("02-onboarding-1", "Onboarding 1/3")]),
            ("step", "Parcourir les trois écrans d'introduction", (
                "Faites défiler : « Trouvez votre départ », « Choisissez la "
                "place », « Embarquez ». « Passer » saute directement à la "
                "fin, « Commencer » clôt la séquence sur le dernier écran."
            )),
            ("shots", [("03-onboarding-2", "Onboarding 2/3"),
                       ("04-onboarding-3", "Onboarding 3/3")]),
            ("expect", (
                "Les trois écrans défilent sans saccade, l'indicateur de "
                "page suit, et « Commencer » mène à l'écran d'accueil "
                "visiteur. L'onboarding ne doit <strong>plus jamais</strong> "
                "réapparaître aux lancements suivants."
            )),
            ("shot", "05-welcome", "Accueil visiteur"),
            ("check", (
                "Fermez l'application depuis le sélecteur de tâches, puis "
                "rouvrez-la. Vous devez arriver directement sur la "
                "connexion ou l'accueil visiteur — jamais sur l'onboarding."
            )),
        ],
    },
    # ------------------------------------------------------------------ 2
    {
        "eyebrow": "Compte",
        "title": "Créer un compte",
        "intro": (
            "L'inscription se fait <strong>par numéro de téléphone par "
            "défaut</strong> : c'est le canal le plus sûr au Bénin, et le "
            "code arrive sur WhatsApp. L'adresse e-mail reste disponible "
            "d'un seul geste, pour qui préfère."
        ),
        "blocks": [
            ("step", "Ouvrir l'inscription", (
                "Depuis l'accueil visiteur, choisissez de créer un compte. "
                "L'écran s'ouvre sur « Votre numéro de téléphone », avec "
                "l'indicatif +229 présélectionné et le libellé « Numéro "
                "WhatsApp »."
            )),
            ("step", "Renseigner nom et numéro", (
                "Le <strong>nom complet est obligatoire</strong> pour une "
                "inscription par téléphone : il figurera sur vos billets et "
                "permet au convoyeur de vous reconnaître. L'inscription "
                "par e-mail, elle, ne le demande pas — le nom est alors "
                "réclamé juste après, à l'étape « Presque terminé »."
            )),
            ("shots", [("06-signup-phone", "Inscription par téléphone"),
                       ("07-signup-email", "Inscription par e-mail")]),
            ("check", (
                "Touchez « Recevoir mon code » avec un numéro incomplet. "
                "Vous devez lire « Numéro incomplet » sous le champ, et "
                "<strong>aucun code ne doit partir</strong>. Essayez aussi "
                "un numéro sans le bon préfixe : le message rappelle qu'« un "
                "numéro Bénin commence par +229 »."
            )),
            ("check", (
                "Essayez un numéro <strong>sans WhatsApp</strong>. Le "
                "serveur ne peut alors livrer aucun code, et l'écran doit "
                "le dire : « Impossible d'envoyer le code. Réessayez plus "
                "tard. » Le message s'affiche <strong>au-dessus du "
                "bouton</strong>, là où le clavier ne le cache pas — un "
                "bouton qui tourne puis ne dit rien laisse croire à une "
                "panne de l'application."
            )),
            ("shot", "68-signup-delivery-failed", "Code impossible à livrer"),
            ("step", "Basculer vers l'e-mail, puis revenir", (
                "Le lien « Inscription avec votre adresse e-mail ? » change "
                "de mode. Vérifiez que ce que vous avez déjà tapé n'est pas "
                "perdu au retour, et que le texte d'accompagnement suit le "
                "mode : WhatsApp d'un côté, e-mail de l'autre."
            )),
            ("step", "Saisir le code reçu", (
                "Six chiffres. Le code est à usage unique et expire. "
                "Vérifiez le renvoi de code, et le comportement d'un code "
                "volontairement faux."
            )),
            ("shots", [("08-otp", "Saisie du code"),
                       ("09-create-pin", "Création du PIN")]),
            ("step", "Choisir un PIN", (
                "Quatre chiffres, à confirmer. C'est ce PIN qui ouvrira "
                "l'application ensuite — notez-le. Un PIN et sa confirmation "
                "différents doivent être refusés clairement."
            )),
            ("check", (
                "Reprenez l'inscription avec un compte <strong>qui existe "
                "déjà</strong>. Au moment de valider le PIN, l'application "
                "doit afficher « Ce compte existe déjà. » suivi du lien "
                "« Connectez-vous plutôt » — et ce lien doit vraiment "
                "ramener sur la connexion."
            )),
            ("step", "Compléter le profil", (
                "« Presque terminé » demande les autorisations utiles "
                "(notifications, localisation) et complète votre identité. "
                "Le nom et le numéro déjà donnés à l'inscription doivent "
                "arriver <strong>préremplis</strong>, indicatif séparé du "
                "numéro national."
            )),
            ("shot", "10-permissions", "« Presque terminé »"),
            ("expect", (
                "« Continuer vers KORA » mène à l'accueil connecté, avec "
                "votre prénom dans la salutation. « Plus tard » doit "
                "fonctionner aussi : refuser une autorisation ne bloque "
                "jamais l'accès à l'application."
            )),
        ],
    },
    # ------------------------------------------------------------------ 3
    {
        "eyebrow": "Compte",
        "title": "Se connecter",
        "intro": (
            "La connexion s'ouvre <strong>en mode e-mail</strong> — l'inverse "
            "de l'inscription, et c'est délibéré : la plupart des comptes "
            "existants ont été créés par e-mail. Le lien de bascule est juste "
            "au-dessus de « PIN oublié »."
        ),
        "blocks": [
            ("step", "Saisir l'identifiant", (
                "Adresse e-mail par défaut. Le lien « Connexion avec votre "
                "numéro de téléphone ? » passe en mode téléphone, avec "
                "indicatif. L'application se souvient du dernier identifiant "
                "utilisé : vérifiez qu'il est bien proposé au retour."
            )),
            ("shots", [("11-login-email", "Connexion par e-mail"),
                       ("12-login-phone", "Connexion par téléphone")]),
            ("step", "Saisir le PIN", (
                "Quatre chiffres sur le clavier dédié. Testez un PIN faux : "
                "le message doit être explicite et le champ se vider, sans "
                "jamais révéler si c'est l'identifiant ou le PIN qui est en "
                "cause."
            )),
            ("shots", [("13-login-pin", "Clavier PIN"),
                       ("14-new-device", "Nouvel appareil détecté")]),
            ("step", "Vérifier le garde-fou « nouvel appareil »", (
                "Sur un téléphone inconnu du compte, le bon PIN ne suffit "
                "pas : « Une vérification par email est obligatoire avant "
                "d'autoriser cet appareil, même avec le bon PIN. » "
                "L'écran montre l'appareil et l'heure de la tentative, et "
                "offre « Ce n'est pas moi »."
            )),
            ("check", (
                "Touchez « Ce n'est pas moi ». La connexion doit être "
                "abandonnée, avec le conseil de changer de PIN. C'est le "
                "chemin qu'emprunterait une vraie victime : il doit être "
                "sans ambiguïté."
            )),
            ("step", "Essayer « PIN oublié »", (
                "Le parcours de réinitialisation demande le compte, envoie "
                "un code, puis fait choisir un nouveau PIN. Vérifiez qu'il "
                "s'adapte au mode : « Indiquez l'adresse du compte » ou "
                "« Indiquez le numéro du compte »."
            )),
        ],
    },
    # ------------------------------------------------------------------ 4
    {
        "eyebrow": "Compte",
        "title": "Gérer son PIN et ses appareils",
        "intro": (
            "Le PIN est la clé de l'application : c'est lui qui ouvre le "
            "compte à chaque lancement. KORA ne le connaît pas, et personne "
            "chez KORA ne peut le lire — il ne se change que depuis le "
            "téléphone, après avoir prouvé qu'on connaît l'ancien."
        ),
        "blocks": [
            ("step", "Ouvrir « Sécurité et appareils »", (
                "Onglet Profil, puis « Sécurité — PIN, email et appareils ». "
                "L'écran porte deux actions et la liste des appareils "
                "autorisés sur le compte."
            )),
            ("shots", [("66-security", "Sécurité et appareils"),
                       ("67-change-pin", "Saisie d'un nouveau PIN")]),
            ("step", "Changer le PIN", (
                "« Modifier mon PIN » demande d'abord « Votre PIN actuel », "
                "puis « Votre nouveau PIN » — « Choisissez 4 chiffres "
                "différents de l'ancien ». C'est le même clavier que celui "
                "de la création du compte : quatre pastilles, un pavé "
                "numérique, pas de chiffre visible."
            )),
            ("check", (
                "Entrez un <strong>mauvais PIN actuel</strong> : le "
                "changement doit être refusé. Puis essayez de reprendre "
                "<strong>le même PIN</strong> comme nouveau code : l'écran "
                "l'annonce d'avance (« différents de l'ancien ») et doit le "
                "refuser aussi."
            )),
            ("step", "Vérifier les appareils connectés", (
                "Chaque téléphone qui s'est connecté au compte figure dans "
                "la liste, avec sa dernière activité. Celui que vous tenez "
                "porte la pastille « ACTIF »."
            )),
            ("check", (
                "Touchez « Révoquer » sur un appareil que vous n'utilisez "
                "plus, puis rouvrez l'application sur cet appareil-là : il "
                "doit redemander une vérification. C'est le geste qu'on fait "
                "après un vol — il doit marcher du premier coup."
            )),
            ("expect", (
                "Après un changement de PIN, la connexion suivante n'accepte "
                "que le nouveau code. L'ancien ne doit plus rien ouvrir, sur "
                "aucun appareil."
            )),
        ],
    },
    # ------------------------------------------------------------------ 5
    {
        "eyebrow": "Accueil",
        "title": "Lire l'écran d'accueil",
        "intro": (
            "Tout part d'ici. L'accueil porte le formulaire de recherche, "
            "le prochain voyage, les accès rapides et l'entrée aller-retour."
        ),
        "blocks": [
            ("step", "Vérifier la salutation et le prochain voyage", (
                "« Bonjour {prénom}, » en haut. Sans réservation à venir, la "
                "carte annonce « Aucun voyage à venir » — elle ne doit jamais "
                "rester vide sans explication."
            )),
            ("shots", [("15-home", "Haut de l'accueil"),
                       ("16-home-quick", "Accès rapides")]),
            ("step", "Parcourir les accès rapides", (
                "« Mes billets », « Colis », « Wallet », « Ride ». "
                "<strong>Colis et Wallet sont volontairement hors "
                "périmètre</strong> pour cette version : leurs cartes ne "
                "doivent plus rediriger, et les onglets correspondants de la "
                "barre du bas ne doivent plus réagir."
            )),
            ("shot", "17-colis-blank", "L'onglet Colis ne répond pas"),
            ("expect", (
                "Toucher « Colis » ou « Wallet » dans la barre du bas ne "
                "doit <strong>rien</strong> déclencher : l'écran ne change "
                "pas, et l'onglet actif reste celui où vous étiez. Si vous "
                "atteignez malgré tout l'un de ces écrans, vous ne devez y "
                "voir que l'en-tête au-dessus d'une page vide. Toute donnée "
                "affichée là est un défaut : ces modules ne sont pas livrés."
            )),
        ],
    },
    # ------------------------------------------------------------------ 5
    {
        "eyebrow": "Réservation",
        "title": "Chercher un voyage — aller simple",
        "intro": (
            "Le cas le plus courant, et la base de tous les suivants : "
            "quatre champs, une recherche, une liste de départs."
        ),
        "blocks": [
            ("step", "Choisir le départ et l'arrivée", (
                "Touchez « Départ » : la liste des villes réellement "
                "desservies s'ouvre, avec vos villes récentes en tête. "
                "Choisir en arrivée la ville déjà en départ doit "
                "<strong>intervertir le trajet</strong> au lieu de produire "
                "un Cotonou → Cotonou impossible."
            )),
            ("shots", [("18-origin", "Choix de la ville"),
                       ("19-date", "Choix de la date")]),
            ("step", "Choisir la date", (
                "Le calendrier n'accepte pas le passé, et annonce jusqu'où "
                "la réservation est ouverte : « Les départs sont ouverts à "
                "la réservation jusqu'à {n} mois à l'avance »."
            )),
            ("step", "Choisir le nombre de passagers", (
                "De 1 à la limite affichée, avec une entrée groupe au-delà. "
                "« Chaque passager recevra un siège et un QR Code "
                "distincts » : c'est ce qu'on vérifiera au chapitre billets."
            )),
            ("shots", [("20-passengers", "Nombre de passagers"),
                       ("21-departures-loading", "Recherche en cours")]),
            ("step", "Lancer la recherche", (
                "L'écran « Recherche en cours » explique ce qu'il fait — "
                "les disponibilités sont vérifiées segment par segment. "
                "Puis la liste arrive, avec le nombre de départs trouvés."
            )),
            ("shots", [("22-departures", "Départs trouvés"),
                       ("23-departures-filters", "Trier et filtrer")]),
            ("step", "Trier et filtrer", (
                "Tri par heure, prix ou durée ; filtre par créneau (matin, "
                "après-midi, soir). Un filtre trop strict doit dire pourquoi "
                "la liste est vide : « Des départs existent ce jour-là, mais "
                "aucun ne part sur les créneaux choisis », avec « Voir tous "
                "les départs » pour s'en sortir."
            )),
            ("check", (
                "Cherchez une liaison non desservie, ou une date sans "
                "départ. Attendu : « Aucun voyage ce jour-là », et une "
                "invitation à changer de date ou de trajet — pas une page "
                "blanche, pas un message technique."
            )),
            ("shots", [("24-departures-empty", "Aucun départ"),
                       ("25-departure-detail", "Détail d'un départ")]),
            ("step", "Ouvrir le détail d'un départ", (
                "Horaires, escales, durée, prix par voyageur. Les prix "
                "affichés sont définitifs : aucun supplément ne doit "
                "apparaître plus tard dans le parcours."
            )),
        ],
    },
    # ------------------------------------------------------------------ 6
    {
        "eyebrow": "Réservation",
        "title": "L'aller-retour",
        "intro": (
            "L'aller-retour se choisit en une seule fois : deux dates, deux "
            "listes de départs, deux sièges, un seul paiement. C'est le "
            "parcours le plus long de l'application — et celui où les "
            "retours en arrière comptent le plus."
        ),
        "blocks": [
            ("step", "Entrer par la carte « Réserver un aller-retour »", (
                "En bas de l'accueil. Elle ouvre « Réserver un voyage » "
                "directement en mode aller-retour, avec un champ de date "
                "retour en plus."
            )),
            ("shots", [("26-bus-search", "Formulaire aller-retour"),
                       ("27-return-date", "Date de retour")]),
            ("step", "Renseigner les deux dates", (
                "Un retour antérieur à l'aller doit être impossible. "
                "Repoussez l'aller <strong>après</strong> le retour déjà "
                "choisi : le retour doit être effacé plutôt que de laisser "
                "partir une recherche absurde."
            )),
            ("step", "Choisir le départ aller, puis le retour", (
                "Les deux listes sont distinctes et portent leur bandeau de "
                "direction, « Aller » puis « Retour »."
            )),
            ("shots", [("28-return-departures", "Départs retour"),
                       ("29-return-empty", "Aucun retour")]),
            ("check", (
                "Provoquez le cas « Aucun retour » : choisissez une date de "
                "retour sans départ. Puis, depuis cet écran, revenez au "
                "formulaire de recherche. <strong>Les informations déjà "
                "saisies doivent être encore là</strong> — villes, dates, "
                "nombre de voyageurs. Devoir tout retaper serait un défaut."
            )),
            ("check", (
                "Toujours depuis « Aucun retour », touchez la flèche retour "
                "de l'en-tête. Vous devez revenir à un écran utile — jamais "
                "sur un écran noir, jamais bloqué."
            )),
        ],
    },
    # ------------------------------------------------------------------ 7
    {
        "eyebrow": "Réservation",
        "title": "Réserver pour un groupe",
        "intro": (
            "Au-delà d'un certain nombre de voyageurs, la réservation "
            "bascule en mode groupe : <strong>un seul paiement, un billet "
            "par personne</strong>, et une remise que KORA applique "
            "automatiquement une fois le seuil atteint."
        ),
        "blocks": [
            ("step", "Passer en mode groupe", (
                "Depuis l'accueil, ouvrez « Passagers ». Le bouton « Groupe » "
                "indique la fourchette acceptée — lisez-la, elle est "
                "configurée par KORA et peut changer d'un environnement à "
                "l'autre. L'écran rappelle sous le compteur : « 1 à 10 · "
                "groupe dès 5 »."
            )),
            ("step", "Monter jusqu'au maximum", (
                "Touchez « + » jusqu'en haut de la fourchette. Au dernier "
                "voyageur, le « + » doit devenir <strong>inactif</strong> "
                "plutôt que de laisser monter un nombre que le serveur "
                "refusera ensuite."
            )),
            ("shots", [("61-group-passengers", "Groupe au maximum"),
                       ("62-group-booking", "Réservation de groupe")]),
            ("expect", (
                "« Chaque passager recevra un siège et un QR Code "
                "distincts. » C'est la promesse à vérifier jusqu'au bout : "
                "un groupe de dix doit produire dix billets, pas un billet "
                "pour dix."
            )),
            ("step", "Choisir le départ, puis lire le récapitulatif groupe", (
                "L'écran « Réservation de groupe » annonce « Un seul "
                "paiement, un billet par voyageur », le tarif initial et le "
                "total estimé. La remise n'y est qu'annoncée : « Le montant "
                "exact apparaît une fois le départ choisi. »"
            )),
            ("shots", [("63-group-discount", "Remise appliquée"),
                       ("64-group-discount-pending", "Seuil non atteint")]),
            ("step", "Retenir tous les sièges d'un coup", (
                "Le plan du bus demande autant de sièges que de voyageurs, "
                "et le compteur suit — « 5/5 », « 10/10 ». Tant qu'il n'est "
                "pas complet, on ne peut pas continuer : un groupe à qui il "
                "manque une place n'est pas un groupe."
            )),
            ("shot", "65-group-seats", "Les sièges du groupe"),
            ("step", "Vérifier la remise", (
                "Au seuil, l'écran « Votre remise groupe » affiche le "
                "pourcentage appliqué automatiquement et ce que le groupe "
                "économise. Refaites le calcul : la remise doit porter sur "
                "le tarif de tous les voyageurs, pas d'un seul."
            )),
            ("check", (
                "Redescendez <strong>sous le seuil</strong> d'un voyageur. "
                "L'écran doit basculer sur « Bientôt une remise » et dire "
                "exactement combien de voyageurs manquent — « Il vous manque "
                "{n} voyageurs pour en bénéficier ». Un seuil annoncé sans "
                "chiffre est inutilisable pour qui hésite à ajouter "
                "quelqu'un."
            )),
            ("step", "Renseigner tous les voyageurs", (
                "Un formulaire par personne, dans l'ordre. Vérifiez que "
                "l'écran dit où vous en êtes et qu'aucun formulaire ne peut "
                "être sauté : un billet sans nom ne passe pas au contrôle."
            )),
            ("step", "Payer une seule fois", (
                "Le groupe fait l'objet d'<strong>un seul paiement</strong>. "
                "Sur le récapitulatif, la ligne « Remise groupe » doit "
                "apparaître avec son montant, et le total doit être cohérent "
                "avec le nombre de voyageurs."
            )),
            ("shot", "51-group-tickets", "Un QR par voyageur"),
            ("expect", (
                "Après paiement, « Vos billets » liste <strong>autant de QR "
                "que de voyageurs</strong> : « Chaque QR est unique. La "
                "validation d'un billet n'affecte pas les autres "
                "voyageurs. » Comptez-les, et faites valider le premier : "
                "les autres doivent rester valides."
            )),
        ],
    },
    # ------------------------------------------------------------------ 8
    {
        "eyebrow": "Réservation",
        "title": "Choisir les sièges",
        "intro": (
            "Le plan du bus montre ce qui est libre sur <em>votre</em> "
            "tronçon. Dès la sélection, les sièges sont retenus pour vous, "
            "et un décompte démarre."
        ),
        "blocks": [
            ("step", "Sélectionner les sièges", (
                "Autant de sièges que de voyageurs. Le compteur suit : "
                "« 2/2 ». Sur un aller-retour, l'application demande "
                "d'abord « les sièges de l'aller », puis ceux du retour."
            )),
            ("shots", [("30-seats-outbound", "Sièges de l'aller"),
                       ("31-seats-inbound", "Sièges du retour")]),
            ("step", "Observer le décompte", (
                "« Votre sélection est protégée » et le temps restant. "
                "Pendant ce décompte, vos sièges ne sont plus vendables à "
                "personne d'autre. Pour en changer, il faut d'abord "
                "« Annuler mes choix »."
            )),
            ("check", (
                "Laissez le décompte aller jusqu'au bout sans payer. "
                "Attendu : « Votre réservation a expiré », avec « Choisir un "
                "autre siège ». Les sièges doivent être réellement "
                "retournés à la vente — vérifiez-le en relançant la "
                "recherche."
            )),
            ("shot", "32-hold-expired", "Rétention expirée"),
        ],
    },
    # ------------------------------------------------------------------ 8
    {
        "eyebrow": "Réservation",
        "title": "Renseigner les voyageurs",
        "intro": (
            "Un formulaire par voyageur. Ces informations partent sur le "
            "billet : une erreur ici se voit au contrôle."
        ),
        "blocks": [
            ("step", "Remplir le formulaire", (
                "Les champs obligatoires portent une <strong>astérisque</strong> : "
                "nom, prénom, téléphone. Validez à vide pour vérifier que "
                "chaque manque est signalé sous son champ, et non par un "
                "message global."
            )),
            ("shots", [("33-passenger-form", "Trajet intérieur"),
                       ("34-passenger-form-intl", "Trajet transfrontalier")]),
            ("step", "Comparer intérieur et transfrontalier", (
                "Sur un trajet <strong>intérieur au Bénin</strong>, le champ "
                "fièvre jaune ne doit <strong>pas</strong> apparaître : il "
                "n'a aucun sens là. Sur un trajet qui <strong>franchit la "
                "frontière</strong>, il doit être présent."
            )),
            ("expect", (
                "Deux réservations, l'une Cotonou → Parakou, l'autre "
                "Cotonou → Abidjan ou Lomé. Le premier formulaire est plus "
                "court que le second, d'exactement ce champ."
            )),
        ],
    },
    # ------------------------------------------------------------------ 9
    {
        "eyebrow": "Réservation",
        "title": "Vérifier le récapitulatif",
        "intro": (
            "Le dernier écran avant l'argent. Il doit dire la vérité sur ce "
            "qui sera débité, et offrir les deux chemins : payer soi-même, "
            "ou passer par un agent KORA."
        ),
        "blocks": [
            ("step", "Relire le voyage", (
                "Trajet, gare, date, voyageurs, sièges. Sur un aller-retour, "
                "les deux sens apparaissent. Chaque ligne a son « Modifier »."
            )),
            ("shots", [("35-summary", "Récapitulatif"),
                       ("36-summary-promo", "Code promo")]),
            ("step", "Vérifier le détail du prix", (
                "« Tarif par voyageur », « Frais de service » (annoncés "
                "inclus), remise groupe le cas échéant, puis « Total à "
                "payer ». La somme doit être juste — refaites le calcul à la "
                "main."
            )),
            ("step", "Essayer un code promo", (
                "Saisissez un code, appliquez-le. Un code accepté s'affiche "
                "en ligne de remise et peut être retiré. Un code invalide "
                "doit le dire sans bloquer : « Vous pouvez continuer au "
                "tarif normal »."
            )),
            ("note", (
                "<strong>Anomalie connue au moment de la rédaction</strong> : "
                "l'application d'un code promo sur une réservation en cours "
                "peut échouer côté serveur. Si cela vous arrive, notez-le et "
                "poursuivez au tarif normal — le défaut est identifié et "
                "suivi côté back-office."
            )),
            ("step", "Choisir la sortie", (
                "Deux boutons : « En ligne » pour payer vous-même, « Avec un "
                "agent KORA » pour confier le dossier. Les deux chapitres "
                "suivants traitent l'un et l'autre."
            )),
        ],
    },
    # ------------------------------------------------------------------ 10
    {
        "eyebrow": "Paiement",
        "title": "Payer en ligne (Mobile Money)",
        "intro": (
            "Le seul moyen branché aujourd'hui est le Mobile Money. "
            "L'opérateur pousse sa demande de code <strong>sur le téléphone "
            "du payeur</strong> : il n'y a pas de page web, et l'application "
            "attend sans jamais conclure elle-même."
        ),
        "blocks": [
            ("step", "Choisir le moyen de paiement", (
                "« Comment souhaitez-vous payer ? » annonce ce qui vient : "
                "« Le montant est confirmé à l'étape suivante, avant tout "
                "débit. » Les moyens hors périmètre sont marqués « Bientôt "
                "disponible » et ne doivent pas être sélectionnables."
            )),
            ("shots", [("37-payment-method", "Choix du moyen"),
                       ("38-momo-operators", "Choix de l'opérateur")]),
            ("step", "Choisir l'opérateur", (
                "MTN, Moov, Celtiis, Wave, avec leurs logos. La liste vient "
                "du serveur : si elle ne charge pas, l'écran doit le dire "
                "(« Impossible de savoir quels opérateurs sont acceptés ») "
                "plutôt que d'en inventer."
            )),
            ("step", "Saisir le numéro à débiter", (
                "Utilisez le <strong>numéro de test de succès</strong> : "
                "+229 01 66 00 00 01. L'encart « Avant de valider » rappelle "
                "l'essentiel — une demande de confirmation va apparaître, et "
                "<strong>le code secret Mobile Money ne se partage "
                "jamais</strong>. Vérifiez qu'il est bien visible, pas noyé."
            )),
            ("shots", [("39-momo-filled", "Prêt à payer"),
                       ("40-payment-pending", "Vérification en cours")]),
            ("step", "Valider et attendre", (
                "L'écran d'attente explique quoi faire et rappelle que le "
                "siège reste réservé. Il ne propose <strong>aucun bouton</strong> "
                "tant que l'attente est normale — c'est voulu. Passé le délai, "
                "« Actualiser le statut » et « Signaler un problème » "
                "apparaissent."
            )),
            ("expect", (
                "Le verdict arrive seul, sans intervention. Succès : l'écran "
                "de confirmation, puis le billet dans « Mes voyages »."
            )),
            ("shots", [("41-payment-success", "Paiement réussi"),
                       ("42-payment-failed-held", "Refus, sièges gardés")]),
            ("check", (
                "Rejouez le paiement avec le <strong>numéro d'échec</strong> "
                "— +229 01 66 00 00 00. Vous devez arriver sur « Paiement "
                "refusé ». Lisez bien lequel des deux messages s'affiche : "
                "si vos sièges sont encore retenus, « Réessayer le "
                "paiement » doit permettre un nouvel essai qui aboutit avec "
                "le numéro de succès."
            )),
            ("shot", "43-payment-failed-lost", "Refus, sièges relâchés"),
            ("check", (
                "Si le message dit au contraire que « vos sièges ont été "
                "relâchés », l'unique bouton doit être « Refaire une "
                "recherche », et le décompte doit avoir "
                "<strong>disparu</strong>. Un décompte qui continue à "
                "tourner sur des sièges rendus serait un défaut : c'est "
                "exactement ce que ce test cherche."
            )),
            ("note", (
                "<strong>Aujourd'hui, c'est toujours ce second message qui "
                "s'affiche.</strong> Le serveur fait expirer la réservation "
                "à <em>chaque</em> paiement refusé : les sièges repartent à "
                "la vente aussitôt, et l'écran « vos sièges restent "
                "réservés » ne peut donc pas apparaître. Vérifié deux fois, "
                "sur deux téléphones et deux comptes différents. "
                "L'application sait afficher les deux cas ; c'est le "
                "comportement serveur qui doit changer — seule l'échéance "
                "de la réservation devrait la faire expirer, pas un "
                "paiement manqué. En attendant, considérez cette "
                "vérification comme <em>connue et non conforme</em> plutôt "
                "que comme un test à refaire."
            )),
        ],
    },
    # ------------------------------------------------------------------ 11
    {
        "eyebrow": "Paiement",
        "title": "Réserver avec un agent KORA",
        "intro": (
            "Pour qui préfère payer en espèces ou en agence : KORA reprend "
            "le dossier, rappelle si besoin, finalise et encaisse. "
            "Attention — <strong>les sièges ne sont pas bloqués pendant "
            "l'attente</strong>, et l'application le dit."
        ),
        "blocks": [
            ("step", "Demander l'assistance", (
                "Depuis le récapitulatif, « Avec un agent KORA ». L'écran "
                "« Réserver avec un agent » explique le principe et avertit : "
                "« Vos sièges ne sont pas bloqués pendant l'attente : le "
                "montant et les sièges sont indicatifs, l'agence les "
                "confirme. »"
            )),
            ("shots", [("44-assist-request", "Choix du canal"),
                       ("45-assist-whatsapp", "Message WhatsApp prérédigé")]),
            ("step", "Choisir WhatsApp", (
                "« Le message est déjà rédigé, vous n'avez qu'à l'envoyer. » "
                "WhatsApp s'ouvre sur la discussion KORA, message en place. "
                "Vérifiez qu'il contient bien la référence du dossier et le "
                "trajet."
            )),
            ("check", (
                "Si WhatsApp ne s'ouvre pas, l'application doit le dire "
                "— « WhatsApp n'a pas pu s'ouvrir… » — et offrir « Copier le "
                "message » pour l'envoyer autrement. Testez ce repli sur un "
                "téléphone sans WhatsApp installé."
            )),
            ("note", (
                "<strong>Si vous lisez « Votre compte n'est pas autorisé à "
                "confier une réservation à un agent »</strong>, ce n'est pas "
                "un bug d'affichage : le serveur refuse ce compte-là. Le cas "
                "courant a été corrigé côté back-office, mais un compte créé "
                "avant ce correctif, ou déjà porteur d'un rôle d'agence, "
                "reste refusé. Signalez le compte concerné ; le paiement en "
                "ligne, lui, reste ouvert."
            )),
            ("step", "Choisir l'appel", (
                "L'autre canal : « Un agent KORA vous répond au téléphone. » "
                "Vérifiez que le numéro composé est bien celui de KORA, et "
                "que la demande est enregistrée même si l'appel échoue."
            )),
            ("step", "Suivre la demande", (
                "« Votre demande » porte un statut : « En attente », « En "
                "cours », « Finalisée », « Refusée », « Expirée »… Chacun "
                "vient avec sa phrase d'explication et ses actions."
            )),
            ("shots", [("46-assist-status", "Suivi de la demande"),
                       ("48-trips-pending", "Onglet « En attente »")]),
            ("step", "Reprendre la main en ligne", (
                "« Continuer vers le paiement » rebascule le dossier en "
                "paiement en ligne — « Vos sièges vous sont reproposés s'ils "
                "sont libres ». Si entre-temps ils sont partis, "
                "l'application doit le dire et nommer le siège concerné."
            )),
            ("expect", (
                "Une fois encaissée par l'agent, la demande passe en "
                "« Finalisée » et les billets apparaissent dans « Mes "
                "voyages » — sans que vous ayez rien à faire de plus."
            )),
        ],
    },
    # ------------------------------------------------------------------ 12
    {
        "eyebrow": "Billet",
        "title": "Consulter son billet",
        "intro": (
            "Le billet est la raison d'être de tout ce qui précède. Il doit "
            "être trouvable en deux gestes, et lisible au contrôle."
        ),
        "blocks": [
            ("step", "Ouvrir « Mes voyages »", (
                "Quatre onglets : « À venir », « En attente », « Passés », "
                "« Annulés », chacun avec son compte. Un onglet vide "
                "explique pourquoi il l'est."
            )),
            ("shots", [("47-trips-upcoming", "Mes voyages"),
                       ("49-ticket-detail", "Détail du billet")]),
            ("step", "Ouvrir le billet", (
                "Trajet, gare, date, voyageur, siège, et l'état : « Billet "
                "confirmé ». Le rappel « Présentez-vous au moins 30 minutes "
                "avant le départ » doit être visible."
            )),
            ("step", "Afficher le QR Code", (
                "« Afficher le QR Code », annoncé « Disponible hors ligne ». "
                "C'est ce code que le convoyeur scanne. Vérifiez qu'il "
                "s'affiche en grand, net, et que l'écran ne s'éteint pas "
                "trop vite."
            )),
            ("shots", [("50-ticket-qr", "QR Code"),
                       ("51-group-tickets", "Billets de groupe")]),
            ("step", "Vérifier un groupe", (
                "Sur une réservation à plusieurs, <strong>chaque voyageur a "
                "son propre QR</strong> : « Chaque QR est unique. La "
                "validation d'un billet n'affecte pas les autres "
                "voyageurs. » Comptez-les."
            )),
            ("check", (
                "Annulez un billet depuis son détail. L'écran de "
                "confirmation doit annoncer les conséquences — siège rendu, "
                "billet sans valeur au contrôle — et, sur un groupe, dire "
                "combien de billets seront annulés. Après annulation, le "
                "billet porte le tampon « ANNULÉ »."
            )),
        ],
    },
    # ------------------------------------------------------------------ 13
    {
        "eyebrow": "Billet",
        "title": "Télécharger le billet en PDF",
        "intro": (
            "Le PDF est le billet officiel KORA. Il s'enregistre sur le "
            "téléphone, ce qui le rend consultable sans réseau et "
            "imprimable."
        ),
        "blocks": [
            ("step", "Lancer le téléchargement", (
                "« Télécharger le billet (PDF) » depuis le détail du billet. "
                "Un état « Téléchargement du billet… » s'affiche pendant "
                "l'opération."
            )),
            ("shots", [("52-pdf-downloading", "Téléchargement"),
                       ("53-pdf-ready", "Billet enregistré")]),
            ("step", "Vérifier l'enregistrement", (
                "Attendu : « Billet officiel KORA enregistré sur ce "
                "téléphone. », puis « Ouvrir le billet » et « Télécharger à "
                "nouveau ». Le sous-titre indique où il se trouve — « Dans "
                "vos téléchargements »."
            )),
            ("step", "Ouvrir le PDF", (
                "Il doit s'ouvrir dans le lecteur du téléphone, à "
                "l'endroit : QR lisible, informations du voyage exactes, "
                "mise en page non tronquée. Si aucun lecteur n'est "
                "installé, l'application doit le dire plutôt que de ne rien "
                "faire."
            )),
            ("shot", "54-pdf-opened", "Le PDF ouvert"),
            ("check", (
                "Provoquez les erreurs, une par une, et vérifiez que chacune "
                "a son message propre : coupez le réseau avant de "
                "télécharger (« Connexion impossible… »), essayez sur un "
                "billet annulé (« Ce billet ne peut pas être "
                "téléchargé… »), et avec une session expirée (« Votre "
                "session a expiré. Reconnectez-vous… »)."
            )),
        ],
    },
    # ------------------------------------------------------------------ 14
    {
        "eyebrow": "Billet",
        "title": "Partager son billet",
        "intro": (
            "Un billet se partage souvent : à celui qui paie, à celui qui "
            "attend à l'arrivée, ou à soi-même sur un autre appareil."
        ),
        "blocks": [
            ("step", "Ouvrir le partage", (
                "« Partager mon billet », annoncé « WhatsApp et "
                "téléchargement ». La feuille de partage du système s'ouvre "
                "avec les applications disponibles."
            )),
            ("shots", [("55-share", "Feuille de partage"),
                       ("56-share-whatsapp", "Reçu sur WhatsApp")]),
            ("step", "Envoyer par WhatsApp", (
                "Choisissez une discussion et envoyez. Vérifiez sur le "
                "téléphone destinataire que le billet est <strong>exploitable "
                "tel quel</strong> : le QR doit rester scannable après le "
                "passage par WhatsApp, sans recompression destructrice."
            )),
            ("expect", (
                "Le partage ne doit jamais exposer autre chose que le "
                "billet : ni jeton de session, ni lien permettant de "
                "modifier la réservation."
            )),
        ],
    },
    # ------------------------------------------------------------------ 15
    {
        "eyebrow": "Terrain",
        "title": "Consulter son billet hors ligne",
        "intro": (
            "C'est le test le plus important du guide. En gare, le réseau "
            "manque souvent — et c'est précisément là qu'il faut présenter "
            "son billet."
        ),
        "blocks": [
            ("step", "Préparer", (
                "Avant de couper le réseau : ouvrez le billet une fois, et "
                "téléchargez son PDF. L'application enregistre alors ce "
                "qu'il faut sur l'appareil."
            )),
            ("step", "Couper le réseau", (
                "Activez le mode avion — Wi-Fi <em>et</em> données mobiles. "
                "Vérifiez qu'il est bien actif avant de continuer."
            )),
            ("shots", [("57-offline-ticket", "Billet hors ligne"),
                       ("58-offline-qr", "QR sans réseau")]),
            ("step", "Rouvrir le billet", (
                "Depuis « Mes voyages », ouvrez le billet et affichez le QR. "
                "La mention « Billet enregistré sur cet appareil et "
                "accessible hors ligne » doit être visible."
            )),
            ("expect", (
                "Le QR s'affiche <strong>sans réseau</strong>, complet et "
                "net. C'est non négociable : un billet qui exige du réseau "
                "pour s'afficher n'est pas un billet."
            )),
            ("step", "Vérifier le PDF hors ligne", (
                "Demandez à nouveau le billet PDF. L'application doit servir "
                "la copie locale et le dire : « Hors connexion : voici le "
                "billet déjà enregistré sur ce téléphone. »"
            )),
            ("shot", "59-offline-pdf", "PDF servi hors ligne"),
            ("check", (
                "Toujours hors ligne, essayez une recherche de départs. Là, "
                "l'application <strong>doit</strong> annoncer l'absence de "
                "réseau clairement et proposer de réessayer — sans perdre ce "
                "que vous aviez saisi."
            )),
        ],
    },
    # ------------------------------------------------------------------ 16
    {
        "eyebrow": "Robustesse",
        "title": "Session, retours en arrière et cas limites",
        "intro": (
            "Un testeur n'a pas fini quand le parcours heureux passe. Ces "
            "vérifications-là sont celles qui remontent le plus de défauts "
            "en production."
        ),
        "blocks": [
            ("step", "Laisser la session expirer", (
                "Les sessions KORA ont une durée limitée. Laissez "
                "l'application de côté assez longtemps, ou testez sur un "
                "compte dont la session a été révoquée, puis revenez."
            )),
            ("shot", "60-session-expired", "Retour à la connexion"),
            ("expect", (
                "L'application doit vous ramener <strong>d'elle-même sur la "
                "connexion</strong>. Vous ne devez jamais rester coincé sur "
                "« Impossible de charger l'accueil » avec un bouton "
                "« Réessayer » qui ne peut pas réussir."
            )),
            ("check", (
                "Depuis chaque écran du parcours de réservation, touchez la "
                "flèche retour de l'en-tête, puis le bouton retour du "
                "téléphone. Aucun des deux ne doit produire un écran noir, "
                "ni enfermer dans une boucle. Si l'historique est vide, on "
                "revient à l'accueil."
            )),
            ("check", (
                "Faites tourner le téléphone, passez en mode sombre, "
                "agrandissez la taille de police du système. Les écrans "
                "doivent rester lisibles et rien ne doit déborder."
            )),
            ("check", (
                "Mettez l'application en arrière-plan pendant un décompte de "
                "sièges, attendez, revenez. Le temps restant doit être juste "
                "— pas figé à la valeur d'avant."
            )),
        ],
    },
]

# --- Annexes -----------------------------------------------------------

CHECKLIST = [
    ("Premier lancement", "Onboarding affiché une seule fois"),
    ("Inscription", "Par téléphone, nom obligatoire, code WhatsApp reçu"),
    ("Inscription", "Bascule e-mail, puis retour sans perte de saisie"),
    ("Inscription", "Compte déjà existant : lien « Connectez-vous plutôt » actif"),
    ("Inscription", "Numéro sans WhatsApp : message visible au-dessus du bouton"),
    ("Profil", "Nom et numéro préremplis après inscription"),
    ("Connexion", "Mode e-mail par défaut, bascule téléphone fonctionnelle"),
    ("Connexion", "PIN faux refusé sans divulguer la cause"),
    ("Connexion", "Nouvel appareil : vérification exigée, « Ce n'est pas moi » actif"),
    ("PIN", "Changement refusé si l'ancien PIN est faux"),
    ("PIN", "Nouveau PIN identique à l'ancien refusé"),
    ("Appareils", "Révocation effective sur l'appareil visé"),
    ("Accueil", "Colis et Wallet sans redirection, en-tête seul"),
    ("Recherche", "Trajet interverti si départ = arrivée"),
    ("Recherche", "Aucun départ : message explicite et porte de sortie"),
    ("Recherche", "Tri et filtres, y compris filtre trop strict"),
    ("Aller-retour", "Retour antérieur à l'aller impossible"),
    ("Aller-retour", "« Aucun retour » : saisie conservée au retour au formulaire"),
    ("Aller-retour", "Flèche retour depuis « Aucun retour » : pas d'écran noir"),
    ("Groupe", "Fourchette annoncée, « + » inactif au maximum"),
    ("Groupe", "Remise appliquée automatiquement au seuil"),
    ("Groupe", "Sous le seuil : nombre de voyageurs manquants annoncé"),
    ("Groupe", "Un seul paiement, un QR par voyageur"),
    ("Sièges", "Décompte visible, sièges réellement retenus"),
    ("Sièges", "Expiration : sièges rendus à la vente"),
    ("Voyageurs", "Astérisques sur les champs obligatoires"),
    ("Voyageurs", "Fièvre jaune absente en intérieur, présente en transfrontalier"),
    ("Récapitulatif", "Total juste, détail du prix cohérent"),
    ("Récapitulatif", "Code promo appliqué puis retiré"),
    ("Paiement", "Opérateurs affichés avec logos, liste venue du serveur"),
    ("Paiement", "Encart « Avant de valider » bien visible"),
    ("Paiement", "Succès avec le numéro de test de succès"),
    ("Paiement", "Refus : message conforme à l'état réel des sièges"),
    ("Paiement", "Nouvel essai après refus, sièges encore retenus"),
    ("Assistance", "WhatsApp ouvert avec message prérédigé et référence"),
    ("Assistance", "Repli « Copier le message » sans WhatsApp"),
    ("Assistance", "Suivi du statut, reprise en ligne"),
    ("Billet", "QR affiché, un QR par voyageur sur un groupe"),
    ("Billet", "Annulation : conséquences annoncées, tampon « ANNULÉ »"),
    ("PDF", "Téléchargement, ouverture, re-téléchargement"),
    ("PDF", "Chaque erreur a son message propre"),
    ("Partage", "Billet exploitable chez le destinataire"),
    ("Hors ligne", "QR affiché en mode avion"),
    ("Hors ligne", "PDF servi depuis la copie locale"),
    ("Robustesse", "Session expirée : retour automatique à la connexion"),
    ("Robustesse", "Retours en arrière sans écran noir"),
    ("Robustesse", "Mode sombre, rotation, grande police"),
]

REPORT_FIELDS = [
    ("Écran", "Le titre exact affiché en haut de l'écran."),
    ("Ce que j'ai fait", "Les gestes, dans l'ordre, à partir d'un état connu."),
    ("Ce que j'attendais", "La phrase du guide, ou votre attente raisonnable."),
    ("Ce que j'ai vu", "Le texte exact du message, capture à l'appui."),
    ("Compte utilisé", "Identifiant, et s'il a été créé par téléphone ou e-mail."),
    ("Réseau", "Wi-Fi, données mobiles, ou mode avion."),
    ("Référence", "Référence de réservation ou de paiement, si l'écran en donne une."),
    ("Version", "La version de l'application — %s pour cette campagne." % APP_VERSION),
]

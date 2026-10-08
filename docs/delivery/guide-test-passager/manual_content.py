# -*- coding: utf-8 -*-
"""Contenu du manuel d'utilisation de l'application Passager KORA EXPRESS.

Ce document s'adresse à quelqu'un qui **n'a jamais ouvert
l'application**, et qui n'est pas forcément à l'aise avec un téléphone.

Trois règles d'écriture :

1. **Dire ce que c'est avant de dire quoi faire.** Chaque chapitre
   commence par décrire ce que le lecteur a sous les yeux — les zones de
   l'écran, les boutons, ce que chaque chose veut dire — avant de lui
   demander le moindre geste.
2. **Aucun mot supposé connu.** QR Code, Mobile Money, code PIN, hors
   ligne : chaque terme est expliqué à sa première apparition, et repris
   dans le glossaire.
3. **Une phrase, une idée**, au vouvoiement, à la voix active. Les
   libellés de l'application sont cités entre « » tels qu'ils
   s'affichent.
"""

APP_VERSION = "1.0.0"

# Portés sur la page de garde.
AUTHOR = "NASSARA Kévine"
WRITTEN_ON = "7 octobre 2026"

# --- Figures -----------------------------------------------------------
FIGURES = {
    "01-splash": "L'ouverture de l'application",
    "02-onboarding-1": "Écran de présentation 1 sur 3",
    "03-onboarding-2": "Écran de présentation 2 sur 3",
    "04-onboarding-3": "Écran de présentation 3 sur 3",
    "05-welcome": "L'écran d'accueil avant connexion",
    "06-signup-phone": "Création de compte avec un numéro de téléphone",
    "07-signup-email": "Création de compte avec une adresse e-mail",
    "68-signup-delivery-failed": "Message affiché quand le code ne part pas",
    "08-otp": "Les six cases du code de vérification",
    "09-create-pin": "Création de votre code PIN",
    "10-permissions": "Vos informations et les autorisations",
    "11-login-email": "Connexion avec une adresse e-mail",
    "12-login-phone": "Connexion avec un numéro de téléphone",
    "13-login-pin": "Le clavier du code PIN",
    "14-new-device": "Vérification sur un téléphone inconnu",
    "66-security": "Sécurité et téléphones connectés",
    "67-change-pin": "Choix d'un nouveau code PIN",
    "15-home": "L'écran d'accueil, partie haute",
    "16-home-quick": "L'écran d'accueil, partie basse",
    "17-colis-blank": "Les services encore à venir",
    "18-origin": "La liste des villes desservies",
    "19-date": "Le calendrier",
    "20-passengers": "Le nombre de voyageurs",
    "21-departures-loading": "La recherche en cours",
    "22-departures": "La liste des départs",
    "23-departures-filters": "Trier et filtrer les départs",
    "24-departures-empty": "Aucun départ à cette date",
    "25-departure-detail": "Le détail d'un départ",
    "26-bus-search": "Le formulaire aller-retour",
    "27-return-date": "Le calendrier du retour",
    "28-return-departures": "La liste des retours",
    "61-group-passengers": "Le nombre de voyageurs d'un groupe",
    "62-group-booking": "Le récapitulatif d'un groupe",
    "63-group-discount": "La remise de groupe appliquée",
    "64-group-discount-pending": "Sous le seuil, pas encore de remise",
    "65-group-seats": "Les sièges d'un groupe",
    "30-seats-outbound": "Le plan du bus",
    "31-seats-inbound": "Le siège du retour",
    "33-passenger-form": "Les informations du voyageur",
    "34-passenger-form-intl": "Informations pour un trajet international",
    "35-summary": "Le récapitulatif du voyage",
    "36-summary-promo": "Le code promo et le détail du prix",
    "37-payment-method": "Le choix du moyen de paiement",
    "38-momo-operators": "Le choix de l'opérateur Mobile Money",
    "39-momo-filled": "L'écran prêt pour le paiement",
    "40-payment-pending": "Le paiement en cours de vérification",
    "41-payment-success": "Le voyage confirmé",
    "43-payment-failed-lost": "Un paiement refusé",
    "44-assist-request": "Demander l'aide d'un agent KORA",
    "45-assist-whatsapp": "Le message préparé pour WhatsApp",
    "46-assist-status": "Le suivi de votre demande",
    "48-trips-pending": "L'onglet des demandes en attente",
    "47-trips-upcoming": "La liste de vos voyages",
    "49-ticket-detail": "Le détail d'un billet",
    "50-ticket-qr": "Le QR Code d'embarquement",
    "51-group-tickets": "Un billet par voyageur",
    "52-pdf-downloading": "Le téléchargement du billet",
    "53-pdf-ready": "Le billet enregistré sur le téléphone",
    "54-pdf-opened": "Le billet officiel KORA",
    "55-share": "Le partage du billet",
    "57-offline-ticket": "Le billet disponible sans réseau",
    "58-offline-qr": "Le QR Code affiché sans réseau",
    "59-offline-pdf": "Le billet servi depuis la copie du téléphone",
}

# --- Ouverture ---------------------------------------------------------

INTRO = {
    "lead": (
        "KORA EXPRESS est l'application qui vous permet de réserver votre "
        "place dans un bus KORA, de la payer depuis votre téléphone, et de "
        "présenter votre billet à l'embarquement — sans passer par un "
        "guichet et sans billet en papier."
    ),
    "what_title": "Ce que l'application fait pour vous",
    "what": [
        ("Trouver un bus",
         "Vous indiquez d'où vous partez, où vous allez et quel jour. "
         "L'application vous montre les bus disponibles, leur heure de "
         "départ, leur durée et leur prix."),
        ("Choisir votre place",
         "Vous voyez le plan du bus et les sièges encore libres. Vous "
         "choisissez le vôtre, comme au cinéma."),
        ("Payer depuis votre téléphone",
         "Vous payez avec votre compte Mobile Money — MTN, Moov ou "
         "Celtiis. Aucun déplacement, aucune espèce à préparer."),
        ("Recevoir votre billet",
         "Le billet arrive aussitôt dans l'application et sur WhatsApp. "
         "Il contient un QR Code : c'est ce carré noir et blanc que le "
         "convoyeur scanne à l'embarquement."),
        ("Voyager sans réseau",
         "Une fois votre billet enregistré, il reste consultable même "
         "sans internet — ce qui arrive souvent en gare."),
        ("Vous faire aider",
         "Si vous préférez payer en espèces ou parler à quelqu'un, vous "
         "pouvez confier votre réservation à un agent KORA d'un seul "
         "geste."),
    ],
    "who_title": "À qui s'adresse ce manuel",
    "who": [
        "À toute personne qui ouvre KORA EXPRESS pour la première fois. "
        "Aucune connaissance préalable n'est supposée.",
        "Si vous n'avez jamais réservé un voyage sur un téléphone, lisez "
        "les deux premiers chapitres dans l'ordre : ils expliquent le "
        "vocabulaire et la façon de se déplacer dans l'application. Tout "
        "le reste en découle.",
        "Si vous êtes déjà à l'aise, allez directement au chapitre qui "
        "vous intéresse grâce au sommaire.",
    ],
    "needs_title": "Ce qu'il vous faut avant de commencer",
    "needs": [
        "<strong>Un téléphone Android</strong> avec l'application KORA "
        "EXPRESS installée.",
        "<strong>Une connexion internet</strong> — Wi-Fi ou données "
        "mobiles — pour réserver et payer.",
        "<strong>Un numéro de téléphone avec WhatsApp</strong>, ou une "
        "adresse e-mail. C'est là que KORA vous enverra votre code de "
        "vérification au moment de créer votre compte.",
        "<strong>Un compte Mobile Money</strong> (MTN MoMo, Moov Flooz ou "
        "Celtiis Cash) si vous souhaitez payer vous-même. Sinon, un agent "
        "KORA peut encaisser pour vous.",
    ],
    "howto_title": "Comment lire ce manuel",
    "howto": [
        "Chaque chapitre commence par <strong>décrire l'écran</strong> : "
        "ce que vous voyez, zone par zone. Viennent ensuite les gestes à "
        "faire, numérotés dans l'ordre.",
        "Les textes entre guillemets — « Rechercher un voyage » — sont "
        "ceux de l'application, écrits exactement comme ils s'affichent.",
        "Chaque écran est illustré par une <strong>figure numérotée</strong>. "
        "La table des figures, après le sommaire, vous permet de "
        "retrouver l'écran que vous avez sous les yeux.",
        "Un encadré <strong>Bon à savoir</strong> apporte un complément "
        "utile. Un encadré <strong>À retenir</strong> signale ce qui "
        "compte vraiment pour votre argent ou votre sécurité.",
        "Les mots techniques sont expliqués à leur première apparition, "
        "et rassemblés dans le <strong>glossaire</strong>, en fin de "
        "manuel.",
    ],
}

CHAPTERS = [
    # ------------------------------------------------------------------ 1
    {
        "title": "Se repérer dans l'application",
        "intro": (
            "Avant de réserver quoi que ce soit, prenons cinq minutes pour "
            "nommer ce que vous allez voir. Les mêmes éléments reviennent "
            "sur tous les écrans : une fois que vous les reconnaissez, "
            "l'application devient simple."
        ),
        "blocks": [
            ("sub", "Les gestes"),
            ("defs", [
                ("Toucher",
                 "Poser brièvement le doigt sur un élément de l'écran, "
                 "puis le relever. C'est l'équivalent du clic de souris. "
                 "Dans ce manuel, on dira « touchez ». Inutile d'appuyer "
                 "fort : l'écran réagit au contact, pas à la pression."),
                ("Faire défiler",
                 "Poser le doigt sur l'écran et le glisser vers le haut "
                 "pour voir la suite, vers le bas pour revenir. Beaucoup "
                 "d'écrans sont plus longs que le téléphone : s'il vous "
                 "semble manquer quelque chose, faites défiler."),
                ("Revenir en arrière",
                 "La flèche « ‹ » en haut à gauche de l'écran vous ramène "
                 "à l'étape précédente. Le bouton retour de votre "
                 "téléphone fait la même chose. Revenir en arrière ne "
                 "supprime jamais ce que vous avez déjà saisi."),
            ]),
            ("sub", "Les éléments d'un écran"),
            ("defs", [
                ("L'en-tête",
                 "La bande du haut. Elle porte le titre de l'écran — par "
                 "exemple « Choisissez votre siège » — et, le plus "
                 "souvent, la flèche de retour."),
                ("Une carte",
                 "Un rectangle blanc aux coins arrondis qui regroupe des "
                 "informations liées entre elles : un départ de bus, un "
                 "billet, un prix. Les cartes sont souvent « touchables » : "
                 "une flèche « › » sur leur droite l'indique."),
                ("Un bouton",
                 "Un rectangle coloré portant une action : « Rechercher un "
                 "voyage », « Payer ». Le bouton principal est jaune. Un "
                 "bouton grisé n'est pas encore utilisable — il vous "
                 "manque une information à fournir."),
                ("Un champ de saisie",
                 "Un cadre dans lequel vous écrivez. Touchez-le : le "
                 "clavier du téléphone apparaît en bas de l'écran. Un "
                 "champ suivi d'une astérisque (*) est obligatoire."),
                ("La barre du bas",
                 "Les cinq icônes toujours présentes en bas : Accueil, "
                 "Voyages, Colis, Wallet, Profil. Elles vous emmènent dans "
                 "les grandes parties de l'application. Celle où vous vous "
                 "trouvez est surlignée en jaune."),
            ]),
            ("sub", "Les mots que vous allez rencontrer"),
            ("defs", [
                ("Code PIN",
                 "Un code secret de quatre chiffres que vous choisissez "
                 "vous-même. Il ouvre l'application, un peu comme le code "
                 "de votre carte bancaire. Vous êtes la seule personne à "
                 "le connaître."),
                ("Code de vérification",
                 "Un code à six chiffres que KORA vous envoie par WhatsApp "
                 "ou par e-mail, pour s'assurer que le numéro ou l'adresse "
                 "vous appartient bien. Il ne sert qu'une fois et expire "
                 "au bout de quelques minutes."),
                ("QR Code",
                 "Le carré noir et blanc imprimé sur votre billet. Le "
                 "convoyeur le scanne avec son appareil à l'embarquement : "
                 "c'est ce qui prouve que votre place est payée."),
                ("Mobile Money",
                 "Le paiement par téléphone proposé par les opérateurs — "
                 "MTN MoMo, Moov Flooz, Celtiis Cash. Vous payez depuis le "
                 "solde de votre compte mobile, en composant votre code "
                 "secret sur une fenêtre envoyée par l'opérateur."),
                ("Hors ligne",
                 "Sans connexion internet. Certaines parties de "
                 "l'application fonctionnent hors ligne : votre billet, "
                 "notamment."),
            ]),
            ("note", (
                "Vous n'avez rien à apprendre par cœur. Ces mots "
                "reviendront expliqués au moment où vous en aurez besoin, "
                "et le glossaire en fin de manuel les reprend tous."
            )),
        ],
    },
    # ------------------------------------------------------------------ 2
    {
        "title": "Ouvrir l'application pour la première fois",
        "intro": (
            "La toute première ouverture vous présente l'application en "
            "trois écrans. Cette présentation ne s'affiche qu'une fois."
        ),
        "blocks": [
            ("step", "Lancer l'application", (
                "Sur l'écran de votre téléphone, touchez l'icône KORA "
                "EXPRESS. Le logo apparaît sur fond bleu marine pendant "
                "une ou deux secondes, le temps que l'application se "
                "connecte."
            )),
            ("fig", "01-splash"),
            ("look", "Ce que vous voyez", [
                ("Le logo KORA EXPRESS",
                 "Au centre de l'écran, entouré de cercles : "
                 "l'application démarre."),
                ("« Le réseau se connecte… »",
                 "En bas, discret. L'application vérifie votre connexion "
                 "internet."),
            ]),
            ("note", (
                "Si cet écran reste affiché longtemps, c'est que votre "
                "connexion internet est lente ou absente. Vérifiez votre "
                "Wi-Fi ou vos données mobiles, puis rouvrez "
                "l'application."
            )),
            ("step", "Parcourir les trois écrans de présentation", (
                "Ils expliquent en une phrase chacun ce que vous pourrez "
                "faire : trouver votre départ, choisir votre place, "
                "embarquer. Pour passer au suivant, touchez « Suivant » en "
                "bas à droite, ou glissez votre doigt de la droite vers la "
                "gauche. « Passer », en bas à gauche, saute directement à "
                "la fin."
            )),
            ("figs", ["02-onboarding-1", "03-onboarding-2"]),
            ("fig", "04-onboarding-3"),
            ("look", "Ce que vous voyez sur ces écrans", [
                ("Les trois petits points",
                 "Juste au-dessus des boutons. Le point allumé indique où "
                 "vous en êtes : premier, deuxième ou troisième écran."),
                ("« Commencer »",
                 "Sur le dernier écran, il remplace « Suivant » et clôt la "
                 "présentation."),
            ]),
            ("step", "Choisir entre créer un compte et se connecter", (
                "L'écran suivant vous présente l'application et vous "
                "propose deux boutons. Touchez « Créer mon compte » si "
                "vous êtes nouveau. Touchez « J'ai déjà un compte » si "
                "vous en avez déjà créé un, même sur un autre téléphone."
            )),
            ("fig", "05-welcome"),
            ("note", (
                "Un compte KORA n'appartient pas à un téléphone, mais à "
                "vous. Vos voyages et vos billets vous suivent si vous "
                "changez d'appareil."
            )),
        ],
    },
    # ------------------------------------------------------------------ 3
    {
        "title": "Créer votre compte",
        "intro": (
            "Créer un compte prend moins de deux minutes. Il vous faut "
            "votre nom, un numéro de téléphone avec WhatsApp, et de quoi "
            "choisir un code secret à quatre chiffres."
        ),
        "blocks": [
            ("look", "Ce que l'écran vous demande", [
                ("« Nom complet »",
                 "Votre prénom et votre nom, tels qu'ils figurent sur "
                 "votre pièce d'identité. C'est ce nom qui apparaîtra sur "
                 "vos billets, et que le convoyeur lira."),
                ("« Numéro WhatsApp »",
                 "Votre numéro de téléphone. L'indicatif du pays — +229 "
                 "pour le Bénin — est déjà choisi à gauche ; vous ne "
                 "saisissez que les chiffres qui suivent."),
                ("« Recevoir mon code »",
                 "Le bouton jaune qui lance l'envoi du code de "
                 "vérification."),
                ("« Inscription avec votre adresse e-mail ? »",
                 "Le lien bleu sous le bouton, si vous préférez utiliser "
                 "une adresse e-mail plutôt qu'un numéro."),
            ]),
            ("figs", ["06-signup-phone", "07-signup-email"]),
            ("step", "Saisir votre nom", (
                "Touchez le champ « Nom complet ». Le clavier apparaît. "
                "Écrivez votre prénom puis votre nom."
            )),
            ("step", "Saisir votre numéro", (
                "Touchez le champ à droite de l'indicatif, puis composez "
                "votre numéro sans le +229 — par exemple 01 97 00 00 00. "
                "Si vous appelez d'un autre pays, touchez l'indicatif pour "
                "en changer."
            )),
            ("step", "Demander votre code", (
                "Touchez « Recevoir mon code ». KORA envoie aussitôt un "
                "message WhatsApp contenant six chiffres."
            )),
            ("warn", (
                "Le code part <strong>sur WhatsApp</strong>. Donnez donc un "
                "numéro sur lequel WhatsApp est installé et fonctionne. "
                "Si le code ne peut pas être livré, l'application vous "
                "l'affiche en rouge au-dessus du bouton : essayez alors "
                "avec une adresse e-mail."
            )),
            ("fig", "68-signup-delivery-failed"),
            ("step", "Recopier le code reçu", (
                "L'écran affiche six cases vides. Ouvrez WhatsApp, lisez "
                "le code, revenez dans l'application et saisissez-le. Les "
                "cases se remplissent une à une."
            )),
            ("fig", "08-otp"),
            ("look", "Ce que vous voyez sur cet écran", [
                ("« Saisissez le code envoyé à +229 ••••••79 »",
                 "Le rappel du numéro destinataire, en partie masqué."),
                ("« Code valable pendant 04:32 »",
                 "Le temps qu'il vous reste. Passé ce délai, le code ne "
                 "fonctionne plus."),
                ("« Renvoyer »",
                 "À droite : demande un nouveau code si le premier n'est "
                 "pas arrivé."),
                ("« Je n'ai pas reçu le code »",
                 "En bas : l'aide si le problème persiste."),
            ]),
            ("step", "Choisir votre code PIN", (
                "L'écran « Créez votre PIN » affiche quatre pastilles "
                "vides et un clavier de chiffres. Composez quatre "
                "chiffres : les pastilles se remplissent. L'application "
                "vous les redemande ensuite pour éviter une faute de "
                "frappe."
            )),
            ("figs", ["09-create-pin", "10-permissions"]),
            ("warn", (
                "Choisissez un code <strong>facile à retenir pour vous, "
                "difficile à deviner pour les autres</strong>. Évitez "
                "1234, votre année de naissance, ou quatre chiffres "
                "identiques. <strong>KORA ne vous demandera jamais ce "
                "code</strong> : ni par téléphone, ni par message, ni sur "
                "WhatsApp."
            )),
            ("step", "Terminer votre profil", (
                "Le dernier écran, « Presque terminé », reprend votre nom "
                "et votre numéro : vous n'avez rien à retaper. Il vous "
                "propose aussi deux services."
            )),
            ("look", "Les deux interrupteurs", [
                ("« Localisation »",
                 "Permet de suivre votre bus en direct et de trouver les "
                 "points KORA proches de vous."),
                ("« Notifications »",
                 "Permet de recevoir votre billet, les rappels de départ "
                 "et les alertes de trajet."),
            ]),
            ("note", (
                "Ces deux services sont facultatifs. « Plus tard » vous "
                "fait entrer dans l'application sans rien activer, et vous "
                "pourrez revenir sur ce choix depuis les réglages de votre "
                "téléphone. « Continuer vers KORA » valide et vous mène à "
                "l'accueil."
            )),
        ],
    },
    # ------------------------------------------------------------------ 4
    {
        "title": "Vous connecter les fois suivantes",
        "intro": (
            "Une fois votre compte créé, l'ouverture tient en deux gestes : "
            "dire qui vous êtes, puis composer votre code PIN."
        ),
        "blocks": [
            ("look", "Ce que vous voyez", [
                ("Le champ d'identifiant",
                 "En haut : votre adresse e-mail par défaut, ou votre "
                 "numéro de téléphone si vous basculez."),
                ("Les quatre pastilles",
                 "Au milieu : elles se remplissent à mesure que vous "
                 "composez votre code PIN. Les chiffres ne s'affichent "
                 "jamais, pour qu'on ne puisse pas les lire par-dessus "
                 "votre épaule."),
                ("Le clavier de chiffres",
                 "En dessous, avec une touche d'effacement et une touche "
                 "de validation « ✓ »."),
                ("« Connexion avec votre numéro de téléphone ? »",
                 "Le lien qui change de méthode d'identification."),
                ("« PIN oublié ? »",
                 "Tout en bas, si vous ne vous souvenez plus de votre "
                 "code."),
            ]),
            ("figs", ["11-login-email", "12-login-phone"]),
            ("step", "Indiquer votre compte", (
                "Si vous vous êtes inscrit avec votre numéro, touchez "
                "« Connexion avec votre numéro de téléphone ? ». "
                "L'application se souvient de votre dernier identifiant et "
                "vous le propose."
            )),
            ("step", "Composer votre code PIN", (
                "Touchez les chiffres de votre code, puis la touche « ✓ ». "
                "L'application s'ouvre sur l'accueil."
            )),
            ("fig", "13-login-pin"),
            ("step", "Si c'est un nouveau téléphone", (
                "Quand vous vous connectez depuis un appareil que votre "
                "compte ne connaît pas encore, le bon code PIN ne suffit "
                "pas : KORA vous envoie en plus un code de vérification. "
                "C'est une protection — si quelqu'un devinait votre code "
                "PIN, il ne pourrait toujours pas entrer."
            )),
            ("fig", "14-new-device"),
            ("look", "Ce que l'écran vous montre", [
                ("« Appareil »",
                 "Le modèle du téléphone qui tente de se connecter."),
                ("« Tentative »",
                 "L'heure de la tentative."),
                ("« Recevoir le code OTP »",
                 "Le bouton qui envoie le code de vérification."),
                ("« Ce n'est pas moi »",
                 "À utiliser si vous n'êtes pas à l'origine de cette "
                 "connexion."),
            ]),
            ("warn", (
                "Si ce n'est pas vous, touchez « Ce n'est pas moi ». La "
                "connexion est abandonnée. Changez ensuite votre code PIN, "
                "comme expliqué au chapitre suivant."
            )),
            ("step", "Si vous avez oublié votre code PIN", (
                "Touchez « PIN oublié ? ». KORA vous demande votre compte, "
                "vous envoie un code de vérification, puis vous laisse "
                "choisir un nouveau code PIN. Vos voyages et vos billets "
                "ne sont pas affectés."
            )),
        ],
    },
    # ------------------------------------------------------------------ 5
    {
        "title": "Protéger votre compte",
        "intro": (
            "Votre compte donne accès à vos billets. Deux réglages "
            "suffisent à le garder sûr : votre code PIN, et la liste des "
            "téléphones autorisés à s'y connecter."
        ),
        "blocks": [
            ("step", "Ouvrir les réglages de sécurité", (
                "Touchez « Profil », la dernière icône de la barre du bas. "
                "Puis, dans « Mon compte », touchez « Sécurité — PIN, "
                "email et appareils »."
            )),
            ("figs", ["66-security", "67-change-pin"]),
            ("look", "Ce que vous voyez", [
                ("« Modifier mon PIN »",
                 "Pour changer votre code secret."),
                ("« Autoriser un nouvel appareil »",
                 "Pour préparer l'accès depuis un autre téléphone."),
                ("« Appareils connectés »",
                 "La liste de tous les téléphones qui se sont connectés à "
                 "votre compte, avec la date de leur dernière activité. "
                 "Celui que vous tenez porte la pastille « ACTIF »."),
                ("« Révoquer »",
                 "En face de chaque appareil : lui retire l'accès."),
            ]),
            ("step", "Changer votre code PIN", (
                "L'application vous demande d'abord votre code actuel, "
                "puis le nouveau — qui doit être différent de l'ancien. "
                "Dès qu'il est enregistré, l'ancien code ne fonctionne "
                "plus, sur aucun téléphone."
            )),
            ("note", (
                "Si vous changez de téléphone ou si vous en perdez un, "
                "touchez « Révoquer » en face de l'appareil concerné. Il "
                "devra se vérifier à nouveau pour accéder à votre compte. "
                "C'est le premier geste à faire en cas de vol."
            )),
        ],
    },
    # ------------------------------------------------------------------ 6
    {
        "title": "L'écran d'accueil",
        "intro": (
            "L'accueil est votre point de départ. Tout commence ici. "
            "Prenons le temps de le décrire de haut en bas."
        ),
        "blocks": [
            ("figs", ["15-home", "16-home-quick"]),
            ("look", "La partie haute", [
                ("Le logo et la cloche",
                 "Tout en haut. La cloche affiche vos notifications ; une "
                 "pastille jaune signale les nouvelles."),
                ("« Bonjour… »",
                 "Votre prénom, suivi d'une phrase d'accueil. C'est le "
                 "signe que vous êtes bien connecté."),
                ("« Où souhaitez-vous voyager ? »",
                 "La carte bleue qui contient le formulaire de recherche. "
                 "C'est le cœur de l'application."),
                ("« DÉPART » et « ARRIVÉE »",
                 "Les deux villes de votre trajet. Le bouton rond jaune, à "
                 "droite, les intervertit d'un geste."),
                ("« DATE » et « PASSAGERS »",
                 "Le jour du voyage et le nombre de personnes."),
                ("« Rechercher un voyage »",
                 "Le grand bouton jaune qui lance la recherche. Il reste "
                 "pâle tant qu'il manque une information."),
            ]),
            ("look", "La partie basse", [
                ("« Accès rapides »",
                 "Quatre raccourcis : Ride, Colis, Wallet et Mes billets."),
                ("« Prochain voyage »",
                 "Votre réservation à venir, avec votre siège. Tant que "
                 "vous n'avez rien réservé, cette carte vous invite à le "
                 "faire."),
                ("« Réserver un aller-retour »",
                 "L'entrée pour réserver l'aller et le retour en une "
                 "seule fois."),
                ("La barre du bas",
                 "Accueil, Voyages, Colis, Wallet, Profil. Elle reste "
                 "visible partout dans l'application."),
            ]),
            ("fig", "17-colis-blank"),
            ("note", (
                "Les services « Colis » et « Wallet » apparaissent déjà "
                "dans l'application, mais ils ne sont pas encore ouverts. "
                "Ils arriveront dans une prochaine version."
            )),
        ],
    },
    # ------------------------------------------------------------------ 7
    {
        "title": "Rechercher un voyage",
        "intro": (
            "Quatre informations suffisent : d'où vous partez, où vous "
            "allez, quel jour, et à combien de personnes."
        ),
        "blocks": [
            ("step", "Choisir votre ville de départ", (
                "Touchez « DÉPART ». Un écran s'ouvre avec la liste des "
                "villes desservies par KORA et un champ de recherche en "
                "haut. Écrivez les premières lettres de votre ville, puis "
                "touchez-la dans la liste."
            )),
            ("fig", "18-origin"),
            ("look", "Ce que vous voyez dans la liste", [
                ("Le nom de la ville",
                 "En gras, par exemple « Bohicon »."),
                ("« Arrêt KORA » ou « Gare KORA »",
                 "En dessous, en petit : le type de point d'arrêt."),
            ]),
            ("warn", (
                "Certaines villes ont <strong>plusieurs points "
                "d'arrêt</strong>. Elles apparaissent alors sur plusieurs "
                "lignes, chacune portant le nom de sa gare — par exemple "
                "« Cotonou » et « Cotonou · Arrêt Cotonou ». Choisissez "
                "celle d'où vous partez réellement : les bus n'y passent "
                "pas aux mêmes heures."
            )),
            ("step", "Choisir votre ville d'arrivée", (
                "Touchez « ARRIVÉE » et procédez de la même façon."
            )),
            ("step", "Choisir la date", (
                "Touchez « DATE ». Un calendrier s'ouvre. Les jours passés "
                "sont grisés : ils ne sont pas sélectionnables. Touchez "
                "votre jour, puis le bouton de confirmation en bas."
            )),
            ("fig", "19-date"),
            ("note", (
                "Les départs sont ouverts à la réservation jusqu'à six "
                "mois à l'avance. Pour changer de mois, utilisez les "
                "flèches de part et d'autre du nom du mois."
            )),
            ("step", "Indiquer le nombre de voyageurs", (
                "Touchez « PASSAGERS ». Les boutons « − » et « + » font "
                "varier le nombre. Chaque voyageur aura son propre siège "
                "et son propre QR Code."
            )),
            ("fig", "20-passengers"),
            ("step", "Lancer la recherche", (
                "Touchez « Rechercher un voyage ». L'application vérifie "
                "les places disponibles et les tarifs, puis affiche les "
                "bus du jour choisi."
            )),
            ("figs", ["21-departures-loading", "22-departures"]),
            ("look", "Ce qu'une carte de départ vous dit", [
                ("Le nom du bus",
                 "« KORA COACH 55 », « KORA CONFORT » : la catégorie de "
                 "véhicule."),
                ("Les places restantes",
                 "En haut à droite."),
                ("Les heures",
                 "À gauche l'heure de départ, à droite l'heure d'arrivée, "
                 "et au centre la durée estimée du trajet."),
                ("Les équipements",
                 "« Wi-Fi · Climatisation · Sièges inclinables » : ce que "
                 "vous trouverez à bord."),
                ("Le prix",
                 "En bas à droite, sous la mention « Prix final garanti »."),
            ]),
            ("warn", (
                "Le prix affiché est <strong>définitif</strong>. Aucun "
                "supplément ne s'ajoutera plus loin dans la réservation."
            )),
            ("step", "Trier et filtrer", (
                "L'icône en haut à droite de la liste ouvre « Trier et "
                "filtrer ». Vous pouvez classer les départs par heure, "
                "prix ou durée, et ne garder que ceux du matin, de "
                "l'après-midi ou du soir."
            )),
            ("figs", ["23-departures-filters", "24-departures-empty"]),
            ("note", (
                "Si aucun bus ne circule ce jour-là, l'application vous "
                "l'indique et vous invite à essayer une autre date ou un "
                "autre trajet. Touchez « Modifier la recherche » : vos "
                "informations sont conservées, vous n'avez qu'à changer ce "
                "qu'il faut."
            )),
            ("step", "Voir le détail d'un départ", (
                "Touchez une carte. L'écran « Votre départ » affiche les "
                "étapes du trajet, les équipements à bord et le prix. Le "
                "bouton « Choisir mon siège » poursuit la réservation."
            )),
            ("fig", "25-departure-detail"),
        ],
    },
    # ------------------------------------------------------------------ 8
    {
        "title": "Réserver un aller et un retour",
        "intro": (
            "Vous pouvez réserver vos deux trajets en une seule fois, et "
            "ne payer qu'une seule fois."
        ),
        "blocks": [
            ("step", "Ouvrir la réservation aller-retour", (
                "Faites défiler l'accueil jusqu'en bas, puis touchez "
                "« Réserver un aller-retour »."
            )),
            ("fig", "26-bus-search"),
            ("look", "Ce qui change par rapport à une recherche simple", [
                ("« Aller simple » / « Aller-retour »",
                 "Deux onglets en haut : le second est déjà sélectionné."),
                ("« Date du retour »",
                 "Un champ supplémentaire sous la date du voyage."),
                ("« Rechercher l'aller et le retour »",
                 "Le bouton, renommé pour l'occasion."),
            ]),
            ("step", "Choisir vos deux dates", (
                "La date de retour ne peut pas être antérieure à celle de "
                "l'aller. Si vous repoussez l'aller au-delà du retour, "
                "l'application efface le retour pour que vous le "
                "choisissiez à nouveau."
            )),
            ("fig", "27-return-date"),
            ("step", "Choisir vos deux bus", (
                "Vous choisissez d'abord l'aller. L'écran des retours "
                "affiche ensuite un rappel jaune de l'aller retenu, pour "
                "que vous gardiez vos horaires en tête."
            )),
            ("fig", "28-return-departures"),
            ("note", (
                "S'il n'existe aucun retour à la date demandée, revenez au "
                "formulaire : vos villes, vos dates et votre nombre de "
                "voyageurs y sont conservés."
            )),
        ],
    },
    # ------------------------------------------------------------------ 9
    {
        "title": "Voyager à plusieurs",
        "intro": (
            "À partir de cinq voyageurs, la réservation passe en mode "
            "groupe : un seul paiement, un billet par personne, et une "
            "remise à partir d'un certain nombre."
        ),
        "blocks": [
            ("step", "Passer en réservation de groupe", (
                "Sur l'accueil, touchez « PASSAGERS », puis le bouton "
                "bleu « Groupe ». Il indique la fourchette acceptée, par "
                "exemple « Groupe 5–10 »."
            )),
            ("fig", "61-group-passengers"),
            ("look", "Ce que vous voyez", [
                ("Le compteur",
                 "Les boutons « − » et « + » de part et d'autre du "
                 "nombre. Au maximum autorisé, le « + » devient pâle et ne "
                 "répond plus."),
                ("« 1 à 10 · groupe dès 5 »",
                 "Sous le mot « Passagers » : le minimum et le maximum."),
                ("« Chaque passager recevra un siège et un QR Code "
                 "distincts »",
                 "Le rappel de ce que vous obtiendrez."),
            ]),
            ("step", "Vérifier le total", (
                "Après avoir choisi votre bus, l'écran « Réservation de "
                "groupe » récapitule : le tarif initial, la remise "
                "éventuelle, et le total estimé."
            )),
            ("figs", ["62-group-booking", "63-group-discount"]),
            ("step", "Profiter de la remise", (
                "Dès que le nombre fixé par KORA est atteint, la remise "
                "s'applique toute seule. L'écran « Votre remise groupe » "
                "vous annonce le pourcentage et la somme économisée."
            )),
            ("fig", "64-group-discount-pending"),
            ("note", (
                "En dessous du seuil, l'application vous dit combien de "
                "voyageurs il vous manque. Au moment où ce manuel est "
                "écrit, la remise est de 10 % à partir de dix voyageurs : "
                "autrement dit, le dixième voyage est offert. Ce seuil est "
                "fixé par KORA et peut évoluer — l'écran affiche toujours "
                "la valeur en vigueur."
            )),
            ("fig", "65-group-seats"),
            ("step", "Choisir les sièges et renseigner chacun", (
                "Vous sélectionnez autant de sièges que de voyageurs, puis "
                "vous renseignez chaque personne à son tour. Le paiement, "
                "lui, reste unique."
            )),
        ],
    },
    # ------------------------------------------------------------------ 10
    {
        "title": "Choisir votre siège",
        "intro": (
            "Le plan du bus vous montre les places encore libres. Vous "
            "choisissez la vôtre, et elle vous est réservée le temps de "
            "finaliser."
        ),
        "blocks": [
            ("fig", "30-seats-outbound"),
            ("look", "Comment lire le plan", [
                ("« AVANT DU BUS »",
                 "En haut du plan : l'orientation. Le conducteur est de ce "
                 "côté."),
                ("La légende des couleurs",
                 "Juste sous le titre : blanc = libre, jaune = votre "
                 "choix, contour jaune = réservé par quelqu'un d'autre, "
                 "bleu = déjà payé."),
                ("Les sièges barrés",
                 "Grisés et rayés : non disponibles sur votre portion de "
                 "trajet."),
                ("La barre du bas",
                 "« SIÈGE SÉLECTIONNÉ » avec votre place et le compteur "
                 "(« 1/1 »), et le total à payer."),
            ]),
            ("step", "Sélectionner votre place", (
                "Touchez un siège blanc : il devient jaune et le compteur "
                "augmente. Pour changer d'avis, touchez-le à nouveau pour "
                "le libérer, puis choisissez-en un autre."
            )),
            ("step", "Sur un aller-retour", (
                "L'application demande d'abord le siège de l'aller, puis "
                "celui du retour. Un bandeau bleu — « SIÈGE ALLER », "
                "« SIÈGE RETOUR » — rappelle de quel trajet il s'agit."
            )),
            ("fig", "31-seats-inbound"),
            ("warn", (
                "Dès que vous avez choisi, un <strong>décompte</strong> "
                "démarre : votre siège vous est réservé, personne d'autre "
                "ne peut le prendre. Si le temps s'écoule avant que vous "
                "ayez payé, le siège repart à la vente et il faut "
                "recommencer la recherche. Comptez une dizaine de minutes."
            )),
        ],
    },
    # ------------------------------------------------------------------ 11
    {
        "title": "Renseigner les voyageurs",
        "intro": (
            "Ces informations figureront sur le billet. Elles permettent "
            "au convoyeur de vérifier que la personne qui monte est bien "
            "celle qui a réservé."
        ),
        "blocks": [
            ("fig", "33-passenger-form"),
            ("look", "Les champs demandés", [
                ("« Prénom * » et « Nom * »",
                 "L'astérisque signifie que le champ est obligatoire. "
                 "Écrivez-les comme sur votre pièce d'identité."),
                ("« Téléphone * »",
                 "Pour vous joindre en cas de changement d'horaire."),
                ("« E-mail (optionnel) »",
                 "Vous pouvez le laisser vide."),
                ("« Siège 3C »",
                 "La pastille jaune en haut à droite rappelle la place "
                 "attribuée à ce voyageur."),
                ("« Je confirme l'exactitude des informations saisies »",
                 "La case à cocher avant de continuer."),
            ]),
            ("note", (
                "Si vous réservez pour vous-même, vos informations sont "
                "déjà remplies. Vérifiez-les tout de même."
            )),
            ("step", "Pour un trajet qui franchit une frontière", (
                "L'écran s'appelle alors « Identités internationales » et "
                "demande en plus votre pièce d'identité, votre "
                "nationalité, votre date de naissance et votre vaccination "
                "contre la fièvre jaune. Ces informations servent au "
                "document que KORA présente à la frontière."
            )),
            ("fig", "34-passenger-form-intl"),
            ("note", (
                "Sur un trajet qui reste au Bénin, ces champs "
                "n'apparaissent pas : ils ne servent à rien et ne vous "
                "sont donc pas demandés."
            )),
            ("warn", (
                "Vérifiez l'orthographe des noms avant de continuer. Un "
                "nom qui ne correspond pas à la pièce d'identité peut "
                "poser problème au moment du contrôle."
            )),
        ],
    },
    # ------------------------------------------------------------------ 12
    {
        "title": "Vérifier votre voyage avant de payer",
        "intro": (
            "Le récapitulatif est le dernier écran avant le paiement. "
            "Relisez-le : tout y est encore modifiable."
        ),
        "blocks": [
            ("fig", "35-summary"),
            ("look", "Ce que récapitule l'écran", [
                ("La carte bleue",
                 "Votre trajet, la date, l'heure, la gare de départ et "
                 "votre siège."),
                ("« Passagers »",
                 "Le nom et le téléphone de chaque voyageur, avec sa "
                 "place. « Modifier » permet de corriger."),
                ("« Code promo »",
                 "Le champ où saisir un code de réduction, si vous en "
                 "avez un."),
                ("« Détail du prix »",
                 "Le tarif par voyageur, les frais de service — toujours "
                 "inclus — et le total à payer."),
                ("« Comment souhaitez-vous payer ? »",
                 "Les deux boutons de sortie, en bas."),
            ]),
            ("step", "Appliquer un code promo", (
                "Saisissez le code dans le champ, puis touchez "
                "« Appliquer le code ». La réduction apparaît aussitôt "
                "dans le détail du prix. Vous pouvez la retirer à tout "
                "moment."
            )),
            ("fig", "36-summary-promo"),
            ("step", "Choisir comment payer", (
                "Deux possibilités. « En ligne » : vous payez vous-même "
                "avec votre compte Mobile Money, c'est le chapitre "
                "suivant. « Avec un agent KORA » : vous confiez votre "
                "réservation à une agence qui encaisse pour vous, c'est le "
                "chapitre d'après."
            )),
        ],
    },
    # ------------------------------------------------------------------ 13
    {
        "title": "Payer avec Mobile Money",
        "intro": (
            "Le Mobile Money, c'est le paiement par téléphone proposé par "
            "les opérateurs. Vous ne saisissez jamais votre code secret "
            "dans l'application KORA : c'est votre opérateur qui vous "
            "l'envoie dans sa propre fenêtre."
        ),
        "blocks": [
            ("fig", "37-payment-method"),
            ("look", "L'écran du choix", [
                ("La carte bleue",
                 "Le montant à payer, et la mention « Paiement sécurisé · "
                 "KORA EXPRESS »."),
                ("« Mobile Money »",
                 "Les opérateurs acceptés, avec leurs logos."),
                ("« KORA Wallet »",
                 "Marqué « Indisponible » : ce moyen n'est pas encore "
                 "ouvert."),
                ("« Vos sièges restent réservés pendant 08:01 »",
                 "Le temps qu'il vous reste pour payer."),
            ]),
            ("step", "Choisir votre opérateur", (
                "Touchez « Mobile Money », puis l'opérateur de votre "
                "compte : MTN MoMo, Moov Flooz ou Celtiis Cash. Un rond "
                "jaune coché confirme votre choix."
            )),
            ("fig", "38-momo-operators"),
            ("step", "Vérifier le numéro à débiter", (
                "L'application propose le numéro de votre compte KORA. "
                "Touchez le champ pour le changer si vous payez depuis un "
                "autre numéro Mobile Money."
            )),
            ("fig", "39-momo-filled"),
            ("look", "L'encadré « AVANT DE VALIDER »", [
                ("Ce qu'il annonce",
                 "« Une demande de confirmation apparaîtra sur votre "
                 "téléphone. Ne partagez jamais votre code secret Mobile "
                 "Money. »"),
                ("Pourquoi il est là",
                 "Parce que la suite se passe hors de l'application, et "
                 "qu'il ne faut pas en être surpris."),
            ]),
            ("warn", (
                "<strong>Ne communiquez votre code secret Mobile Money à "
                "personne.</strong> Ni à un agent KORA, ni à quelqu'un qui "
                "vous appelle, ni à un proche. Vous ne le composez que "
                "dans la fenêtre que votre opérateur affiche sur votre "
                "téléphone."
            )),
            ("step", "Payer", (
                "Touchez « Payer », suivi du montant. Quelques secondes "
                "plus tard, votre opérateur affiche une demande de "
                "confirmation sur votre téléphone : composez-y votre code "
                "secret."
            )),
            ("fig", "40-payment-pending"),
            ("look", "L'écran d'attente", [
                ("« Paiement en cours de vérification… »",
                 "L'application attend la réponse de l'opérateur."),
                ("Le texte en dessous",
                 "Il vous rappelle quoi faire, et que votre siège reste "
                 "réservé pendant ce temps."),
                ("« Vérification automatique toutes les 10 secondes »",
                 "Vous n'avez rien à toucher : l'application vérifie "
                 "d'elle-même."),
            ]),
            ("step", "Si le paiement aboutit", (
                "L'écran affiche « Votre voyage est confirmé ! ». Votre "
                "billet est ajouté à votre compte et vous est envoyé sur "
                "WhatsApp. « Afficher mon billet » vous le montre "
                "immédiatement."
            )),
            ("figs", ["41-payment-success", "43-payment-failed-lost"]),
            ("step", "Si le paiement est refusé", (
                "L'écran vous l'annonce et vous indique la suite. Si vos "
                "sièges ont été relâchés, touchez « Refaire une recherche » : "
                "le même bus est souvent encore disponible, et il ne vous "
                "faudra qu'une minute."
            )),
            ("note", (
                "Un paiement refusé ne débite rien. Si une somme a malgré "
                "tout quitté votre compte Mobile Money, contactez le "
                "support KORA avec la « Référence paiement » affichée à "
                "l'écran."
            )),
        ],
    },
    # ------------------------------------------------------------------ 14
    {
        "title": "Se faire aider par un agent KORA",
        "intro": (
            "Vous préférez payer en espèces, ou parler à quelqu'un ? KORA "
            "reprend votre réservation, vous rappelle si besoin, finalise "
            "et encaisse pour vous."
        ),
        "blocks": [
            ("step", "Demander de l'aide", (
                "Sur le récapitulatif, touchez « Avec un agent KORA », le "
                "bouton blanc portant l'icône WhatsApp."
            )),
            ("fig", "44-assist-request"),
            ("look", "Ce que l'écran vous propose", [
                ("Le texte d'introduction",
                 "« Un agent KORA retrouve votre réservation, vous "
                 "recontacte si besoin, puis finalise et encaisse. »"),
                ("L'encadré d'avertissement",
                 "Vos sièges ne sont pas bloqués pendant l'attente."),
                ("« WhatsApp »",
                 "Le message est déjà rédigé, vous n'avez qu'à l'envoyer."),
                ("« Appel »",
                 "Un agent KORA vous répond au téléphone."),
            ]),
            ("warn", (
                "Pendant l'attente, <strong>vos sièges ne sont pas "
                "bloqués</strong>. Le montant et les places affichés sont "
                "indicatifs : c'est l'agence qui les confirme."
            )),
            ("step", "Envoyer votre demande par WhatsApp", (
                "Touchez « Envoyer via WhatsApp ». WhatsApp s'ouvre sur la "
                "discussion avec KORA, le message déjà écrit : votre "
                "référence, votre trajet, vos sièges souhaités. Il ne vous "
                "reste qu'à l'envoyer."
            )),
            ("fig", "45-assist-whatsapp"),
            ("note", (
                "Si WhatsApp ne s'ouvre pas, l'application vous le dit et "
                "propose « Copier le message » : vous pouvez alors le "
                "coller dans un SMS ou un autre moyen de contact."
            )),
            ("step", "Suivre votre demande", (
                "L'écran « Votre demande » affiche son état et votre "
                "référence. Vous le retrouvez aussi dans « Voyages », "
                "onglet « En attente »."
            )),
            ("figs", ["46-assist-status", "48-trips-pending"]),
            ("look", "Les états possibles", [
                ("« En attente »",
                 "Votre demande est dans la file du réseau KORA."),
                ("« En cours »",
                 "Un agent prépare votre réservation."),
                ("« Finalisée »",
                 "Le paiement a été encaissé, vos billets arrivent."),
                ("« Refusée » ou « Expirée »",
                 "KORA n'a pas pu donner suite : refaites une "
                 "réservation."),
            ]),
            ("step", "Reprendre la main", (
                "À tout moment, « Continuer vers le paiement » vous rend "
                "la réservation pour la payer vous-même. Vos sièges vous "
                "sont reproposés s'ils sont encore libres."
            )),
        ],
    },
    # ------------------------------------------------------------------ 15
    {
        "title": "Retrouver et présenter vos billets",
        "intro": (
            "Tous vos voyages sont rangés dans l'onglet « Voyages », les "
            "passés comme ceux à venir."
        ),
        "blocks": [
            ("fig", "47-trips-upcoming"),
            ("look", "Les quatre onglets", [
                ("« À venir »",
                 "Vos prochains voyages."),
                ("« En attente »",
                 "Les réservations confiées à un agent KORA."),
                ("« Passés »",
                 "Vos trajets terminés, billet compris."),
                ("« Annulés »",
                 "Les billets annulés et leurs remboursements."),
            ]),
            ("step", "Ouvrir un billet", (
                "Touchez « Voir le billet → » sur la carte du voyage. "
                "L'écran rappelle votre trajet, votre gare, la date, le "
                "voyageur et le siège."
            )),
            ("fig", "49-ticket-detail"),
            ("step", "Afficher le QR Code", (
                "Touchez « Afficher le QR Code ». La liste des voyageurs "
                "apparaît ; touchez une ligne pour afficher son code en "
                "grand. C'est ce carré que le convoyeur scanne."
            )),
            ("fig", "50-ticket-qr"),
            ("warn", (
                "Présentez-vous à la gare <strong>au moins 30 minutes "
                "avant le départ</strong>, l'écran de votre téléphone "
                "allumé et la luminosité suffisante pour que le code soit "
                "lisible."
            )),
            ("fig", "51-group-tickets"),
            ("note", (
                "Sur une réservation de groupe, chaque voyageur a son "
                "propre QR Code. La validation de l'un n'affecte pas les "
                "autres : chacun peut embarquer de son côté."
            )),
            ("step", "Annuler un billet", (
                "Depuis le détail du billet, touchez « Annuler le billet ». "
                "L'application vous rappelle les conséquences avant de "
                "confirmer : le siège retourne à la vente, le billet ne "
                "vaut plus rien au contrôle, et le remboursement suit les "
                "conditions de KORA."
            )),
        ],
    },
    # ------------------------------------------------------------------ 16
    {
        "title": "Télécharger, imprimer et partager",
        "intro": (
            "Le billet officiel KORA est un document PDF — un fichier que "
            "l'on peut enregistrer, imprimer ou envoyer. L'enregistrer sur "
            "votre téléphone vous permet aussi de le consulter sans "
            "réseau."
        ),
        "blocks": [
            ("step", "Enregistrer le billet", (
                "Depuis le détail du billet, touchez « Télécharger le "
                "billet (PDF) ». Un message vous indique que le "
                "téléchargement est en cours, puis qu'il est terminé."
            )),
            ("figs", ["52-pdf-downloading", "53-pdf-ready"]),
            ("look", "Les options qui apparaissent ensuite", [
                ("« Ouvrir le billet »",
                 "Affiche le billet dans le lecteur de documents de votre "
                 "téléphone."),
                ("« Télécharger à nouveau »",
                 "Récupère une version fraîche depuis KORA."),
                ("« Enregistrer une copie »",
                 "Range le fichier dans vos téléchargements, d'où vous "
                 "pourrez l'imprimer ou l'envoyer."),
            ]),
            ("fig", "54-pdf-opened"),
            ("look", "Ce que contient le billet officiel", [
                ("Votre trajet et vos horaires",
                 "Avec la gare de départ et celle d'arrivée."),
                ("Votre nom et votre siège",
                 "Tels que vous les avez saisis."),
                ("Le QR Code",
                 "Le même que dans l'application."),
                ("La référence du billet",
                 "À citer si vous contactez le support."),
            ]),
            ("step", "Partager votre billet", (
                "Touchez « Partager mon billet ». Votre téléphone affiche "
                "la liste des applications disponibles — WhatsApp, e-mail, "
                "et les autres. Choisissez-en une, puis le destinataire."
            )),
            ("fig", "55-share"),
            ("warn", (
                "Partagez votre billet avec prudence : <strong>toute "
                "personne qui détient le QR Code peut le présenter à "
                "l'embarquement</strong>. Ne le publiez jamais sur les "
                "réseaux sociaux."
            )),
        ],
    },
    # ------------------------------------------------------------------ 17
    {
        "title": "Voyager sans connexion internet",
        "intro": (
            "En gare, le réseau manque souvent — et c'est précisément là "
            "qu'il faut montrer son billet. L'application est prévue pour."
        ),
        "blocks": [
            ("step", "Préparer avant de partir", (
                "Tant que vous avez du réseau, ouvrez votre billet une "
                "fois et téléchargez-le. L'application garde alors tout le "
                "nécessaire sur votre téléphone."
            )),
            ("step", "Présenter votre billet sans réseau", (
                "Ouvrez « Voyages », puis votre billet, puis le QR Code. "
                "Tout s'affiche normalement."
            )),
            ("figs", ["57-offline-ticket", "58-offline-qr"]),
            ("look", "Comment savoir que c'est prêt", [
                ("« Billet enregistré sur cet appareil et accessible hors "
                 "ligne »",
                 "Ce bandeau, avec une icône de nuage barré, vous confirme "
                 "que le billet est disponible sans réseau."),
                ("« Copie hors connexion »",
                 "Sous « Ouvrir le billet » : l'application vous sert la "
                 "copie enregistrée sur le téléphone."),
            ]),
            ("fig", "59-offline-pdf"),
            ("note", (
                "Sans réseau, vous ne pouvez pas rechercher de nouveaux "
                "départs ni payer : ces fonctions ont besoin d'internet. "
                "Elles reviennent dès que la connexion est rétablie."
            )),
        ],
    },
    # ------------------------------------------------------------------ 18
    {
        "title": "Questions fréquentes",
        "intro": (
            "Les situations que l'on rencontre le plus souvent, et la "
            "conduite à tenir."
        ),
        "blocks": [
            ("faq", [
                ("Je n'ai pas reçu mon code de vérification.",
                 "Vérifiez que WhatsApp fonctionne sur le numéro que vous "
                 "avez donné, et que vous avez du réseau. Attendez une "
                 "minute, puis touchez « Renvoyer ». Si le code ne part "
                 "toujours pas, l'application vous l'indique en rouge : "
                 "essayez alors avec une adresse e-mail."),
                ("J'ai oublié mon code PIN.",
                 "Sur l'écran de connexion, touchez « PIN oublié ? ». KORA "
                 "vous envoie un code de vérification, puis vous laisse "
                 "choisir un nouveau code PIN. Vos billets ne sont pas "
                 "affectés."),
                ("L'application affiche « Aucun voyage ce jour-là ».",
                 "Aucun bus ne dessert cette liaison à cette date. Essayez "
                 "un autre jour. Si votre ville a plusieurs points "
                 "d'arrêt, vérifiez aussi que vous avez choisi le bon."),
                ("Mon décompte est arrivé à zéro avant que je paie.",
                 "Vos sièges sont repartis à la vente. Relancez la "
                 "recherche : le même bus est souvent encore disponible."),
                ("Mon paiement a été refusé.",
                 "Rien n'a été débité. Vérifiez le solde de votre compte "
                 "Mobile Money et le numéro saisi, puis reprenez la "
                 "réservation."),
                ("La fenêtre de mon opérateur ne s'est pas affichée.",
                 "Vérifiez que votre téléphone a du réseau et que le "
                 "numéro saisi est bien celui de votre compte Mobile "
                 "Money. Passé le délai, l'application vous propose "
                 "« Actualiser le statut » et « Signaler un problème »."),
                ("Je suis déconnecté sans avoir rien fait.",
                 "Pour votre sécurité, une session a une durée limitée. "
                 "Reconnectez-vous avec votre code PIN : vos billets vous "
                 "attendent."),
                ("Je change de téléphone.",
                 "Connectez-vous simplement sur le nouveau. KORA vous "
                 "demandera un code de vérification, puis vos billets vous "
                 "suivront. Pensez à révoquer l'ancien appareil dans "
                 "« Sécurité »."),
                ("Mon téléphone sera déchargé le jour du départ.",
                 "Téléchargez le billet PDF et imprimez-le avant de "
                 "partir. Le QR Code imprimé est scanné de la même "
                 "façon."),
                ("Quelqu'un me demande mon code PIN ou mon code Mobile "
                 "Money.",
                 "Ne le donnez jamais. Ni KORA, ni votre opérateur ne vous "
                 "les demanderont. Si cela vous arrive, changez votre code "
                 "PIN et signalez-le au support."),
            ]),
        ],
    },
    # ------------------------------------------------------------------ 19
    {
        "title": "Glossaire",
        "intro": (
            "Tous les mots de ce manuel qui ne vont pas de soi, "
            "rassemblés en un endroit."
        ),
        "blocks": [
            ("defs", [
                ("Aller-retour",
                 "Réserver le trajet aller et le trajet retour en une "
                 "seule fois, avec un seul paiement."),
                ("Appareil autorisé",
                 "Un téléphone que votre compte reconnaît. Un appareil "
                 "inconnu doit passer par un code de vérification."),
                ("Code de vérification",
                 "Code à six chiffres envoyé par KORA sur WhatsApp ou par "
                 "e-mail, pour confirmer que le numéro ou l'adresse vous "
                 "appartient. Usage unique, valable quelques minutes."),
                ("Code PIN",
                 "Votre code secret à quatre chiffres, choisi par vous, "
                 "qui ouvre l'application. KORA ne le connaît pas et ne "
                 "vous le demandera jamais."),
                ("Code promo",
                 "Code de réduction fourni par KORA, à saisir sur le "
                 "récapitulatif avant de payer."),
                ("Convoyeur",
                 "L'agent KORA qui accueille les voyageurs à "
                 "l'embarquement et scanne les billets."),
                ("Décompte",
                 "Le temps pendant lequel votre siège vous est réservé "
                 "avant paiement. Une fois écoulé, le siège repart à la "
                 "vente."),
                ("Gare KORA / Arrêt KORA",
                 "Le point précis d'où part ou arrive le bus. Une ville "
                 "peut en compter plusieurs."),
                ("Hors ligne",
                 "Sans connexion internet. Votre billet reste consultable "
                 "hors ligne s'il a été ouvert au moins une fois."),
                ("Mobile Money",
                 "Paiement par téléphone proposé par les opérateurs — MTN "
                 "MoMo, Moov Flooz, Celtiis Cash. Le montant est prélevé "
                 "sur le solde de votre compte mobile."),
                ("PDF",
                 "Format de document qui garde sa mise en page. Le billet "
                 "officiel KORA en est un : on peut l'enregistrer, "
                 "l'imprimer et l'envoyer."),
                ("QR Code",
                 "Le carré noir et blanc du billet. Scanné par le "
                 "convoyeur, il prouve que votre place est payée."),
                ("Récapitulatif",
                 "L'écran qui résume votre voyage et son prix, juste avant "
                 "le paiement."),
                ("Réservation assistée",
                 "Confier votre réservation à un agent KORA, qui la "
                 "finalise et encaisse pour vous."),
                ("Session",
                 "La période pendant laquelle vous restez connecté. Elle a "
                 "une durée limitée, pour votre sécurité."),
            ]),
        ],
    },
]

CONTACT = {
    "title": "Besoin d'aide ?",
    "lead": (
        "Si une question reste sans réponse, l'application vous met en "
        "relation avec KORA en deux gestes : touchez « Profil », puis "
        "« Centre d'aide » ou « WhatsApp KORA »."
    ),
    "rows": [
        ("Depuis l'application",
         "Onglet « Profil », rubrique « Besoin d'aide ? »."),
        ("Pour une question sur un billet",
         "Gardez la référence affichée sur le billet : elle permet de le "
         "retrouver immédiatement."),
        ("Pour une question sur un paiement",
         "Notez la « Référence paiement » affichée après la transaction."),
        ("Le jour du départ",
         "Présentez-vous à la gare au moins 30 minutes avant l'heure "
         "annoncée, billet prêt à être scanné."),
    ],
}

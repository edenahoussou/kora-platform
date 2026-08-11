(() => {
  "use strict";

  const logo = "assets/kora-logo.png";
  const state = {
    current: "splash",
    previous: [],
    selectedSeats: ["4A"],
    timer: 420,
    passengerCount: 1,
    tripType: "oneway",
    pin: "",
    otp: "",
    walletUse: 5000,
    pointsUse: 500,
    firstPin: "",
    rideVehicle: "KORA GO",
    ridePrice: 2850,
    rideMessages: [
      { from: "driver", text: "Bonjour Aïcha, je suis en route vers votre position." },
      { from: "user", text: "Bonjour Karim, je vous attends devant l’entrée principale." },
      { from: "driver", text: "Parfait, j’arrive dans environ 4 minutes." }
    ],
    dark: false
  };

  const groups = [
    {
      name: "Authentification",
      screens: [
        ["splash", "AUTH-01", "Splash KORA"],
        ["welcome", "AUTH-02", "Bienvenue"],
        ["phone", "AUTH-03", "Adresse email"],
        ["otp", "AUTH-04", "Vérification OTP"],
        ["create-pin", "AUTH-05", "Création du PIN"],
        ["confirm-pin", "AUTH-06", "Confirmation du PIN"],
        ["permissions", "AUTH-07", "Autorisations"],
        ["login", "AUTH-08", "Connexion par PIN"],
        ["locked", "AUTH-09", "Compte bloqué"],
        ["new-device", "AUTH-10", "Nouvel appareil"],
        ["otp-error", "AUTH-11", "Code incorrect ou expiré"]
      ]
    },
    {
      name: "Accueil & notifications",
      screens: [
        ["home", "HOME-01", "Accueil super-app"],
        ["notifications", "HOME-02", "Centre de notifications"],
        ["notification-detail", "HOME-03", "Détail d’une notification"]
      ]
    },
    {
      name: "Réservation BUS",
      screens: [
        ["bus-search", "BUS-01", "Recherche BUS"],
        ["origin", "BUS-02", "Choix de l’origine"],
        ["destination", "BUS-03", "Choix de la destination"],
        ["date", "BUS-04", "Choix de la date"],
        ["passengers", "BUS-05", "Nombre de passagers"],
        ["searching", "BUS-06", "Recherche en cours"],
        ["results", "BUS-07", "Résultats des voyages"],
        ["no-results", "BUS-08", "Aucun résultat"],
        ["offline-results", "BUS-09", "Résultats hors ligne"],
        ["departure", "BUS-10", "Détail d’un départ"],
        ["seats", "BUS-11", "Choix des sièges"],
        ["expired", "BUS-12", "Réservation expirée"],
        ["passenger-info", "BUS-13", "Informations passagers"],
        ["international-info", "BUS-14", "Informations internationales"]
        ,["group-booking", "BUS-15", "Réservation de groupe"]
        ,["group-discount", "BUS-16", "Remise groupe"]
        ,["multi-tickets", "BUS-17", "Billets QR des passagers"]
        ,["company-account", "BUS-18", "Compte entreprise"]
        ,["company-trips", "BUS-19", "Déplacements de l’organisation"]
        ,["company-credit", "BUS-20", "Plafond de crédit entreprise"]
        ,["return-date", "BUS-21", "Date du retour"]
        ,["return-results", "BUS-22", "Départs du retour"]
        ,["roundtrip-summary", "BUS-23", "Récapitulatif aller-retour"]
        ,["connection-detail", "BUS-24", "Détail de la correspondance"]
        ,["connection-ticket", "BUS-25", "Billet avec correspondance"]
      ]
    },
    {
      name: "Paiement",
      screens: [
        ["summary", "PAY-01", "Récapitulatif"],
        ["payment-method", "PAY-02", "Choix du paiement"],
        ["mobile-money", "PAY-03", "Paiement Mobile Money"],
        ["mobile-validation", "PAY-04", "Validation sur téléphone"],
        ["payment-pending", "PAY-05", "Paiement en attente"],
        ["payment-refused", "PAY-06", "Paiement refusé"],
        ["wallet-payment", "PAY-07", "Paiement Wallet"],
        ["ticket-preparing", "PAY-08", "Billet en préparation"],
        ["booking-success", "PAY-09", "Réservation confirmée"],
        ["payment-timeout", "PAY-10", "Délai de paiement dépassé"],
        ["paid-no-ticket", "PAY-11", "Paiement reçu, billet différé"],
        ["double-payment", "PAY-12", "Double paiement refusé"],
        ["payment-reversed", "PAY-13", "Paiement annulé par l’opérateur"],
        ["card-payment-bus", "PAY-14", "Carte bancaire Bus"],
        ["card-payment-ride", "PAY-15", "Carte bancaire Ride"],
        ["card-payment-parcel", "PAY-16", "Carte bancaire Colis"],
        ["card-payment-wallet", "PAY-17", "Carte bancaire Wallet"],
        ["card-payment-premium", "PAY-18", "Carte bancaire Premium"]
      ]
    },
    {
      name: "Billets & suivi",
      screens: [
        ["ticket", "TICKET-01", "Billet numérique"],
        ["share-ticket", "TICKET-02", "Partager le billet"],
        ["tickets", "TICKET-03", "Mes billets"],
        ["ticket-detail", "TICKET-04", "Détail du billet"],
        ["tracking", "TICKET-05", "Suivi du bus"],
        ["stops", "TICKET-06", "Détail des escales"],
        ["gps-offline", "TICKET-07", "GPS hors connexion"],
        ["trip-complete", "TICKET-08", "Voyage terminé"]
      ]
    },
    {
      name: "Annulation",
      screens: [
        ["cancel-policy", "CANCEL-01", "Conditions d’annulation"],
        ["cancel-confirm", "CANCEL-02", "Confirmer l’annulation"],
        ["cancel-impossible", "CANCEL-03", "Annulation impossible"],
        ["refund-success", "CANCEL-04", "Crédit Wallet confirmé"],
        ["cancelled-ticket", "CANCEL-05", "Billet annulé"],
        ["operator-cancelled", "CANCEL-06", "Départ annulé par KORA"],
        ["counter-ticket-locked", "CANCEL-07", "Billet guichet non annulable"]
      ]
    },
    {
      name: "Suivi colis",
      screens: [
        ["parcel-search", "PARCEL-01", "Rechercher un colis"],
        ["parcel-tracking", "PARCEL-02", "Suivi du colis"],
        ["parcel-arrived", "PARCEL-03", "Colis arrivé"],
        ["parcel-error", "PARCEL-04", "Colis introuvable"],
        ["parcel-history", "PARCEL-05", "Mes colis"],
        ["parcel-detail", "PARCEL-06", "Détails du colis"],
        ["parcel-qr", "PARCEL-07", "QR Code colis"],
        ["parcel-incident", "PARCEL-08", "Incident colis"],
        ["parcel-returned", "PARCEL-09", "Colis retourné"],
        ["parcel-options", "PARCEL-10", "Options de réception"]
        ,["parcel-create", "PARCEL-11", "Nouvel envoi"]
        ,["parcel-service", "PARCEL-12", "Mode de dépôt et livraison"]
        ,["parcel-details-form", "PARCEL-13", "Informations du colis"]
        ,["parcel-recipient", "PARCEL-14", "Expéditeur et destinataire"]
        ,["parcel-quote", "PARCEL-15", "Devis colis"]
        ,["parcel-payment", "PARCEL-16", "Paiement colis"]
        ,["parcel-confirmed", "PARCEL-17", "Envoi colis confirmé"]
        ,["parcel-pickup-map", "PARCEL-18", "Collecte à domicile"]
        ,["parcel-delivery-address", "PARCEL-19", "Adresse de livraison"]
        ,["parcel-delivery-quote", "PARCEL-20", "Tarif de livraison locale"]
        ,["parcel-delivery-assigned", "PARCEL-21", "Livreur assigné"]
        ,["parcel-delivery-live", "PARCEL-22", "Suivi de la livraison"]
        ,["parcel-recipient-notification", "PARCEL-23", "Notification du destinataire"]
        ,["parcel-delivered-final", "PARCEL-24", "Livraison colis terminée"]
      ]
    },
    {
      name: "Wallet & fidélité",
      screens: [
        ["wallet", "WALLET-01", "Mon Wallet"],
        ["wallet-history", "WALLET-02", "Historique des mouvements"],
        ["wallet-detail", "WALLET-03", "Détail d’un mouvement"],
        ["wallet-use", "WALLET-04", "Utiliser mes points"],
        ["wallet-expiry", "WALLET-05", "Expiration prochaine"]
        ,["wallet-recharge", "WALLET-06", "Recharger le Wallet"]
        ,["wallet-recharge-method", "WALLET-07", "Moyen de recharge"]
        ,["wallet-recharge-pending", "WALLET-08", "Recharge en attente"]
        ,["wallet-recharge-refused", "WALLET-09", "Recharge refusée"]
        ,["wallet-recharge-success", "WALLET-10", "Recharge confirmée"]
        ,["wallet-receipt", "WALLET-11", "Reçu de recharge"]
        ,["wallet-bonuses", "WALLET-12", "Bonus fidélité"]
        ,["wallet-referral", "WALLET-13", "Parrainage"]
        ,["wallet-referral-status", "WALLET-14", "Suivi des filleuls"]
        ,["wallet-services", "WALLET-15", "Utiliser sur les services KORA"]
        ,["wallet-combined", "WALLET-16", "Paiement combiné"]
        ,["wallet-insufficient", "WALLET-17", "Solde insuffisant"]
        ,["wallet-rules", "WALLET-18", "Règles du Wallet"]
        ,["premium-home", "PREMIUM-01", "KORA Premium"]
        ,["premium-plans", "PREMIUM-02", "Choix de l’abonnement"]
        ,["premium-checkout", "PREMIUM-03", "Paiement Premium"]
        ,["premium-confirmed", "PREMIUM-04", "Abonnement activé"]
        ,["premium-benefits", "PREMIUM-05", "Avantages Premium"]
        ,["premium-manage", "PREMIUM-06", "Gérer l’abonnement"]
      ]
    },
    {
      name: "KORA Ride",
      screens: [
        ["ride-home", "RIDE-01", "Commander une course"],
        ["ride-pickup", "RIDE-02", "Point de départ"],
        ["ride-destination", "RIDE-03", "Destination"],
        ["ride-vehicle", "RIDE-04", "Choix du véhicule"],
        ["ride-summary", "RIDE-05", "Récapitulatif de course"],
        ["ride-searching", "RIDE-06", "Recherche d’un chauffeur"],
        ["ride-no-driver", "RIDE-07", "Aucun chauffeur disponible"],
        ["ride-driver-found", "RIDE-08", "Chauffeur trouvé"],
        ["ride-chat", "RIDE-08B", "Messagerie avec le chauffeur"],
        ["ride-arriving", "RIDE-09", "Chauffeur en approche"],
        ["ride-in-progress", "RIDE-11", "Course en cours"],
        ["ride-offline", "RIDE-12", "Course hors ligne"],
        ["ride-payment", "RIDE-13", "Paiement de la course"],
        ["ride-cash-confirm", "RIDE-14", "Confirmation paiement cash"],
        ["ride-complete", "RIDE-15", "Course terminée"],
        ["ride-rating", "RIDE-16", "Notation du chauffeur"],
        ["ride-history", "RIDE-17", "Historique des courses"],
        ["ride-mobile-money", "RIDE-18", "Paiement Ride Mobile Money"],
        ["ride-wallet-payment", "RIDE-19", "Paiement Ride Wallet"]
        ,["ride-share", "RIDE-20", "Partager le trajet en direct"]
        ,["ride-sos", "RIDE-21", "Assistance SOS"]
        ,["ride-attempts", "RIDE-22", "Tentatives de recherche"]
        ,["ride-payment-pending", "RIDE-23", "Paiement Ride en attente"]
        ,["ride-payment-refused", "RIDE-24", "Paiement Ride refusé"]
      ]
    },
    {
      name: "Profil & aide",
      screens: [
        ["profile", "PROFILE-01", "Mon profil"],
        ["profile-edit", "PROFILE-02", "Modifier mes informations"],
        ["security", "PROFILE-03", "Sécurité et appareils"],
        ["preferences", "PROFILE-04", "Préférences"],
        ["help", "HELP-01", "Centre d’aide"],
        ["contact", "HELP-02", "Contacter KORA"]
      ]
    }
  ];

  const registry = Object.fromEntries(
    groups.flatMap(group => group.screens.map(([id, code, title]) => [id, { id, code, title, group: group.name }]))
  );

  const app = document.querySelector("#app");
  const screenCode = document.querySelector("#screen-code");
  const screenName = document.querySelector("#screen-name");
  const backdrop = document.querySelector("#directory-backdrop");
  const directoryList = document.querySelector("#directory-list");
  const filterInput = document.querySelector("#directory-filter");

  const icon = (char) => `<span aria-hidden="true">${char}</span>`;
  const money = (n) => `${new Intl.NumberFormat("fr-FR").format(n)} FCFA`;
  const nav = (active = "home") => `
    <nav class="bottom-nav" aria-label="Navigation principale">
      <button data-goto="home" class="${active === "home" ? "active" : ""}"><i class="nav-icon nav-home"></i><span>Accueil</span></button>
      <button data-goto="tickets" class="${active === "tickets" ? "active" : ""}"><i class="nav-icon nav-ticket"></i><span>Voyages</span></button>
      <button data-goto="parcel-search" class="${active === "parcel" ? "active" : ""}"><i class="nav-icon nav-parcel"></i><span>Colis</span></button>
      <button data-goto="wallet" class="${active === "wallet" ? "active" : ""}"><i class="nav-icon nav-wallet"></i><span>Wallet</span></button>
      <button data-goto="profile" class="${active === "profile" ? "active" : ""}"><i class="nav-icon nav-profile"></i><span>Profil</span></button>
    </nav>`;

  const header = (title, eyebrow = "", options = {}) => `
    <header class="app-header">
      ${options.back === false ? `<img class="brand-mini" src="${logo}" alt="KORA Express">` : `<button class="icon-button" data-back aria-label="Retour">‹</button>`}
      <div style="flex:1">
        ${eyebrow ? `<span class="eyebrow">${eyebrow}</span>` : ""}
        ${title ? `<h2>${title}</h2>` : ""}
      </div>
      ${options.action || ""}
    </header>`;

  const screen = (body, options = {}) => `
    <section class="screen ${options.noNav ? "no-nav" : ""} ${options.navy ? "navy-screen" : ""}">
      ${body}
    </section>
    ${options.noNav ? "" : nav(options.active)}`;

  const qr = () => {
    const pattern = "111011101101001011111010111001101001111110100101001111010101110010111001011111001";
    return `<div class="qr" aria-label="QR Code du billet">${[...pattern].map(v => `<i style="background:${v === "1" ? "var(--kora)" : "white"}"></i>`).join("")}</div>`;
  };

  const tripCard = (time, arrival, price, seats, action = "departure", from = "Cotonou", to = "Malanville") => `
    <article class="trip-card clickable" data-goto="${action}" tabindex="0">
      <div class="trip-head"><span class="tag gold"><i class="trip-mode-icon">${mobilityPictogram("bus")}</i>KORA Confort</span><span class="tiny muted">${seats} places</span></div>
      <div class="trip-route">
        <div class="trip-time">${time}<small>${from}</small></div>
        <div class="route-line"><span>≈ 9 h 45</span></div>
        <div class="trip-time" style="text-align:right">${arrival}<small>${to}</small></div>
      </div>
      <div class="trip-foot"><span class="tiny muted">Wi-Fi · Climatisation · USB</span><div class="dynamic-price"><small>Prix final garanti</small><strong>${money(price)}</strong></div></div>
    </article>`;

  const ticketMarkup = (cancelled = false) => `
    <article class="digital-ticket ${cancelled ? "cancelled" : ""}">
      ${cancelled ? `<div class="cancel-stamp">ANNULÉ</div>` : ""}
      <div class="ticket-top row"><img src="${logo}" alt="KORA Express"><span class="tag gold">${cancelled ? "Annulé" : "Confirmé"}</span></div>
      <div class="ticket-route">
        <div class="ticket-city"><strong>CTN</strong><small>Cotonou</small></div>
        <div class="ticket-arrow">⟶</div>
        <div class="ticket-city"><strong>MLV</strong><small>Malanville</small></div>
      </div>
      <div class="ticket-cut"></div>
      <div class="ticket-body">
        ${qr()}
        <div class="ticket-data">
          <div><small>Date</small><strong>04 AOÛ</strong></div>
          <div><small>Départ</small><strong>07:30</strong></div>
          <div><small>Siège</small><strong>4A</strong></div>
        </div>
        <div class="divider"></div>
        <div class="row"><span class="tiny muted">KE-20260804-78342</span><strong class="tiny">A. HOUNKPÈ</strong></div>
      </div>
    </article>`;

  const stateView = ({ title, text, symbol = "!", primary, primaryLabel = "Réessayer", secondary, secondaryLabel = "Retour à l’accueil", loader = false }) => `
    <div class="state-visual"><div class="state-icon">${symbol}</div></div>
    <div class="state-copy">
      <h2>${title}</h2>
      <p>${text}</p>
    </div>
    ${loader ? `<div class="loader-route"></div>` : ""}
    <div class="stack">
      ${primary ? `<button class="primary" data-goto="${primary}">${primaryLabel}</button>` : ""}
      ${secondary ? `<button class="ghost" data-goto="${secondary}">${secondaryLabel}</button>` : ""}
    </div>`;

  const toast = (message) => {
    const el = document.createElement("div");
    el.className = "toast";
    el.textContent = message;
    document.querySelector("#toast-region").appendChild(el);
    window.setTimeout(() => el.remove(), 2600);
  };

  const renderers = {
    splash: () => screen(`
      <div style="min-height:720px;display:grid;place-items:center;text-align:center;align-content:center">
        <img src="${logo}" alt="KORA Express" style="width:230px;padding:18px;border-radius:22px;background:white">
        <p class="lead" style="color:rgba(255,255,255,.62);margin-top:24px">Voyagez plus loin,<br>en toute confiance.</p>
        <div class="loader-route"></div>
        <button class="link-button" data-goto="welcome" style="color:white;margin-top:10px">Passer l’introduction</button>
      </div>`, { noNav: true, navy: true }),

    welcome: () => screen(`
      <div class="welcome-experience">
        <section class="welcome-scene">
          <div class="welcome-brand"><img src="${logo}" alt="KORA Express"><span>Voyagez plus loin</span></div>
          <div class="welcome-orbit orbit-one"></div><div class="welcome-orbit orbit-two"></div>
          <div class="welcome-stars"><i></i><i></i><i></i></div>
          <div class="welcome-route">
            <span class="welcome-station start"></span>
            <span class="welcome-road"><i></i><b class="welcome-bus">K</b></span>
            <span class="welcome-station finish"></span>
          </div>
          <div class="welcome-service-pill"><i class="line-shield"></i><span><strong>Voyage sous contrôle</strong><small>Billet, paiement et suivi réunis</small></span></div>
        </section>
        <section class="welcome-content">
          <h1>Plus qu’un trajet.<br><em>Une expérience KORA.</em></h1>
          <p>Réservez votre place, voyagez sereinement et gardez chaque étape à portée de main.</p>
          <div class="welcome-benefits"><span><i>✓</i>Paiement sécurisé</span><span><i>✓</i>Billet hors ligne</span><span><i>✓</i>Suivi en direct</span></div>
          <div class="welcome-actions">
            <button class="primary welcome-primary" data-goto="phone"><span>Créer mon compte</span><b>→</b></button>
            <button class="welcome-login" data-goto="login">J’ai déjà un compte <b>›</b></button>
          </div>
          <div class="welcome-progress"><i class="active"></i><i></i><i></i></div>
        </section>
      </div>`, { noNav: true }),

    phone: () => screen(`
      ${header("Votre adresse email", "Création de compte")}
      <p class="lead">Nous vous enverrons un code de vérification sécurisé par email.</p>
      <label class="field"><span>Adresse email</span><input class="field-control" value="aicha.hounkpe@example.com" inputmode="email" autocomplete="email"></label>
      <div class="card mist"><div class="row start"><b style="color:var(--gold)">◎</b><p style="margin:0">Votre email sert d’identifiant KORA et reste protégé.</p></div></div>
      <div style="margin-top:24px"><button class="primary" data-goto="otp">Recevoir mon code</button></div>`, { noNav: true }),

    otp: () => screen(`
      ${header("Code de vérification", "Sécurité")}
      <p class="lead">Saisissez le code envoyé à <strong>a•••••@exemple.com</strong>.</p>
      <div class="code-row">${["5","8","3","1","",""].map((v,i)=>`<input class="code-box ${i===4?"active":""}" value="${v}" maxlength="1" inputmode="numeric" data-otp-digit aria-label="Chiffre OTP ${i+1}">`).join("")}</div>
      <div class="row"><span class="tiny muted">Code valable pendant 04:12</span><button class="link-button" data-toast="Un nouveau code a été envoyé.">Renvoyer</button></div>
      <div style="margin-top:25px"><button class="primary" data-goto="create-pin">Vérifier le code</button></div>
      <button class="ghost" style="margin-top:10px" data-toast="Un nouveau code vient de vous être envoyé.">Je n’ai pas reçu le code</button>
      <button class="link-button" style="width:100%;margin-top:8px" data-goto="otp-error">Voir l’état code invalide</button>`, { noNav: true }),

    "create-pin": () => pinScreen("Créez votre PIN", "Choisissez 4 chiffres faciles à retenir pour vous, difficiles à deviner pour les autres.", "confirm-pin"),
    "confirm-pin": () => pinScreen("Confirmez votre PIN", "Saisissez à nouveau votre code personnel.", "permissions", true),

    permissions: () => screen(`
      ${header("Presque terminé", "Autorisations")}
      <p class="lead">Activez les services utiles. Vous pourrez modifier ces choix plus tard.</p>
      <div class="stack">
        ${permissionCard("⌖", "Localisation", "Suivre votre bus et trouver les points KORA proches.")}
        ${permissionCard("◉", "Notifications", "Recevoir votre billet, les rappels et les alertes de trajet.")}
      </div>
      <div style="margin-top:25px" class="stack">
        <button class="primary" data-goto="home">Continuer vers KORA</button>
        <button class="link-button" data-goto="home">Plus tard</button>
      </div>`, { noNav: true }),

    login: () => screen(`
      <div style="text-align:center;margin-top:35px"><img src="${logo}" alt="KORA Express" style="width:150px;padding:8px;border-radius:13px;background:white"></div>
      <div style="text-align:center;margin-top:32px"><span class="eyebrow">Bon retour parmi nous</span><h2 style="color:white;margin:6px 0">Votre PIN KORA</h2><p class="lead">aicha.hounkpe@example.com</p></div>
      <div class="pin-dots">${[0,1,2,3].map(i=>`<i class="${i<state.pin.length?"filled":""}"></i>`).join("")}</div>
      ${keypad()}
      <button class="link-button" style="color:white;width:100%;margin-top:12px" data-goto="otp">PIN oublié ?</button>
      <button class="link-button" style="color:white;width:100%;margin-top:8px" data-goto="new-device">Connexion sur un nouvel appareil</button>`, { noNav: true, navy: true }),

    locked: () => screen(`
      ${header("", "", { action: "" })}
      ${stateView({ title: "Compte temporairement bloqué", text: "Après plusieurs PIN incorrects, l’accès est suspendu pendant 30 minutes. Un code de déblocage sera envoyé par email.", symbol: "⌛", primary: "otp", primaryLabel: "Recevoir le code par email", secondary: "welcome", secondaryLabel: "Retour" })}`, { noNav: true }),

    "new-device": () => screen(`
      ${header("Nouvel appareil détecté", "Protection du compte")}
      <div class="security-device-visual"><i class="line-shield"></i><span>Connexion à confirmer</span></div>
      <h2 class="form-title">Est-ce bien vous ?</h2>
      <p class="lead">Une vérification par email est obligatoire avant d’autoriser cet appareil, même avec le bon PIN.</p>
      <div class="card mist"><div class="row"><span class="muted">Appareil</span><strong>Tecno CAMON · Cotonou</strong></div><div class="divider"></div><div class="row"><span class="muted">Tentative</span><strong>À l’instant</strong></div></div>
      <button class="primary" style="margin-top:16px" data-goto="otp">Recevoir le code OTP</button>
      <button class="ghost" style="margin-top:9px" data-toast="Cette tentative a été signalée et bloquée.">Ce n’est pas moi</button>`, { noNav: true }),

    "otp-error": () => screen(`
      ${header("Code non valide", "Vérification OTP")}
      ${stateView({ title: "Le code est incorrect ou expiré", text: "Un seul code reste actif à la fois. Demandez-en un nouveau puis saisissez les six chiffres dans les cinq minutes.", symbol: "!", primary: "otp", primaryLabel: "Recevoir un nouveau code", secondary: "phone", secondaryLabel: "Modifier l’email" })}`, { noNav: true }),

    home: () => screen(`
      <div class="home-hero">
        <div class="home-top"><img class="brand-mini" src="${logo}" alt="KORA Express"><button class="icon-button notification-button" data-goto="notifications" aria-label="Notifications">◉</button></div>
        <div class="greeting"><small>Bonjour Aïcha,</small><h2>Votre prochain voyage commence ici.</h2></div>
      </div>
      <div class="floating-search">
        <div class="search-card-label"><span>Où souhaitez-vous voyager ?</span><b>KORA BUS</b></div>
        <div class="route-fields">
          <button class="route-field" data-goto="origin" style="width:100%;border:0;border-bottom:1px solid var(--line);background:none;text-align:left"><i class="route-dot"></i><div><small>Départ</small><strong>Cotonou · St Michel</strong></div></button>
          <button class="route-field" data-goto="destination" style="width:100%;border:0;background:none;text-align:left"><i class="route-dot gold"></i><div><small>Arrivée</small><strong>Malanville</strong></div></button>
          <button class="swap-button" data-toast="Origine et destination inversées">⇅</button>
        </div>
        <div class="home-search-meta">
          <button data-goto="date"><span>Date</span><strong>04 août 2026</strong></button>
          <button data-goto="passengers"><span>Passagers</span><strong>1 adulte</strong></button>
        </div>
        <button class="primary home-search-button" data-goto="searching">Rechercher un voyage</button>
      </div>
      <div class="section-title"><h3>Accès rapides</h3></div>
      <div class="home-quick-grid">
        <button class="home-quick-item active" data-goto="ride-home"><b>⌁</b><span>Ride</span></button>
        <button class="home-quick-item" data-goto="parcel-search"><b>◇</b><span>Colis</span></button>
        <button class="home-quick-item" data-goto="wallet"><b>◫</b><span>Wallet</span></button>
        <button class="home-quick-item" data-goto="tickets"><b>▣</b><span>Mes billets</span></button>
      </div>
      <button class="home-ride-banner" data-goto="ride-home">
        <span class="home-ride-car">${mobilityPictogram("car")}</span>
        <span><small>KORA RIDE</small><strong>Un déplacement en ville ?</strong><em>Commandez votre chauffeur en quelques instants.</em></span>
        <span class="home-ride-arrow">→</span>
      </button>
      <div class="section-title"><h3>Prochain voyage</h3><button data-goto="tickets">Voir tout</button></div>
      <div class="card navy clickable" data-goto="ticket-detail">
        <div class="row"><span class="tag">Dans 7 jours</span><span class="tiny">Siège 4A</span></div>
        <div style="margin:16px 0 11px;font-size:19px;font-weight:900">Cotonou <span style="color:var(--gold)">⟶</span> Malanville</div>
        <div class="row"><span class="tiny" style="color:rgba(255,255,255,.65)">04 août · 07:30</span><span>→</span></div>
      </div>
      <button class="travel-reminder" data-goto="ticket-detail">
        <span class="reminder-icon">◉</span>
        <span><strong>Rappel utile</strong><small>Présentez-vous 30 min avant le départ.</small></span>
        <b>›</b>
      </button>`, { active: "home" }),

    notifications: () => screen(`
      ${header("Notifications", "Votre activité", { action: `<button class="link-button" data-toast="Toutes les notifications sont marquées comme lues.">Tout lire</button>` })}
      <div class="stack">
        ${notification("▣","Billet confirmé","Votre voyage Cotonou → Malanville est confirmé.","Il y a 2 min","notification-detail",true)}
        ${notification("◷","Départ dans 2 heures","Présentez-vous à la gare KORA St Michel.","Aujourd’hui","ticket-detail",true)}
        ${notification("!","Retard estimé : 35 min","Nouveau départ prévu à 08:05. Circulation dense.","Aujourd’hui","tracking")}
        ${notification("◫","+ 12 500 FCFA","Votre remboursement a été crédité sur le Wallet.","Hier","wallet-detail")}
        ${notification("◇","Votre colis est arrivé","KC-20260728-40982 est disponible à Parakou.","Lun.","parcel-arrived")}
        ${notification("×","Départ annulé","Remboursement intégral déclenché sur votre Wallet.","Lun.","operator-cancelled")}
        ${notification("◎","Rappel de voyage","Votre bus part demain à 07:30 depuis St Michel.","28 juil.","ticket-detail")}
      </div>`, { active: "home" }),

    "notification-detail": () => screen(`
      ${header("Votre billet est prêt", "Notification")}
      <div class="card navy">
        <span class="tag">Confirmation</span>
        <h2 style="font-size:24px;margin:18px 0 8px">Cotonou → Malanville</h2>
        <p>Votre réservation KE-20260804-78342 est confirmée. Présentez votre QR Code à l’embarquement.</p>
      </div>
      <div class="card" style="margin-top:12px">
        <div class="row"><span class="muted tiny">Départ</span><strong>04 août · 07:30</strong></div>
        <div class="divider"></div>
        <div class="row"><span class="muted tiny">Gare</span><strong>St Michel, Cotonou</strong></div>
        <div class="divider"></div>
        <div class="row"><span class="muted tiny">Siège</span><strong>4A</strong></div>
      </div>
      <button class="primary" style="margin-top:16px" data-goto="ticket">Afficher mon billet</button>`, { active: "home" }),

    "bus-search": () => screen(`
      ${header("Réserver un voyage", "KORA BUS", { action: `<button class="icon-button" data-goto="help" aria-label="Aide">?</button>` })}
      <div class="trip-type-switch"><button class="${state.tripType==="oneway"?"active":""}" data-trip-type="oneway">Aller simple</button><button class="${state.tripType==="roundtrip"?"active":""}" data-trip-type="roundtrip">Aller-retour</button></div>
      ${bookingProgress(1, "Votre trajet")}
      <div class="booking-route-card">
        <div class="booking-route-head"><span>Trajet</span><button data-toast="Origine et destination inversées">Inverser ↕</button></div>
        <button class="booking-location" data-goto="origin">
          <i class="station-point origin-point"></i>
          <span><small>Départ</small><strong>Cotonou · St Michel</strong><em>Gare KORA recommandée</em></span>
          <b>›</b>
        </button>
        <div class="booking-route-rail"><i></i><i></i><i></i></div>
        <button class="booking-location" data-goto="destination">
          <i class="station-point destination-point"></i>
          <span><small>Arrivée</small><strong>Malanville</strong><em>Gare centrale KORA</em></span>
          <b>›</b>
        </button>
      </div>
      <div class="booking-meta-grid">
        <button data-goto="date"><i class="line-calendar"></i><span><small>Date du voyage</small><strong>Mar. 04 août</strong></span><b>›</b></button>
        <button data-goto="passengers"><i class="line-users"></i><span><small>Passagers</small><strong>${state.passengerCount} adulte</strong></span><b>›</b></button>
      </div>
      ${state.tripType==="roundtrip"?`<button class="return-date-card" data-goto="return-date"><i class="line-calendar"></i><span><small>Date du retour</small><strong>Dim. 09 août</strong></span><b>›</b></button>`:""}
      <div class="comfort-note">
        <i class="line-shield"></i>
        <span><strong>Voyagez l’esprit tranquille</strong><small>Bagage inclus · Assistance KORA · Paiement sécurisé</small></span>
      </div>
      <button class="primary booking-cta" data-goto="searching">Rechercher ${state.tripType==="roundtrip"?"l’aller et le retour":"les départs"} <span>→</span></button>
      <div class="recent-routes">
        <div class="section-title"><h3>Trajets récents</h3></div>
        <button data-goto="searching"><span>Cotonou</span><i></i><span>Parakou</span><b>›</b></button>
        <button data-goto="searching"><span>Porto-Novo</span><i></i><span>Lomé</span><b>›</b></button>
      </div>`, { active: "home" }),

    origin: () => locationPicker("D’où partez-vous ?", "Cotonou", "destination", ["Cotonou · St Michel","Porto-Novo · Gare KORA","Bohicon · Carrefour","Parakou · Centre-ville"]),
    destination: () => locationPicker("Où allez-vous ?", "Malanville", "date", ["Malanville · Gare KORA","Parakou · Centre-ville","Kandi · RNIE 2","Natitingou · Gare KORA"]),
    date: () => calendarScreen(),
    passengers: () => passengerScreen(),

    searching: () => screen(`
      ${header("Recherche en cours", "Cotonou → Malanville")}
      ${stateView({ title: "Nous trouvons votre meilleur départ", text: "Disponibilités et tarifs sont vérifiés sur chaque segment du trajet.", symbol: "↗", loader: true })}
      <div class="stack"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>
      <button class="link-button" style="width:100%;margin-top:15px" data-goto="results">Afficher les résultats</button>`, { noNav: true }),

    results: () => screen(`
      ${header("3 départs disponibles", "Mar. 04 août · 1 passager", { action: `<button class="icon-button" data-goto="bus-search" aria-label="Modifier">⌁</button>` })}
      <div class="price-assurance"><i>✓</i><span><strong>Prix affichés définitifs</strong><small>Les éventuelles règles de remplissage sont déjà incluses.</small></span></div>
      <div class="row" style="margin-bottom:12px"><button class="tag outline" data-toast="Tri par heure appliqué">Heure ⌄</button><button class="tag outline" data-toast="Filtre prix appliqué">Prix ⌄</button><button class="tag outline" data-toast="Filtre services ouvert">Services</button></div>
      ${tripCard("07:30","17:15",12500,12)}
      ${tripCard("09:00","18:55",12500,5)}
      ${tripCard("20:00","05:45",14500,22)}
      <button class="connection-result-card" data-goto="connection-detail"><span class="tag gold">1 correspondance</span><div><strong>06:45</strong><i></i><strong>17:40</strong></div><h3>Cotonou → Bohicon → Natitingou</h3><p>Changement de bus à Bohicon · attente 35 min</p><footer><span>2 segments inclus</span><b>14 500 FCFA ›</b></footer></button>
      <article class="trip-card sold-out" aria-disabled="true">
        <div class="trip-head"><span class="tag outline">KORA Confort</span><span class="tag danger">Complet</span></div>
        <div class="trip-route"><div class="trip-time">22:30<small>Cotonou</small></div><div class="route-line"><span>≈ 9 h 45</span></div><div class="trip-time">08:15<small>Malanville</small></div></div>
        <div class="trip-foot"><span>Aucune place sur ce segment</span><strong>Indisponible</strong></div>
      </article>
      ${state.tripType==="roundtrip"?`<button class="primary" style="margin-top:13px" data-goto="return-results">Continuer avec ce trajet aller</button>`:""}
      <button class="link-button" style="width:100%;margin-top:15px" data-goto="no-results">Voir l’état sans résultat</button>`, { active: "home" }),

    "no-results": () => screen(`
      ${header("Aucun départ", "Cotonou → Malanville")}
      ${stateView({ title: "Aucun voyage ce jour-là", text: "Essayez une autre date. Les prochains départs disponibles sont mercredi et vendredi.", symbol: "⌕", primary: "date", primaryLabel: "Modifier la date", secondary: "bus-search", secondaryLabel: "Modifier le trajet" })}`, { active: "home" }),

    "offline-results": () => screen(`
      <div class="card gold" style="margin:-10px 0 14px"><div class="row"><strong>⌁ Mode hors ligne</strong><span class="tiny">Dernière mise à jour 09:32</span></div></div>
      ${header("Derniers départs connus", "Cotonou → Malanville")}
      <p class="lead">Les prix et disponibilités devront être confirmés au retour du réseau.</p>
      ${tripCard("07:30","17:15",12500,12)}
      ${tripCard("20:00","05:45",14500,22)}
      <button class="primary" style="margin-top:16px" data-toast="Tentative de reconnexion…">Réessayer la connexion</button>`, { active: "home" }),

    departure: () => screen(`
      ${header("Votre départ", "Ligne L2 · KORA Confort")}
      <div class="card navy">
        <span class="tag">12 places restantes</span>
        <div class="trip-route" style="margin-top:23px">
          <div class="trip-time" style="color:white">07:30<small style="color:rgba(255,255,255,.6)">Cotonou</small></div>
          <div class="route-line" style="background:rgba(255,255,255,.2)"><span style="color:rgba(255,255,255,.55)">≈ 9 h 45</span></div>
          <div class="trip-time" style="color:white;text-align:right">17:15<small style="color:rgba(255,255,255,.6)">Malanville</small></div>
        </div>
        <div class="row"><span class="tiny">Mardi 04 août 2026</span><strong>12 500 F</strong></div>
      </div>
      <div class="section-title"><h3>Votre confort à bord</h3></div>
      <div class="quick-grid">
        ${serviceTile("⌁","Wi-Fi")}
        ${serviceTile("❄","Climatisé")}
        ${serviceTile("▣","USB")}
      </div>
      <div class="section-title"><h3>Les étapes</h3><button data-goto="stops">Tout voir</button></div>
      <div class="card">
        ${miniTimeline(["Cotonou · 07:30","Bohicon · ≈ 09:20","Parakou · ≈ 13:25","Malanville · ≈ 17:15"])}
      </div>
      <div class="card mist" style="margin-top:11px"><p style="margin:0">Les heures de passage sont des estimations actualisées pendant le trajet.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="seats">Choisir mon siège</button>`, { active: "home" }),

    seats: () => seatScreen(),
    expired: () => screen(`
      ${header("", "")}
      ${stateView({ title: "Votre réservation a expiré", text: "Le délai de 10 minutes est terminé et le siège 4A a été libéré. Sélectionnez à nouveau un siège disponible.", symbol: "⌛", primary: "seats", primaryLabel: "Choisir un autre siège", secondary: "results", secondaryLabel: "Retour aux départs" })}`, { noNav: true }),
    "passenger-info": () => passengerInfo(false),
    "international-info": () => passengerInfo(true),

    "return-date": () => screen(`
      ${header("Quand souhaitez-vous revenir ?", "Aller-retour")}
      <div class="outbound-reminder"><span>ALLER</span><strong>Mar. 04 août · Cotonou → Malanville</strong></div>
      <div class="card"><div class="row"><button class="icon-button" data-toast="Mois précédent">‹</button><strong style="color:var(--kora)">Août 2026</strong><button class="icon-button" data-toast="Mois suivant">›</button></div><div class="return-calendar">${[5,6,7,8,9,10,11,12,13,14,15,16,17,18].map(d=>`<button data-goto="bus-search" class="${d===9?"selected":""}">${d}</button>`).join("")}</div></div>
      <button class="primary" style="margin-top:14px" data-goto="bus-search">Choisir le dimanche 09 août</button>`, { noNav: true }),

    "return-results": () => screen(`
      ${header("Choisissez votre retour", "Malanville → Cotonou · Dim. 09 août")}
      <div class="outbound-reminder"><span>ALLER CHOISI</span><strong>04 août · 07:30 → 17:15</strong></div>
      ${tripCard("07:00","16:45",12500,14,"roundtrip-summary","Malanville","Cotonou")}
      ${tripCard("10:30","20:15",12500,7,"roundtrip-summary","Malanville","Cotonou")}
      ${tripCard("19:30","05:15",14500,19,"roundtrip-summary","Malanville","Cotonou")}`, { active: "home" }),

    "roundtrip-summary": () => screen(`
      ${header("Votre aller-retour", "Deux trajets · un seul paiement")}
      <section class="roundtrip-leg"><span>ALLER</span><div><strong>Cotonou → Malanville</strong><small>Mar. 04 août · 07:30–17:15</small></div><button data-goto="results">Modifier</button></section>
      <section class="roundtrip-leg"><span>RETOUR</span><div><strong>Malanville → Cotonou</strong><small>Dim. 09 août · 07:00–16:45</small></div><button data-goto="return-results">Modifier</button></section>
      <div class="card">${detailRows([["Aller","12 500 FCFA"],["Retour","12 500 FCFA"],["Total","25 000 FCFA"]])}</div>
      <div class="card mist"><p style="margin:0">Vous recevrez un billet aller et un billet retour, chacun avec son QR Code.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="seats">Choisir les sièges de l’aller</button>`, { active: "tickets" }),

    "connection-detail": () => screen(`
      ${header("Voyage avec correspondance", "Cotonou → Natitingou")}
      <div class="connection-alert"><i>!</i><span><strong>Changement de bus à Bohicon</strong><small>Temps prévu : 35 minutes · assistance KORA sur place</small></span></div>
      <section class="connection-leg"><b>1</b><div><small>SEGMENT 1 · BUS KE-204</small><strong>Cotonou → Bohicon</strong><p>06:45–09:05 · Siège 4A</p></div></section>
      <div class="connection-wait"><span>35 min de correspondance</span><small>Bagage transféré par l’équipe KORA</small></div>
      <section class="connection-leg"><b>2</b><div><small>SEGMENT 2 · BUS KE-418</small><strong>Bohicon → Natitingou</strong><p>09:40–17:40 · Siège 6C</p></div></section>
      <div class="card mist"><p style="margin:0">Un seul achat, mais <strong>deux QR Codes</strong> : un par bus. Cela évite qu’un premier scan bloque le second embarquement.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="connection-ticket">Choisir ce voyage</button>`, { active: "home" }),

    "connection-ticket": () => screen(`
      ${header("Votre billet correspondance", "2 segments confirmés")}
      <div class="connection-ticket-card"><span>SEGMENT 1</span><strong>Cotonou → Bohicon</strong><div class="mini-ticket-qr">${qr()}</div><small>QR 1 · Bus KE-204 · Siège 4A</small></div>
      <div class="connection-ticket-card"><span>SEGMENT 2</span><strong>Bohicon → Natitingou</strong><div class="mini-ticket-qr">${qr()}</div><small>QR 2 · Bus KE-418 · Siège 6C</small></div>
      <div class="card gold"><p style="margin:0;color:var(--kora)">Présentez le QR correspondant à chaque embarquement.</p></div>`, { active: "tickets" }),

    "group-booking": () => screen(`
      ${header("Réservation de groupe", "5 à 10 passagers")}
      <div class="group-hero"><span>10</span><div><h2>Un seul paiement, un billet par voyageur</h2><p>Choisissez jusqu’à 10 sièges. Chaque passager reçoit son propre QR Code.</p></div></div>
      <div class="card">${detailRows([["Passagers","10 adultes"],["Tarif initial","125 000 FCFA"],["Remise groupe · 10 %","− 12 500 FCFA"],["Total estimé","112 500 FCFA"]])}</div>
      <div class="card mist"><p style="margin:0">La remise configurée par KORA s’applique automatiquement dès que le seuil est atteint.</p></div>
      <button class="primary" style="margin-top:14px" data-group-booking>Continuer avec 10 passagers</button>`, { noNav: true }),

    "group-discount": () => screen(`
      ${header("Votre remise groupe", "Avantage appliqué")}
      <div class="discount-hero"><small>Vous économisez</small><strong>12 500 FCFA</strong><span>−10 % appliqué automatiquement</span></div>
      <div class="card">${miniTimeline(["Choisir 10 sièges","Renseigner les 10 passagers","Régler en un seul paiement"])}</div>
      <button class="primary" style="margin-top:14px" data-goto="seats">Choisir les sièges</button>`, { noNav: true }),

    "multi-tickets": () => screen(`
      ${header("Billets du groupe", "10 voyageurs · 10 QR Codes")}
      <div class="multi-ticket-list">
        ${["Aïcha HOUNKPÈ · 4A","Nadia ADJOVI · 4B","Serge KORA · 5A","Mariam DOSSOU · 5B"].map((name,i)=>`<button data-goto="ticket"><span class="mini-qr">${i+1}</span><span><strong>${name}</strong><small>KE-20260804-78${342+i} · Prêt</small></span><b>›</b></button>`).join("")}
      </div>
      <div class="card mist"><p style="margin:0">Chaque QR est unique. La validation d’un billet n’affecte pas les autres voyageurs.</p></div>
      <button class="primary" style="margin-top:14px" data-toast="Les 10 billets ont été préparés pour WhatsApp.">Partager tous les billets</button>`, { active: "tickets" }),

    "company-account": () => screen(`
      ${header("Compte entreprise", "ONG Horizon Bénin")}
      <div class="company-hero"><span>OH</span><div><small>Crédit mensuel disponible</small><strong>1 240 000 FCFA</strong><p>Plafond : 2 000 000 FCFA</p></div></div>
      <div class="wallet-action-grid"><button data-goto="bus-search"><span>＋</span><strong>Réserver</strong></button><button data-goto="company-trips"><span>▣</span><strong>Déplacements</strong></button><button data-goto="company-credit"><span>◫</span><strong>Crédit</strong></button><button data-toast="Rapport mensuel préparé en PDF."><span>↗</span><strong>Rapport</strong></button></div>
      <div class="card">${detailRows([["Référent","Aïcha HOUNKPÈ"],["Période","Juillet 2026"],["Voyages","38"],["Consommé","760 000 FCFA"]])}</div>`, { active: "tickets" }),

    "company-trips": () => screen(`
      ${header("Déplacements de l’organisation", "Juillet 2026")}
      <div class="card">${transaction("▣","Cotonou → Parakou","Nadia A. · 28 juil.","12 500 F")}${transaction("▣","Porto-Novo → Lomé","Serge K. · 25 juil.","15 000 F")}${transaction("▣","Cotonou → Malanville","Aïcha H. · 22 juil.","12 500 F")}</div>
      <button class="primary" style="margin-top:14px" data-toast="Rapport CSV/PDF préparé.">Exporter le rapport mensuel</button>`, { active: "tickets" }),

    "company-credit": () => screen(`
      ${header("Plafond de crédit", "ONG Horizon Bénin")}
      <div class="wallet-card"><small>Disponible ce mois</small><div class="balance">1 240 000 <small>FCFA</small></div><div class="wallet-foot"><span>Utilisé : 760 000 F</span><span>62 % restant</span></div></div>
      <div class="ride-course-progress" style="margin-top:16px"><i style="width:38%"></i></div>
      <div class="card">${detailRows([["Plafond mensuel","2 000 000 FCFA"],["Prochaine facture","31 juillet"],["Mode de règlement","Facture mensuelle"],["Statut","Compte actif"]])}</div>
      <button class="primary" style="margin-top:14px" data-goto="bus-search">Réserver sur le crédit</button>`, { active: "tickets" }),

    summary: () => screen(`
      ${header("Vérifiez votre voyage", "Récapitulatif")}
      <div class="card navy">
        <div class="row"><span class="tag">Ligne L2</span><span class="tiny">Siège ${state.selectedSeats.join(", ")}</span></div>
        <h3 style="margin:18px 0 6px;font-size:19px">Cotonou → Malanville</h3>
        <p>Mardi 04 août · 07:30<br>Gare KORA St Michel</p>
      </div>
      <div class="section-title"><h3>Passagers</h3><button data-goto="passenger-info">Modifier</button></div>
      <div class="card">${Array.from({length:state.passengerCount},(_,i)=>`<div class="passenger-summary-row"><div><strong>${i===0?"Aïcha HOUNKPÈ":`Passager ${i+1} KORA`}</strong><small>+229 01 97 42 18 ${60+i}</small></div><span class="tag gold">${state.selectedSeats[i]||"—"}</span></div>`).join("")}</div>
      <div class="section-title"><h3>Détail du prix</h3></div>
      <div class="card">
        <div class="row"><span class="muted">${state.passengerCount} billet${state.passengerCount>1?"s":""} KORA Confort</span><strong>${money(12500*state.passengerCount)}</strong></div>
        <div class="divider"></div>
        <div class="row"><span class="muted">Frais de service</span><strong>Inclus</strong></div>
        <div class="divider"></div>
        ${state.passengerCount>=5?`<div class="row"><span class="muted">Remise groupe</span><strong>Incluse au tarif affiché</strong></div><div class="divider"></div>`:""}
        <div class="row"><strong>Total à payer</strong><span class="amount">${money(12500*state.passengerCount)}</span></div>
      </div>
      <button class="primary" style="margin-top:15px" data-goto="payment-method">Continuer vers le paiement</button>`, { active: "tickets" }),

    "payment-method": () => screen(`
      ${header("Comment souhaitez-vous payer ?", "Paiement sécurisé")}
      <button class="card clickable" data-goto="mobile-money" style="width:100%;text-align:left">
        <div class="row"><div class="row" style="justify-content:flex-start"><span class="transaction-icon">M</span><div><strong>Mobile Money</strong><p>MTN · Moov · Celtiis · Wave</p></div></div><span>›</span></div>
      </button>
      <button class="card clickable" data-goto="wallet-payment" style="width:100%;text-align:left">
        <div class="row"><div class="row" style="justify-content:flex-start"><span class="transaction-icon">◫</span><div><strong>KORA Wallet</strong><p>Solde disponible : 18 750 FCFA</p></div></div><span>›</span></div>
      </button>
      <button class="card clickable" data-goto="card-payment-bus" style="width:100%;text-align:left">
        <div class="row"><div class="row" style="justify-content:flex-start"><span class="transaction-icon card-symbol">▭</span><div><strong>Carte bancaire</strong><p>Visa · Mastercard</p></div></div><span>›</span></div>
      </button>
      <div class="card mist" style="margin-top:14px"><p style="margin:0">Vos sièges restent réservés pendant <strong data-live-timer>06:42</strong>.</p></div>
      <div class="card" style="margin-top:14px"><div class="row"><span>Total</span><strong class="amount">${money(12500*state.passengerCount)}</strong></div></div>`, { noNav: true }),

    "mobile-money": () => screen(`
      ${header("Mobile Money", `Paiement · ${money(12500*state.passengerCount)}`)}
      <label class="field"><span>Choisissez votre opérateur</span><select class="field-control" required><option value="" selected disabled>Sélectionner un opérateur</option><option>MTN MoMo</option><option>Moov Money</option><option>Celtiis Cash</option><option>Wave</option></select></label>
      <label class="field"><span>Numéro à débiter</span><input class="field-control" value="+229 01 97 42 18 60"></label>
      <div class="card mist"><p style="margin:0">Une demande de confirmation apparaîtra sur votre téléphone. Ne partagez jamais votre code secret Mobile Money.</p></div>
      <button class="primary" style="margin-top:20px" data-goto="mobile-validation">Payer ${money(12500*state.passengerCount)}</button>`, { noNav: true }),

    "mobile-validation": () => screen(`
      ${header("Confirmez sur votre téléphone", "MTN MoMo")}
      ${stateView({ title: "Une demande vous a été envoyée", text: "Composez votre code secret Mobile Money sur la fenêtre sécurisée de votre opérateur.", symbol: "M", primary: "payment-pending", primaryLabel: "J’ai confirmé", secondary: "payment-method", secondaryLabel: "Changer de moyen" })}
      <div class="card mist" style="margin-top:13px"><div class="row"><span class="tiny muted">Expire dans</span><strong>00:28</strong></div></div>`, { noNav: true }),

    "payment-pending": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Paiement en cours de vérification…", text: "Votre siège reste réservé. La confirmation peut prendre quelques instants selon le réseau Mobile Money.", symbol: "↻", loader: true, primary: "booking-success", primaryLabel: "Actualiser le statut", secondary: "payment-refused", secondaryLabel: "Signaler un problème" })}
      <p class="tiny muted" style="text-align:center;margin-top:18px">Vérification automatique toutes les 10 secondes</p>
      <div class="payment-case-links"><button data-goto="payment-timeout">Délai dépassé</button><button data-goto="paid-no-ticket">Débité sans billet</button><button data-goto="double-payment">Double paiement</button><button data-goto="payment-reversed">Paiement annulé</button></div>`, { noNav: true }),

    "payment-refused": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Le paiement n’a pas abouti", text: "Aucun montant n’a été prélevé. Vérifiez votre solde ou choisissez un autre moyen de paiement.", symbol: "×", primary: "mobile-money", primaryLabel: "Réessayer", secondary: "payment-method", secondaryLabel: "Changer de moyen" })}
      <button class="link-button" style="width:100%;margin-top:13px" data-goto="contact">Besoin d’aide ?</button>`, { noNav: true }),

    "wallet-payment": () => screen(`
      ${header("Payer avec le Wallet", "Paiement total ou partiel")}
      <div class="wallet-card" style="min-height:155px">
        <small>Solde disponible</small><div class="balance">18 750 <small>FCFA</small></div>
      </div>
      <div class="section-title"><h3>Montant à utiliser</h3></div>
      <div class="wallet-amount-control"><button data-wallet-amount="-1000">−</button><div><small>Crédit Wallet utilisé</small><strong>${money(state.walletUse)}</strong></div><button data-wallet-amount="1000">+</button></div>
      <label class="range-field"><span>Points fidélité utilisés</span><strong>${state.pointsUse} points</strong><input type="range" min="100" max="2400" step="100" value="${state.pointsUse}" data-points-range></label>
      <div class="card">
        <div class="row"><span class="muted">Total du billet</span><strong>${money(12500*state.passengerCount)}</strong></div>
        <div class="divider"></div>
        <div class="row"><span class="muted">Wallet + points</span><strong>− ${money(state.walletUse+state.pointsUse)}</strong></div>
        <div class="divider"></div>
        <div class="row"><span class="muted">Complément Mobile Money</span><strong style="color:var(--digital)">${money(Math.max(0,12500*state.passengerCount-state.walletUse-state.pointsUse))}</strong></div>
      </div>
      <button class="primary" style="margin-top:18px" data-goto="${12500*state.passengerCount-state.walletUse-state.pointsUse>0?"mobile-money":"booking-success"}">Confirmer et continuer</button>
      <div class="card mist" style="margin-top:9px"><p style="margin:0">Minimum 100 points. Les crédits et points ne peuvent être ni retirés ni transférés.</p></div>`, { noNav: true }),

    "ticket-preparing": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Paiement reçu, billet en préparation", text: "Votre paiement est sécurisé. Une anomalie retarde l’émission du billet ; notre équipe est déjà informée.", symbol: "✓", loader: true, primary: "booking-success", primaryLabel: "Actualiser", secondary: "contact", secondaryLabel: "Contacter KORA" })}
      <div class="card mist" style="margin-top:16px;text-align:center"><span class="tiny muted">Référence paiement</span><strong style="display:block;color:var(--kora);margin-top:3px">MM-98270341</strong></div>`, { noNav: true }),

    "booking-success": () => screen(`
      <div style="min-height:690px;display:flex;flex-direction:column;justify-content:center">
        ${stateView({ title: "Votre voyage est confirmé !", text: "Le billet a été ajouté à votre compte et envoyé sur WhatsApp.", symbol: "✓", primary: "ticket", primaryLabel: "Afficher mon billet", secondary: "home", secondaryLabel: "Retour à l’accueil" })}
        <div class="card navy" style="margin-top:22px">
          <div class="row"><span class="tag">04 août</span><strong>Siège 4A</strong></div>
          <h3 style="margin:14px 0 5px">Cotonou → Malanville</h3>
          <p>Départ à 07:30 · St Michel</p>
        </div>
        ${state.passengerCount > 1 ? `<button class="ghost" style="margin-top:10px" data-goto="multi-tickets">Voir les ${state.passengerCount} billets individuels</button>` : ""}
      </div>`, { noNav: true }),

    "payment-timeout": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Paiement toujours en vérification", text: "Le délai de 30 secondes est dépassé. Vos sièges restent protégés deux minutes supplémentaires pendant nos vérifications.", symbol: "⌛", loader: true, primary: "payment-pending", primaryLabel: "Vérifier maintenant", secondary: "payment-method", secondaryLabel: "Changer de moyen" })}
      <div class="card gold" style="margin-top:14px"><div class="row"><span>Protection prolongée</span><strong>01:42</strong></div></div>`, { noNav: true }),

    "paid-no-ticket": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Paiement reçu, billet en cours d’émission", text: "Votre place est sécurisée et marquée PAYÉE. L’équipe KORA est avertie et votre billet sera émis sans nouveau paiement.", symbol: "✓", loader: true, primary: "ticket-preparing", primaryLabel: "Suivre l’émission", secondary: "contact", secondaryLabel: "Contacter KORA" })}
      <div class="card mist" style="margin-top:14px"><div class="row"><span class="muted">Référence</span><strong>MM-98270341</strong></div></div>`, { noNav: true }),

    "double-payment": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Ce billet a déjà été payé", text: "La seconde tentative a été refusée. Si un second débit apparaît, il sera automatiquement recrédité sur votre KORA Wallet.", symbol: "!", primary: "ticket", primaryLabel: "Voir le billet payé", secondary: "wallet", secondaryLabel: "Voir mon Wallet" })}`, { noNav: true }),

    "payment-reversed": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Paiement annulé par l’opérateur", text: "Le débit Mobile Money a été annulé après confirmation. Le montant a été crédité automatiquement sur votre KORA Wallet.", symbol: "↻", primary: "wallet-detail", primaryLabel: "Voir le remboursement", secondary: "payment-method", secondaryLabel: "Choisir un autre paiement" })}`, { noNav: true }),

    "card-payment-bus": () => cardPaymentScreen("Billet KORA BUS", money(12500*state.passengerCount), "booking-success", "payment-method"),
    "card-payment-ride": () => cardPaymentScreen("Course KORA RIDE", money(state.ridePrice), "ride-complete", "ride-payment"),
    "card-payment-parcel": () => cardPaymentScreen("Envoi KORA COLIS", "5 250 FCFA", "parcel-confirmed", "parcel-payment"),
    "card-payment-wallet": () => cardPaymentScreen("Recharge KORA Wallet", "10 000 FCFA", "wallet-recharge-success", "wallet-recharge-method"),
    "card-payment-premium": () => cardPaymentScreen("Abonnement KORA Premium", "2 500 FCFA", "premium-confirmed", "premium-checkout"),

    ticket: () => screen(`
      ${header("Votre billet", "Prêt pour l’embarquement", { action: `<button class="icon-button ticket-share-top" data-goto="share-ticket" aria-label="Partager">↗</button>` })}
      <div class="ticket-status-strip"><i></i><span><strong>Voyage confirmé</strong><small>Présentez ce QR Code au convoyeur</small></span><b>✓</b></div>
      <div class="premium-ticket-wrap">
        ${ticketMarkup()}
        <div class="qr-scan-line" aria-hidden="true"></div>
      </div>
      <div class="ticket-primary-actions">
        <button data-goto="tracking"><i class="line-location"></i><span><strong>Suivre le bus</strong><small>Position et escales</small></span><b>›</b></button>
        <button data-goto="share-ticket"><i class="line-share"></i><span><strong>Partager</strong><small>WhatsApp ou fichier</small></span><b>›</b></button>
      </div>
      <div class="offline-ticket-note"><i>✓</i><span>Billet enregistré sur cet appareil et accessible hors ligne</span></div>`, { active: "tickets" }),

    "share-ticket": () => screen(`
      ${header("Partager le billet", "KE-20260804-78342")}
      <div class="card navy" style="text-align:center;padding:23px">
        <div style="width:65px;height:65px;margin:auto;display:grid;place-items:center;border-radius:20px;background:var(--gold);color:var(--kora);font-size:28px;font-weight:900">W</div>
        <h3 style="font-size:20px;margin:14px 0 6px">Envoyer par WhatsApp</h3>
        <p>Le canal recommandé par KORA pour conserver votre billet.</p>
        <button class="primary" style="margin-top:17px" data-toast="Partage WhatsApp simulé avec succès.">Ouvrir WhatsApp</button>
      </div>
      <div class="menu-list" style="margin-top:12px">
        ${menuItem("▣","Télécharger le billet","Format image disponible hors ligne","Billet enregistré sur l’appareil.")}
        ${menuItem("⌁","Copier la référence","KE-20260804-78342","Référence copiée.")}
        ${menuItem("…","Plus d’options","Partage système simulé","Options de partage ouvertes.")}
      </div>`, { active: "tickets" }),

    tickets: () => screen(`
      ${header("Mes voyages", "Vos billets", { back: false, action: `<button class="icon-button" data-goto="notifications">◉</button>` })}
      <div class="row" style="justify-content:flex-start;margin-bottom:14px">
        <button class="tag gold" data-toast="Billets à venir affichés.">À venir · 1</button><button class="tag outline" data-goto="trip-complete">Passés · 3</button><button class="tag outline" data-goto="cancelled-ticket">Annulés · 1</button>
      </div>
      <div class="card navy clickable" data-goto="ticket-detail">
        <div class="row"><span class="tag">Confirmé</span><span class="tiny">KE-…78342</span></div>
        <h3 style="margin:18px 0 5px">Cotonou → Malanville</h3>
        <p>Mar. 04 août · 07:30 · Siège 4A</p>
        <div class="divider" style="background:rgba(255,255,255,.12)"></div>
        <div class="row"><span class="tiny">Départ dans 7 jours</span><span style="color:var(--gold)">Voir le billet →</span></div>
      </div>
      <div class="section-title"><h3>Une nouvelle destination ?</h3></div>
      <button class="primary" data-goto="bus-search">Réserver un voyage</button>`, { active: "tickets" }),

    "ticket-detail": () => screen(`
      ${header("Cotonou → Malanville", "Billet confirmé")}
      <div class="card navy">
        <div class="row"><span class="tag">Dans 7 jours</span><strong>4A</strong></div>
        <h3 style="font-size:21px;margin:17px 0 4px">Mar. 04 août · 07:30</h3>
        <p>Gare KORA St Michel, Cotonou</p>
      </div>
      <div class="menu-list" style="margin-top:13px">
        ${menuGoto("▦","Afficher le QR Code","Disponible hors ligne","ticket")}
        ${menuGoto("⌖","Suivre le bus","Position et escales","tracking")}
        ${menuGoto("↗","Partager mon billet","WhatsApp et téléchargement","share-ticket")}
        ${menuGoto("×","Annuler le billet","Voir les conditions","cancel-policy")}
      </div>
      <div class="card mist" style="margin-top:13px"><p style="margin:0">Présentez-vous au moins 30 minutes avant le départ.</p></div>`, { active: "tickets" }),

    tracking: () => trackingScreen(false),
    stops: () => stopsScreen(),
    "gps-offline": () => trackingScreen(true),
    "trip-complete": () => screen(`
      ${header("Voyage terminé", "Cotonou → Parakou")}
      <div class="state-visual" style="margin-top:10px"><div class="state-icon">✓</div></div>
      <div class="state-copy"><h2>Bien arrivé à destination</h2><p>Merci d’avoir voyagé avec KORA EXPRESS.</p></div>
      <div class="card">
        <div class="row"><span class="muted">Départ réel</span><strong>07:36</strong></div><div class="divider"></div>
        <div class="row"><span class="muted">Arrivée réelle</span><strong>13:41</strong></div><div class="divider"></div>
        <div class="row"><span class="muted">Durée</span><strong>6 h 05</strong></div>
      </div>
      <button class="primary" style="margin-top:15px" data-toast="Merci pour votre avis 5 étoiles !">Noter mon voyage</button>
      <button class="ghost" style="margin-top:9px" data-goto="bus-search">Réserver à nouveau</button>`, { active: "tickets" }),

    "cancel-policy": () => screen(`
      ${header("Annuler mon billet", "Conditions applicables")}
      <div class="card gold"><div class="row"><div><span class="tiny">Crédit estimé</span><div class="amount">9 375 FCFA</div></div><span class="tag outline">75 %</span></div></div>
      <div class="section-title"><h3>Détail</h3></div>
      <div class="card">
        <div class="row"><span class="muted">Montant payé</span><strong>12 500 F</strong></div>
        <div class="divider"></div><div class="row"><span class="muted">Pénalité applicable</span><strong>− 3 125 F</strong></div>
        <div class="divider"></div><div class="row"><strong>Crédit Wallet</strong><strong>9 375 F</strong></div>
      </div>
      <div class="card mist" style="margin-top:12px"><p style="margin:0">Le crédit est immédiat, sans retrait cash ni transfert Mobile Money.</p></div>
      <button class="primary" style="margin-top:17px" data-goto="cancel-confirm">Continuer</button>
      <button class="link-button" style="width:100%;margin-top:8px" data-goto="cancel-impossible">Voir l’état après H-72</button>
      <button class="link-button" style="width:100%;margin-top:7px" data-goto="counter-ticket-locked">Billet acheté au guichet</button>`, { noNav: true }),

    "cancel-confirm": () => screen(`
      ${header("Confirmer l’annulation", "Action irréversible")}
      ${stateView({ title: "Souhaitez-vous vraiment annuler ?", text: "Le siège 4A sera libéré immédiatement et 9 375 FCFA seront crédités sur votre Wallet.", symbol: "!", primary: "refund-success", primaryLabel: "Oui, annuler le billet", secondary: "ticket-detail", secondaryLabel: "Conserver mon billet" })}`, { noNav: true }),

    "cancel-impossible": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Ce billet ne peut plus être annulé", text: "Le délai autorisé est dépassé ou le billet a déjà été validé à l’embarquement.", symbol: "×", primary: "ticket-detail", primaryLabel: "Retour au billet", secondary: "contact", secondaryLabel: "Contacter le service client" })}`, { noNav: true }),

    "refund-success": () => screen(`
      ${header("", "")}
      ${stateView({ title: "9 375 FCFA crédités", text: "Votre billet est annulé. Le crédit est déjà disponible sur votre KORA Wallet.", symbol: "✓", primary: "wallet", primaryLabel: "Voir mon Wallet", secondary: "cancelled-ticket", secondaryLabel: "Voir le billet annulé" })}`, { noNav: true }),

    "cancelled-ticket": () => screen(`
      ${header("Billet annulé", "04 août 2026")}
      ${ticketMarkup(true)}
      <div class="card mist" style="margin-top:13px"><div class="row"><span class="muted">Crédit Wallet</span><strong>9 375 FCFA</strong></div><p>Crédité le 28 juillet à 11:42</p></div>
      <button class="primary" style="margin-top:13px" data-goto="bus-search">Réserver un autre voyage</button>`, { active: "tickets" }),

    "operator-cancelled": () => screen(`
      ${header("Départ annulé", "Décision KORA EXPRESS")}
      ${stateView({ title: "Remboursement intégral déclenché", text: "KORA a annulé le départ du 04 août. Les 12 500 FCFA seront crédités sur votre Wallet et une confirmation vous est envoyée par WhatsApp, SMS et notification.", symbol: "!", primary: "wallet", primaryLabel: "Voir le remboursement", secondary: "bus-search", secondaryLabel: "Choisir un autre départ" })}
      <div class="card gold" style="margin-top:13px"><div class="row"><span>Montant remboursé</span><strong>12 500 FCFA · 100 %</strong></div></div>`, { active: "tickets" }),

    "counter-ticket-locked": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Billet guichet non annulable", text: "Les billets achetés au guichet sont définitifs. Seul un administrateur peut intervenir dans une situation exceptionnelle.", symbol: "×", primary: "ticket-detail", primaryLabel: "Retour au billet", secondary: "contact", secondaryLabel: "Contacter KORA" })}`, { noNav: true }),

    "parcel-search": () => screen(`
      ${header("Mes colis", "KORA EXPRESS", { back: false, action: `<button class="icon-button" data-goto="help" aria-label="Aide">?</button>` })}
      <section class="parcel-hero">
        <div class="parcel-hero-copy"><span>Suivi intelligent</span><h2>Votre colis,<br>toujours à portée.</h2><p>Suivez chaque étape, du dépôt au retrait.</p></div>
        <div class="parcel-route-art" aria-hidden="true"><i></i><i></i><i></i><b>K</b></div>
      </section>
      <section class="parcel-search-box">
        <label><span>Référence du colis</span><div><i class="parcel-scan-icon"></i><input value="KC-20260728-40982" aria-label="Référence du colis"><button data-toast="Scanner disponible depuis l’appareil photo." aria-label="Scanner le reçu">⌗</button></div></label>
        <button class="primary parcel-search-cta" data-goto="parcel-tracking"><span>Suivre mon colis</span><b>→</b></button>
      </section>
      <button class="parcel-create-cta" data-goto="parcel-create"><span>＋</span><div><strong>Envoyer un nouveau colis</strong><small>Dépôt, collecte et livraison</small></div><b>›</b></button>
      <div class="parcel-section-head"><div><span>Activité récente</span><h3>Vos envois</h3></div><button data-goto="parcel-history">Voir tout</button></div>
      <button class="parcel-active-card" data-goto="parcel-tracking">
        <span class="parcel-status-line"><i></i> En transit</span>
        <div class="parcel-card-route"><div><small>Départ</small><strong>Cotonou</strong></div><i></i><div><small>Arrivée</small><strong>Parakou</strong></div></div>
        <div class="parcel-card-foot"><span>KC-…40982</span><b>Aujourd’hui · 16:30</b><em>›</em></div>
      </button>
      <button class="parcel-help-link" data-goto="parcel-error"><i>?</i><span><strong>Une référence ne fonctionne pas ?</strong><small>Nous vous aidons à retrouver votre envoi</small></span><b>›</b></button>`, { active: "parcel" }),

    "parcel-tracking": () => screen(`
      ${header("Suivi du colis", "KC-20260728-40982", { action: `<button class="icon-button" data-toast="Lien de suivi copié." aria-label="Partager">↗</button>` })}
      <section class="parcel-live-card">
        <div class="parcel-live-top"><span><i></i> En mouvement</span><small>Actualisé à 14:08</small></div>
        <div class="parcel-live-route"><div><b>CTN</b><small>Cotonou</small></div><div class="parcel-moving-line"><i></i><span>BUS L2</span></div><div><b>PKO</b><small>Parakou</small></div></div>
        <div class="parcel-eta"><span>Arrivée estimée</span><strong>Aujourd’hui · 16:30</strong></div>
      </section>
      <div class="parcel-section-head"><div><span>Progression</span><h3>4 étapes sur 6</h3></div><button data-goto="parcel-detail">Détails</button></div>
      <section class="parcel-timeline-card">${parcelTimeline()}</section>
      <section class="parcel-info-grid">
        <button data-goto="parcel-detail"><i class="line-users"></i><span><small>Destinataire</small><strong>Serge A.</strong></span><b>›</b></button>
        <button data-toast="Le point de retrait sera confirmé à l’arrivée."><i class="line-location"></i><span><small>Retrait</small><strong>KORA Parakou</strong></span><b>›</b></button>
      </section>
      <button class="primary parcel-refresh" data-toast="Suivi actualisé à l’instant."><span>Actualiser le suivi</span><b>↻</b></button>
      <button class="ghost" style="margin-top:9px" data-goto="parcel-arrived">Voir l’état « arrivé »</button>`, { active: "parcel" }),

    "parcel-arrived": () => screen(`
      ${header("Prêt à être retiré", "KC-…40982")}
      <section class="parcel-arrived-hero"><span class="parcel-check">✓</span><small>Arrivé à destination</small><h2>Votre colis vous attend à Parakou</h2><p>Disponible jusqu’au 04 août à 19:00.</p></section>
      <section class="pickup-pass">
        <div class="pickup-pass-head"><img src="${logo}" alt="KORA EXPRESS"><span>PASS DE RETRAIT</span></div>
        <div class="pickup-code"><small>Code confidentiel</small><strong>K4 0982</strong><button data-toast="Code de retrait copié.">Copier</button></div>
        <p>Présentez ce code et une pièce d’identité au comptoir.</p>
      </section>
      <button class="ghost" style="margin-top:9px" data-goto="parcel-qr">Afficher le QR Code colis</button>
      <button class="parcel-agency-card" data-toast="Itinéraire vers l’agence KORA Parakou ouvert."><i class="line-location"></i><span><small>Point de retrait</small><strong>Agence KORA Parakou</strong><em>Carrefour Hubert Maga · 07:00–19:00</em></span><b>›</b></button>
      <button class="parcel-agency-card" data-goto="parcel-options"><i class="line-share"></i><span><small>Mode de réception</small><strong>Retrait gratuit en agence</strong><em>Livraison locale disponible en option</em></span><b>›</b></button>
      <button class="primary" style="margin-top:12px" data-toast="Itinéraire vers l’agence KORA Parakou ouvert.">Afficher l’itinéraire</button>
      <button class="ghost" style="margin-top:9px" data-goto="contact">Contacter l’agence</button>`, { active: "parcel" }),

    "parcel-error": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Colis introuvable", text: "Cette référence n’existe pas ou n’est pas encore activée. Vérifiez les caractères puis réessayez.", symbol: "⌕", primary: "parcel-search", primaryLabel: "Corriger la référence", secondary: "contact", secondaryLabel: "Contacter KORA" })}
      <div class="card mist" style="margin-top:15px"><p style="margin:0">Format attendu : <strong>KC-AAAAMMJJ-NNNNN</strong></p></div>`, { active: "parcel" }),

    "parcel-history": () => screen(`
      ${header("Tous mes colis", "3 envois enregistrés")}
      <div class="parcel-filter-tabs"><button class="active" data-toast="Tous les colis affichés.">Tous</button><button data-toast="Filtre : en cours">En cours</button><button data-toast="Filtre : retirés">Retirés</button></div>
      <button class="parcel-history-card active" data-goto="parcel-tracking"><span class="parcel-box-mark">K</span><div><small>KC-…40982 · Aujourd’hui</small><strong>Cotonou → Parakou</strong><span>En transit · arrivée vers 16:30</span></div><b>›</b></button>
      <button class="parcel-history-card" data-goto="parcel-arrived"><span class="parcel-box-mark delivered">✓</span><div><small>KC-…38114 · 18 juillet</small><strong>Abidjan → Cotonou</strong><span>Retiré par le destinataire</span></div><b>›</b></button>
      <button class="parcel-history-card" data-goto="parcel-detail"><span class="parcel-box-mark delivered">✓</span><div><small>KC-…27401 · 02 juillet</small><strong>Lomé → Porto-Novo</strong><span>Livraison terminée</span></div><b>›</b></button>
      <button class="ghost" style="margin-top:14px" data-goto="parcel-search">Suivre une autre référence</button>`, { active: "parcel" }),

    "parcel-detail": () => screen(`
      ${header("Détails de l’envoi", "KC-20260728-40982")}
      <section class="parcel-detail-card"><div><small>Nature de l’envoi</small><strong>Petit colis · 2,4 kg</strong></div><span class="parcel-box-mark">K</span></section>
      <section class="parcel-contact-card"><h3>Expédition</h3><div><span>Expéditeur</span><strong>Awa Hounkpè</strong><small>+229 •• •• 18 32</small></div><i></i><div><span>Destinataire</span><strong>Serge Adandé</strong><small>+229 •• •• 44 12</small></div></section>
      <section class="parcel-detail-list">
        <div><span>Déposé à</span><strong>Agence KORA St Michel</strong></div>
        <div><span>Retrait prévu</span><strong>Agence KORA Parakou</strong></div>
        <div><span>Frais d’envoi</span><strong>2 966 F HT + 534 F TVA</strong></div>
        <div><span>Instructions</span><strong>Manipuler avec soin</strong></div>
      </section>
      <button class="primary" style="margin-top:13px" data-goto="parcel-tracking">Retour au suivi</button>
      <button class="ghost" style="margin-top:9px" data-goto="parcel-incident">Signaler un problème</button>
      <button class="link-button" style="width:100%;margin-top:8px" data-goto="parcel-returned">Voir l’état retourné</button>`, { active: "parcel" }),

    "parcel-qr": () => screen(`
      ${header("QR Code du colis", "KC-20260728-40982")}
      <section class="parcel-qr-card"><img src="${logo}" alt="KORA EXPRESS">${qr()}<strong>KC-20260728-40982</strong><p>Présentez ce QR Code au retrait. Il ne contient aucune donnée personnelle.</p></section>
      <button class="primary" style="margin-top:14px" data-toast="QR Code enregistré sur l’appareil.">Enregistrer hors ligne</button>
      <button class="ghost" style="margin-top:9px" data-goto="share-ticket">Partager sur WhatsApp</button>`, { active: "parcel" }),

    "parcel-incident": () => screen(`
      ${header("Signaler un incident", "KC-…40982")}
      <div class="incident-alert"><span>!</span><div><strong>Votre signalement sera prioritaire</strong><p>KORA ouvre un dossier et informe immédiatement l’expéditeur.</p></div></div>
      <label class="field"><span>Nature du problème</span><select class="field-control"><option>Colis endommagé</option><option>Colis perdu</option><option>Retard important</option><option>Autre problème</option></select></label>
      <label class="field"><span>Description</span><textarea class="field-control" rows="4">Décrivez précisément la situation observée.</textarea></label>
      <button class="ghost" data-toast="Photo ajoutée au dossier.">Ajouter une photo</button>
      <button class="primary" style="margin-top:10px" data-toast="Incident KCI-40982 ouvert. La Direction et l’expéditeur sont informés.">Envoyer le signalement</button>`, { active: "parcel" }),

    "parcel-returned": () => screen(`
      ${header("Colis retourné", "KC-…27401")}
      ${stateView({ title: "Retour vers l’expéditeur", text: "Le colis n’a pas été retiré dans le délai de 30 jours. Il est retourné à Cotonou avec frais de retour.", symbol: "↻", primary: "parcel-tracking", primaryLabel: "Suivre le retour", secondary: "contact", secondaryLabel: "Contacter KORA" })}
      <div class="card mist" style="margin-top:13px"><div class="row"><span class="muted">Nouvelle estimation</span><strong>02 août · 15:00</strong></div></div>`, { active: "parcel" }),

    "parcel-options": () => screen(`
      ${header("Recevoir mon colis", "Parakou")}
      <button class="delivery-option selected" data-toast="Retrait gratuit sélectionné."><i class="line-location"></i><span><strong>Retrait en agence</strong><small>Agence KORA Parakou · Gratuit</small></span><b>✓</b></button>
      <button class="delivery-option" data-goto="parcel-delivery-address"><i class="line-share"></i><span><strong>Livraison par zemidjan</strong><small>À votre adresse · à partir de 750 FCFA</small></span><b>›</b></button>
      <div class="card mist" style="margin-top:12px"><p style="margin:0">La livraison à domicile sera confiée à un partenaire KORA local après confirmation de l’adresse.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="parcel-arrived">Confirmer mon choix</button>`, { active: "parcel" }),

    "parcel-create": () => screen(`
      ${header("Nouvel envoi", "KORA COLIS")}
      <div class="booking-progress">${bookingProgress(1,"Service")}</div>
      <section class="parcel-create-hero"><span>K</span><div><h2>Envoyez partout, simplement</h2><p>Jusqu’à 30 kg avec suivi et preuve de retrait.</p></div></section>
      <button class="delivery-option selected" data-goto="parcel-service"><span class="transaction-icon">⌂</span><span><strong>Dépôt en agence ou KORA POINT</strong><small>Vous apportez le colis · disponible maintenant</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="parcel-pickup-map"><span class="transaction-icon">⌁</span><span><strong>Collecte à domicile</strong><small>Un zemidjan vient récupérer le colis</small></span><b>›</b></button>`, { active: "parcel" }),

    "parcel-pickup-map": () => screen(`
      ${header("Adresse de collecte", "Collecte à domicile")}
      <div class="ride-mini-map parcel-pickup-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><i class="ride-map-pin pin-center"></i></div>
      <label class="field"><span>Adresse</span><input class="field-control" value="Haie Vive · Rue 1382, Cotonou"></label>
      <label class="field"><span>Instructions au zemidjan</span><input class="field-control" value="Portail bleu, appeler à l’arrivée"></label>
      <div class="card mist"><p style="margin:0">Collecte estimée sous 45 minutes. Le prix sera ajouté au devis avant paiement.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="parcel-service">Confirmer l’adresse</button>`, { active: "parcel" }),

    "parcel-service": () => screen(`
      ${header("Trajet du colis", "Étape 2 sur 6")}
      <button class="booking-location" data-toast="Sélecteur de ville de départ ouvert."><i class="station-point"></i><span><small>Départ</small><strong>Cotonou</strong><em>Agence KORA St Michel</em></span><b>›</b></button>
      <button class="booking-location" data-toast="Sélecteur de ville d’arrivée ouvert."><i class="station-point destination-point"></i><span><small>Destination</small><strong>Parakou</strong><em>Agence KORA Centre</em></span><b>›</b></button>
      <div class="section-title"><h3>Réception à destination</h3></div>
      <button class="delivery-option selected" data-toast="Retrait en agence sélectionné."><span class="transaction-icon">K</span><span><strong>Retrait en agence</strong><small>Gratuit · code et pièce d’identité</small></span><b>✓</b></button>
      <button class="delivery-option" data-goto="parcel-delivery-address"><span class="transaction-icon">⌁</span><span><strong>Livraison à domicile</strong><small>Zemidjan KORA · à partir de 750 FCFA</small></span><b>›</b></button>
      <button class="primary" style="margin-top:14px" data-goto="parcel-details-form">Continuer</button>`, { active: "parcel" }),

    "parcel-details-form": () => screen(`
      ${header("Informations du colis", "Étape 3 sur 6")}
      <label class="field"><span>Nature de l’envoi</span><select class="field-control"><option>Vêtements et accessoires</option><option>Documents</option><option>Matériel électronique</option><option>Autre objet autorisé</option></select></label>
      <div class="grid-2"><label class="field"><span>Poids</span><input class="field-control" value="2,4 kg"></label><label class="field"><span>Valeur déclarée</span><input class="field-control" value="25 000 F"></label></div>
      <label class="field"><span>Description</span><input class="field-control" value="Petit carton fermé · vêtements"></label>
      <div class="parcel-warning"><strong>Objets interdits</strong><p>Produits inflammables, armes, médicaments non déclarés, espèces supérieures à 500 000 FCFA.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="parcel-recipient">Continuer</button>`, { active: "parcel" }),

    "parcel-recipient": () => screen(`
      ${header("Contacts de l’envoi", "Étape 4 sur 6")}
      <div class="section-title"><h3>Expéditeur</h3></div>
      <div class="grid-2"><label class="field"><span>Nom complet</span><input class="field-control" value="Aïcha HOUNKPÈ"></label><label class="field"><span>Téléphone</span><input class="field-control" value="+229 01 97 42 18 60"></label></div>
      <div class="section-title"><h3>Destinataire</h3></div>
      <div class="grid-2"><label class="field"><span>Nom complet</span><input class="field-control" value="Serge ADANDÉ"></label><label class="field"><span>Téléphone</span><input class="field-control" value="+229 01 96 44 12 08"></label></div>
      <label class="field"><span>Email du destinataire</span><input class="field-control" value="serge.adande@example.com" inputmode="email"></label>
      <div class="card mist"><p style="margin:0">Le destinataire recevra les alertes d’arrivée et son code confidentiel de retrait.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="parcel-quote">Calculer le devis</button>`, { active: "parcel" }),

    "parcel-quote": () => screen(`
      ${header("Votre devis", "Étape 5 sur 6")}
      <section class="parcel-quote-route"><span>Cotonou</span><i></i><span>Parakou</span></section>
      <div class="card">${detailRows([["Transport · jusqu’à 5 kg","4 500 FCFA"],["Collecte à domicile","750 FCFA"],["Livraison en agence","Incluse"],["Total TTC","5 250 FCFA"]])}</div>
      <div class="card mist"><p style="margin:0">Au-delà de 5 kg : +1 500 FCFA par tranche de 5 kg. Poids maximum : 30 kg.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="parcel-payment">Choisir le paiement</button>
      <button class="ghost" style="margin-top:9px" data-goto="parcel-details-form">Modifier le colis</button>`, { active: "parcel" }),

    "parcel-payment": () => screen(`
      ${header("Paiement de l’envoi", "5 250 FCFA")}
      <button class="delivery-option" data-goto="mobile-money"><span class="transaction-icon">M</span><span><strong>Mobile Money</strong><small>MTN · Moov · Celtiis · Wave</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="wallet-combined"><span class="transaction-icon">K</span><span><strong>Wallet et points</strong><small>Paiement total ou combiné</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="card-payment-parcel"><span class="transaction-icon card-symbol">▭</span><span><strong>Carte bancaire</strong><small>Visa · Mastercard</small></span><b>›</b></button>
      <button class="delivery-option selected" data-goto="parcel-confirmed"><span class="transaction-icon">F</span><span><strong>Payer au dépôt</strong><small>Cash ou Mobile Money à l’agence</small></span><b>›</b></button>
      <div class="card gold" style="margin-top:12px"><p style="margin:0;color:var(--kora)">Vous gagnerez 158 points après validation du paiement.</p></div>`, { active: "parcel" }),

    "parcel-confirmed": () => screen(`
      ${header("Envoi confirmé", "KC-20260729-51842")}
      <section class="parcel-final-hero"><span>✓</span><small>PRISE EN CHARGE CONFIRMÉE</small><h2>Votre colis entre dans le réseau KORA</h2><p>Collecte aujourd’hui entre 11:15 et 12:00 · arrivée estimée demain à Parakou.</p></section>
      <div class="card">${detailRows([["Trajet","Cotonou → Parakou"],["Service","Collecte à domicile + retrait agence"],["Destinataire","Serge ADANDÉ"],["Montant payé","5 250 FCFA"]])}</div>
      <div class="recipient-notified"><i>✓</i><span><strong>Serge a été informé</strong><small>Email et WhatsApp envoyés au destinataire</small></span><button data-goto="parcel-recipient-notification">Voir</button></div>
      <button class="primary" style="margin-top:13px" data-goto="parcel-tracking">Suivre mon colis</button>
      <button class="ghost" style="margin-top:9px" data-goto="parcel-qr">Afficher le bordereau QR</button>`, { active: "parcel" }),

    "parcel-delivery-address": () => screen(`
      ${header("Livraison à domicile", "Adresse du destinataire")}
      <div class="ride-mini-map parcel-delivery-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><i class="ride-map-pin pin-center"></i></div>
      <label class="field"><span>Quartier et adresse</span><input class="field-control" value="Zongo · Rue de la pharmacie, Parakou"></label>
      <div class="grid-2"><label class="field"><span>Destinataire</span><input class="field-control" value="Serge ADANDÉ"></label><label class="field"><span>Téléphone</span><input class="field-control" value="+229 01 96 44 12 08"></label></div>
      <label class="field"><span>Indication pour le livreur</span><input class="field-control" value="Maison beige, portail noir"></label>
      <button class="primary" style="margin-top:13px" data-goto="parcel-delivery-quote">Calculer le tarif</button>`, { active: "parcel" }),

    "parcel-delivery-quote": () => screen(`
      ${header("Confirmer la livraison", "Parakou · 3,8 km")}
      <section class="parcel-delivery-summary"><span class="transaction-icon">⌁</span><div><small>Livraison estimée</small><strong>Aujourd’hui · 17:15–17:45</strong><p>Zongo · Rue de la pharmacie</p></div></section>
      <div class="card">${detailRows([["Livraison locale","750 FCFA"],["Assurance colis","Incluse"],["Total à régler","750 FCFA"]])}</div>
      <button class="delivery-option selected" data-goto="parcel-delivery-assigned"><span class="transaction-icon">K</span><span><strong>KORA Wallet</strong><small>Solde disponible : 18 750 FCFA</small></span><b>✓</b></button>
      <button class="delivery-option" data-goto="mobile-money"><span class="transaction-icon">M</span><span><strong>Mobile Money</strong><small>MTN · Moov · Celtiis · Wave</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="card-payment-parcel"><span class="transaction-icon card-symbol">▭</span><span><strong>Carte bancaire</strong><small>Visa · Mastercard</small></span><b>›</b></button>
      <button class="primary" style="margin-top:13px" data-goto="parcel-delivery-assigned">Payer 750 FCFA et demander un livreur</button>`, { active: "parcel" }),

    "parcel-delivery-assigned": () => screen(`
      ${header("Livreur assigné", "Arrive à l’agence dans 8 min")}
      <section class="parcel-courier-card"><div class="driver-avatar">IM<span>VÉRIFIÉ</span></div><div><small>Votre livreur KORA</small><h2>Idriss M.</h2><p>★ 4,8 · Zem KORA 214</p></div><button data-toast="Appel sécurisé ouvert.">☎</button></section>
      <div class="ride-mini-map parcel-delivery-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><i class="ride-map-pin pin-center"></i><i class="ride-car-dot dot-one">K</i></div>
      <div class="card">${miniTimeline(["Livreur en route vers l’agence","Retrait du colis avec scan sécurisé","Livraison au destinataire contre code"])}</div>
      <button class="primary" style="margin-top:13px" data-goto="parcel-delivery-live">Suivre la livraison</button>`, { active: "parcel" }),

    "parcel-delivery-live": () => screen(`
      ${header("Livraison en cours", "Arrivée dans environ 12 min")}
      <section class="ride-full-map parcel-live-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><span class="ride-map-road road-b"></span><i class="ride-map-pin pin-destination"></i><i class="ride-car-dot moving">K</i></section>
      <section class="ride-arrival-sheet"><div class="ride-sheet-handle"></div><div class="row"><div><small class="muted">Idriss livre votre colis</small><div class="amount">12 minutes</div></div><span class="tag gold">En route</span></div><p>Code de remise destinataire : <strong>K4 0982</strong></p>
      <div class="ride-driver-actions"><button data-toast="Appel sécurisé ouvert.">Appeler</button><button data-goto="parcel-detail">Détails du colis</button></div>
      <button class="primary" style="margin-top:10px" data-goto="parcel-delivered-final">Simuler la livraison terminée</button></section>`, { noNav: true }),

    "parcel-recipient-notification": () => screen(`
      ${header("Notification destinataire", "Serge ADANDÉ")}
      <div class="notification-preview"><span>K</span><div><small>KORA EXPRESS</small><strong>Un colis vous est destiné</strong><p>Aïcha HOUNKPÈ vous envoie un colis de Cotonou vers Parakou. Référence : KC-20260729-51842.</p></div></div>
      <div class="card">${detailRows([["Email","serge.adande@example.com · envoyé"],["WhatsApp","+229 •• •• 12 08 · envoyé"],["Prochaine alerte","Départ du colis"],["Code de retrait","Envoyé uniquement à l’arrivée"]])}</div>
      <button class="primary" style="margin-top:14px" data-toast="Notification renvoyée au destinataire.">Renvoyer la notification</button>`, { active: "parcel" }),

    "parcel-delivered-final": () => screen(`
      ${header("Livraison terminée", "KC-20260729-51842")}
      <section class="parcel-delivered-proof"><span>✓</span><h2>Colis remis à Serge ADANDÉ</h2><p>Aujourd’hui · 17:32 · Code K4 0982 validé</p></section>
      <div class="card">${detailRows([["Livreur","Idriss M. · Zem KORA 214"],["Adresse","Zongo, Parakou"],["Preuve","Code destinataire validé"],["Statut","Livré"]])}</div>
      <div class="recipient-notified"><i>✓</i><span><strong>Expéditeur et destinataire informés</strong><small>Confirmation envoyée par email et WhatsApp</small></span><button data-toast="Confirmations envoyées.">Détails</button></div>
      <button class="primary" style="margin-top:13px" data-goto="parcel-history">Voir mes colis</button>`, { active: "parcel" }),

    "ride-home": () => screen(`
      ${header("Où allons-nous ?", "KORA RIDE", { action: `<button class="icon-button" data-goto="ride-history" aria-label="Historique">↗</button>` })}
      <section class="ride-map-hero">
        <div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><span class="ride-map-road road-b"></span>
        <i class="ride-map-pin pin-user"></i><i class="ride-car-dot dot-one">K</i><i class="ride-car-dot dot-two">K</i>
        <div class="ride-live-pill"><i></i><span><strong>12 chauffeurs proches</strong><small>Prise en charge dès 4 min</small></span></div>
      </section>
      <section class="ride-search-card">
        <div class="ride-search-title"><span>Commander une course</span><b>Maintenant</b></div>
        <button class="ride-address" data-goto="ride-pickup"><i class="ride-origin-dot"></i><span><small>Départ</small><strong>Ma position actuelle</strong><em>Haie Vive, Cotonou</em></span><b>›</b></button>
        <div class="ride-address-line"></div>
        <button class="ride-address" data-goto="ride-destination"><i class="ride-destination-dot"></i><span><small>Destination</small><strong>Où allez-vous ?</strong><em>Saisissez une adresse ou un lieu</em></span><b>›</b></button>
      </section>
      <div class="ride-section-head"><h3>Destinations récentes</h3><button data-goto="ride-history">Historique</button></div>
      <div class="ride-recent-grid">
        <button data-goto="ride-vehicle"><i class="line-location"></i><span><strong>Domicile</strong><small>Fidjrossè · 18 min</small></span></button>
        <button data-goto="ride-vehicle"><i class="line-location"></i><span><strong>Bureau</strong><small>Ganhi · 12 min</small></span></button>
      </div>
      <div class="ride-safety-note"><i class="line-shield"></i><span><strong>Votre sécurité d’abord</strong><small>Chauffeurs vérifiés · Course suivie · Assistance KORA</small></span></div>`, { active: "home" }),

    "ride-pickup": () => screen(`
      ${header("Point de départ", "KORA RIDE")}
      <label class="directory-search"><span>⌕</span><input value="Haie Vive, Cotonou" autofocus></label>
      <div class="ride-mini-map"><div class="ride-map-grid"></div><i class="ride-map-pin pin-center"></i><span>Déplacez la carte pour ajuster</span></div>
      <button class="menu-item" data-goto="ride-destination"><span class="menu-icon">⌖</span><span><strong>Utiliser ma position</strong><small>Rue 1217, Haie Vive</small></span><span class="chevron">›</span></button>
      <button class="primary" style="margin-top:15px" data-goto="ride-destination">Confirmer le départ</button>
      <button class="link-button" style="width:100%;margin-top:9px" data-toast="Saisie manuelle activée en remplacement du GPS.">Saisir l’adresse manuellement</button>`, { noNav: true }),

    "ride-destination": () => screen(`
      ${header("Votre destination", "KORA RIDE")}
      <label class="directory-search"><span>⌕</span><input placeholder="Adresse, quartier ou établissement" autofocus></label>
      <div class="section-title"><h3>Suggestions</h3></div>
      <div class="menu-list">
        ${menuGoto("⌖","Aéroport de Cotonou","Rue 1250 · 4,2 km","ride-vehicle")}
        ${menuGoto("○","Place de l’Amazone","Boulevard de la Marina · 2,8 km","ride-vehicle")}
        ${menuGoto("○","Stade de l’Amitié","Kouhounou · 7,6 km","ride-vehicle")}
        ${menuGoto("○","Fidjrossè Plage","Route des Pêches · 6,4 km","ride-vehicle")}
      </div>`, { noNav: true }),

    "ride-vehicle": () => screen(`
      ${header("Choisissez votre véhicule", "Haie Vive → Aéroport")}
      <div class="ride-route-summary"><span>4,2 km</span><i></i><span>≈ 14 min</span><b>Prix estimé</b></div>
      <div class="ride-vehicle-list">
        ${rideVehicleCard("KORA MOTO","1 passager","Arrive dans 3 min",1800,"moto")}
        ${rideVehicleCard("KORA KEKE","Jusqu’à 3 passagers","Arrive dans 5 min",2200,"keke")}
        ${rideVehicleCard("KORA GO","Confort · 4 passagers","Arrive dans 4 min",2850,"car")}
        ${rideVehicleCard("KORA COMFORT","Premium · 4 passagers","Arrive dans 7 min",3900,"comfort")}
        ${rideVehicleCard("KORA XL","Groupe · 6 à 7 places","Arrive dans 8 min",5200,"xl")}
      </div>
      <div class="ride-demand-note"><i>↗</i><span><strong>Tarif normal</strong><small>Prix figé dès votre confirmation.</small></span></div>
      <button class="primary ride-confirm-vehicle" data-goto="ride-summary"><span>Choisir ${state.rideVehicle}</span><b>${money(state.ridePrice)}</b></button>`, { noNav: true }),

    "ride-summary": () => screen(`
      ${header("Confirmez votre course", state.rideVehicle)}
      <section class="ride-summary-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><i class="ride-map-pin pin-user"></i><i class="ride-map-pin pin-destination"></i></section>
      <section class="ride-trip-card">
        <div><i class="ride-origin-dot"></i><span><small>Départ</small><strong>Haie Vive, Cotonou</strong></span><button data-goto="ride-pickup">Modifier</button></div>
        <i class="ride-trip-rail"></i>
        <div><i class="ride-destination-dot"></i><span><small>Destination</small><strong>Aéroport de Cotonou</strong></span><button data-goto="ride-destination">Modifier</button></div>
      </section>
      <section class="ride-fare-card"><div><span>${state.rideVehicle}</span><small>4,2 km · environ 14 min</small></div><strong>${money(state.ridePrice)}</strong></section>
      <button class="primary" style="margin-top:13px" data-goto="ride-searching">Confirmer et rechercher</button>
      <p class="ride-fare-disclaimer">Le tarif est désormais figé. Il ne changera pas pendant la course.</p>`, { noNav: true }),

    "ride-searching": () => screen(`
      ${header("", "")}
      <section class="ride-searching-scene"><div class="ride-radar"><i></i><i></i><i></i><b>K</b></div><div class="state-copy"><h2>Nous cherchons votre chauffeur</h2><p>Des partenaires KORA proches reçoivent votre demande.</p></div></section>
      <div class="ride-search-progress"><i></i></div>
      <div class="card mist"><div class="row"><span class="muted">Zone de recherche</span><strong>5 km autour de vous</strong></div><div class="divider"></div><div class="row"><span class="muted">Véhicule</span><strong>${state.rideVehicle}</strong></div></div>
      <button class="primary" style="margin-top:14px" data-goto="ride-attempts">Suivre la recherche</button>
      <button class="ghost" style="margin-top:9px" data-goto="ride-no-driver">Voir l’état indisponible</button>
      <button class="link-button" style="width:100%;margin-top:9px" data-goto="ride-home">Annuler la demande</button>`, { noNav: true }),

    "ride-no-driver": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Aucun chauffeur disponible actuellement", text: "Aucun partenaire n’a accepté dans un rayon de 5 km. Réessayez dans quelques minutes ou demandez à être alerté.", symbol: "⌕", primary: "ride-searching", primaryLabel: "Relancer la recherche", secondary: "ride-home", secondaryLabel: "Modifier la course" })}
      <button class="ghost" style="margin-top:11px" data-toast="Nous vous alerterons dès qu’un chauffeur sera disponible.">M’alerter quand un chauffeur est disponible</button>`, { noNav: true }),

    "ride-attempts": () => screen(`
      ${header("Recherche en cours", "Tentative 1 sur 3")}
      <div class="ride-attempt-timer"><span>15</span><small>secondes pour répondre</small><i></i></div>
      <div class="card">${miniTimeline(["Karim · demande envoyée","Chauffeur suivant si refus ou délai dépassé","Maximum 3 tentatives automatiques"])}</div>
      <div class="ride-search-progress"><i></i></div>
      <button class="primary" style="margin-top:14px" data-goto="ride-driver-found">Simuler l’acceptation</button>
      <button class="ghost" style="margin-top:9px" data-goto="ride-no-driver">Simuler 3 tentatives échouées</button>`, { noNav: true }),

    "ride-driver-found": () => screen(`
      ${header("Chauffeur trouvé", "Arrive dans environ 4 min")}
      <section class="ride-driver-card">
        <div class="driver-avatar">KM<span>GOLD</span></div>
        <div><small>Votre chauffeur</small><h2>Karim MENSAH</h2><p>★ 4,9 · 312 courses</p></div>
        <button data-toast="Appel sécurisé dans l’application.">☎</button>
      </section>
      <section class="ride-car-card"><i class="ride-car-visual">K</i><div><small>TOYOTA COROLLA · BLANCHE</small><strong>AB 4589 RB</strong></div><span>KORA GO</span></section>
      <div class="ride-mini-map driver-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><i class="ride-map-pin pin-center"></i><i class="ride-car-dot dot-one">K</i></div>
      <div class="ride-driver-actions"><button data-goto="ride-chat">Message</button><button data-goto="ride-arriving">Suivre l’arrivée</button></div>
      <button class="link-button" style="width:100%;margin-top:10px" data-toast="Demande d’annulation ouverte avec les conditions applicables.">Annuler la course</button>`, { noNav: true }),

    "ride-chat": () => screen(`
      ${header("Karim MENSAH", "En ligne · arrive dans 4 min", { action: `<button class="icon-button" data-toast="Appel sécurisé dans l’application." aria-label="Appeler Karim">☎</button>` })}
      <section class="ride-chat-driver">
        <div class="driver-avatar">KM<span>GOLD</span></div>
        <div><strong>Votre chauffeur KORA</strong><small>Toyota Corolla · AB 4589 RB</small></div>
        <i></i>
      </section>
      <div class="ride-chat-safety"><i>⌁</i><span>Votre numéro reste confidentiel. Les échanges sont sécurisés par KORA.</span></div>
      <section class="ride-chat-thread" aria-label="Conversation avec Karim">
        <div class="ride-chat-time">Aujourd’hui · 14:26</div>
        ${state.rideMessages.map(message => `<div class="ride-message ${message.from === "user" ? "sent" : "received"}"><p>${message.text}</p><small>${message.from === "user" ? "14:28 · Lu" : "14:27"}</small></div>`).join("")}
      </section>
      <div class="ride-quick-messages">
        <button data-quick-message="Je vous attends ici.">Je vous attends ici</button>
        <button data-quick-message="J’arrive tout de suite.">J’arrive</button>
        <button data-quick-message="Pouvez-vous m’appeler ?">Appelez-moi</button>
      </div>
      <form class="ride-chat-compose" data-message-form>
        <input type="text" class="field-control" data-message-input aria-label="Votre message" placeholder="Écrire un message…" autocomplete="off">
        <button type="submit" data-message-send aria-label="Envoyer le message">➤</button>
      </form>`, { noNav: true }),

    "ride-arriving": () => screen(`
      ${header("Votre chauffeur approche", "2 min · 650 m")}
      <section class="ride-full-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><span class="ride-map-road road-b"></span><i class="ride-map-pin pin-user"></i><i class="ride-car-dot moving">K</i></section>
      <section class="ride-arrival-sheet"><div class="ride-sheet-handle"></div><div class="row"><div><small class="muted">Karim arrive dans</small><div class="amount">2 minutes</div></div><span class="tag gold">AB 4589 RB</span></div><p>Attendez dans un endroit sûr et vérifiez l’immatriculation.</p>
      <div class="ride-driver-actions"><button data-toast="Appel sécurisé ouvert.">Appeler</button><button data-goto="ride-chat">Écrire</button></div>
      <button class="primary" style="margin-top:11px" data-goto="ride-in-progress">Monter à bord · Démarrer la course</button></section>`, { noNav: true }),

    "ride-in-progress": () => rideProgressScreen(false),
    "ride-offline": () => rideProgressScreen(true),

    "ride-payment": () => screen(`
      ${header("Régler la course", "Course terminée")}
      <section class="ride-final-fare"><small>Montant final</small><strong>${money(state.ridePrice)}</strong><span>4,3 km · 16 min</span></section>
      <div class="section-title"><h3>Moyen de paiement</h3></div>
      <button class="delivery-option selected" data-goto="ride-cash-confirm"><span class="transaction-icon">F</span><span><strong>Espèces</strong><small>À remettre directement au chauffeur</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="ride-mobile-money"><span class="transaction-icon">M</span><span><strong>Mobile Money</strong><small>MTN · Moov · Celtiis · Wave</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="ride-wallet-payment"><span class="transaction-icon">K</span><span><strong>KORA Wallet</strong><small>Solde : 18 750 FCFA</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="card-payment-ride"><span class="transaction-icon card-symbol">▭</span><span><strong>Carte bancaire</strong><small>Visa · Mastercard</small></span><b>›</b></button>
      <div class="card mist" style="margin-top:11px"><p style="margin:0">Vous gagnez 3 % en points KORA après confirmation du paiement.</p></div>`, { noNav: true }),

    "ride-cash-confirm": () => screen(`
      ${header("Paiement en espèces", `${money(state.ridePrice)} à remettre`)}
      ${stateView({ title: "Avez-vous payé le chauffeur ?", text: "Confirmez uniquement après avoir remis le montant. Sans action, les points seront crédités automatiquement dans cinq minutes.", symbol: "F", primary: "ride-complete", primaryLabel: "Oui, paiement effectué", secondary: "contact", secondaryLabel: "Signaler un problème" })}`, { noNav: true }),

    "ride-mobile-money": () => screen(`
      ${header("Mobile Money", `Course · ${money(state.ridePrice)}`)}
      <label class="field"><span>Choisissez votre opérateur</span><select class="field-control" required><option value="" selected disabled>Sélectionner un opérateur</option><option>MTN MoMo</option><option>Moov Money</option><option>Celtiis Cash</option><option>Wave</option></select></label>
      <label class="field"><span>Numéro à débiter</span><input class="field-control" value="+229 01 97 42 18 60"></label>
      <div class="card mist"><p style="margin:0">Le tarif a été figé à la confirmation de la course. Aucun supplément ne sera ajouté.</p></div>
      <button class="primary" style="margin-top:17px" data-goto="ride-payment-pending">Payer ${money(state.ridePrice)}</button>
      <button class="ghost" style="margin-top:9px" data-goto="ride-payment">Changer de moyen</button>`, { noNav: true }),

    "ride-wallet-payment": () => screen(`
      ${header("KORA Wallet", `Course · ${money(state.ridePrice)}`)}
      <div class="wallet-card"><small>Solde disponible</small><div class="balance">18 750 <small>FCFA</small></div><div class="wallet-foot"><span>2 480 points</span><span>Actif</span></div></div>
      <div class="card" style="margin-top:13px"><div class="row"><span class="muted">Course KORA GO</span><strong>${money(state.ridePrice)}</strong></div><div class="divider"></div><div class="row"><span class="muted">Solde après paiement</span><strong>${money(18750-state.ridePrice)}</strong></div></div>
      <button class="primary" style="margin-top:15px" data-goto="ride-complete">Confirmer avec le Wallet</button>
      <button class="ghost" style="margin-top:9px" data-goto="ride-payment">Changer de moyen</button>`, { noNav: true }),

    "ride-payment-pending": () => screen(`
      ${header("", "")}${stateView({title:"Paiement Ride en cours",text:"Nous attendons la confirmation Mobile Money. Gardez cette page ouverte et ne relancez pas le paiement.",symbol:"…",primary:"ride-complete",primaryLabel:"Simuler la confirmation",secondary:"ride-payment-refused",secondaryLabel:"Voir l’état refusé"})}`, { noNav: true }),

    "ride-payment-refused": () => screen(`
      ${header("", "")}${stateView({title:"Paiement refusé",text:"La course reste à régler. Choisissez un autre moyen ou payez le chauffeur en espèces.",symbol:"!",primary:"ride-payment",primaryLabel:"Changer de moyen",secondary:"ride-cash-confirm",secondaryLabel:"Payer en espèces"})}`, { noNav: true }),

    "ride-share": () => screen(`
      ${header("Partager mon trajet", "Suivi en direct")}
      <div class="share-live-card"><span class="state-icon">↗</span><h2>Aïcha voyage avec KORA</h2><p>Le lien montre la position en direct, le chauffeur, le véhicule et l’heure d’arrivée estimée.</p><div><small>Lien sécurisé valable jusqu’à l’arrivée</small><strong>kora.app/live/KR-7264</strong></div></div>
      <button class="primary" style="margin-top:14px" data-toast="Lien de suivi préparé dans WhatsApp.">Partager sur WhatsApp</button>
      <button class="ghost" style="margin-top:9px" data-toast="Lien de suivi copié.">Copier le lien</button>
      <div class="card mist" style="margin-top:10px"><p style="margin:0">Le partage s’arrête automatiquement à la fin de la course.</p></div>`, { noNav: true }),

    "ride-sos": () => screen(`
      ${header("Assistance d’urgence", "Course KR-20260729-7264")}
      <div class="sos-hero"><span>SOS</span><h2>Votre sécurité d’abord</h2><p>Votre position, le nom du chauffeur et l’immatriculation sont prêts à être envoyés.</p></div>
      <button class="delivery-option" data-toast="Position envoyée à Aminata H."><span class="transaction-icon">A</span><span><strong>Aminata H.</strong><small>Contact d’urgence · +229 •• •• 42 10</small></span><b>Envoyer</b></button>
      <button class="delivery-option" data-toast="Appel vers les services d’urgence préparé."><span class="transaction-icon">☎</span><span><strong>Services d’urgence</strong><small>Appel prioritaire</small></span><b>Appeler</b></button>
      <button class="danger-button" style="width:100%;margin-top:12px" data-toast="Alerte SOS transmise à KORA avec votre géolocalisation.">Déclencher l’alerte KORA</button>
      <button class="link-button" style="width:100%;margin-top:10px" data-back>Annuler et revenir à la course</button>`, { noNav: true }),

    "ride-complete": () => screen(`
      ${header("", "")}
      ${stateView({ title: "Vous êtes arrivé !", text: "Merci d’avoir choisi KORA RIDE. 86 points viennent d’être ajoutés à votre Wallet.", symbol: "✓", primary: "ride-rating", primaryLabel: "Noter mon chauffeur", secondary: "home", secondaryLabel: "Retour à l’accueil" })}
      <section class="ride-receipt"><div><span>Haie Vive</span><i></i><span>Aéroport</span></div><p>16 min · 4,3 km · ${money(state.ridePrice)}</p></section>`, { noNav: true }),

    "ride-rating": () => screen(`
      ${header("Comment s’est passée la course ?", "Votre avis compte")}
      <div class="driver-avatar rating-avatar">KM<span>GOLD</span></div><h2 class="ride-rating-name">Karim MENSAH</h2>
      <div class="ride-stars">${[1,2,3,4,5].map(n=>`<button data-toast="${n} étoile${n>1?"s":""} sélectionnée${n>1?"s":""}.">★</button>`).join("")}</div>
      <div class="ride-rating-tags"><button data-toast="Conduite prudente sélectionnée.">Conduite prudente</button><button data-toast="Véhicule propre sélectionné.">Véhicule propre</button><button data-toast="Chauffeur courtois sélectionné.">Courtois</button><button data-toast="Ponctuel sélectionné.">Ponctuel</button></div>
      <label class="field"><span>Commentaire <small class="muted">(optionnel)</small></span><textarea class="field-control" rows="3" placeholder="Partagez votre expérience"></textarea></label>
      <button class="primary" data-goto="home">Envoyer mon avis</button>`, { noNav: true }),

    "ride-history": () => screen(`
      ${header("Mes courses", "KORA RIDE")}
      <div class="parcel-filter-tabs"><button class="active" data-toast="Toutes les courses affichées.">Toutes</button><button data-toast="Courses terminées affichées.">Terminées</button><button data-toast="Courses annulées affichées.">Annulées</button></div>
      ${rideHistoryCard("Aujourd’hui · 14:22","Haie Vive","Aéroport de Cotonou","2 850 F","ride-complete")}
      ${rideHistoryCard("24 juillet · 18:08","Ganhi","Fidjrossè","3 200 F","ride-complete")}
      ${rideHistoryCard("19 juillet · 09:41","Akpakpa","Place de l’Amazone","2 100 F","ride-complete")}
      <button class="primary" style="margin-top:13px" data-goto="ride-home">Commander une nouvelle course</button>`, { active: "home" }),

    wallet: () => screen(`
      ${header("Mon Wallet", "Crédits & fidélité", { back: false, action: `<button class="icon-button" data-goto="wallet-history">↗</button>` })}
      <div class="wallet-card"><small>Solde Cash & crédits</small><div class="balance">18 750 <small>FCFA</small></div><div class="wallet-foot"><span>Remboursements + recharges</span><span>Disponible</span></div></div>
      <button class="wallet-points-strip" data-goto="wallet-expiry"><span class="transaction-icon">★</span><span><small>Points fidélité</small><strong>2 480 points · valeur 2 480 FCFA</strong></span><b>›</b></button>
      <div class="wallet-action-grid">
        <button data-goto="wallet-recharge"><span>＋</span><strong>Recharger</strong></button>
        <button data-goto="wallet-services"><span>⌁</span><strong>Utiliser</strong></button>
        <button data-goto="wallet-bonuses"><span>★</span><strong>Bonus</strong></button>
        <button data-goto="wallet-history"><span>↗</span><strong>Historique</strong></button>
      </div>
      <div class="section-title"><h3>Derniers mouvements</h3><button data-goto="wallet-history">Voir tout</button></div>
      <div class="card">
        ${transaction("+","Remboursement billet","KE-…78342","+ 9 375 F",true)}
        ${transaction("★","Points KORA BUS","Billet Cotonou → Parakou","+ 375 pts",true)}
        ${transaction("−","Paiement billet","KE-…51802","− 6 000 F")}
      </div>`, { active: "wallet" }),

    "wallet-history": () => screen(`
      ${header("Mouvements", "KORA Wallet")}
      <div class="row" style="justify-content:flex-start;margin-bottom:13px"><button class="tag gold" data-toast="Tous les mouvements affichés.">Tous</button><button class="tag outline" data-toast="Filtre crédits appliqué.">Crédits</button><button class="tag outline" data-toast="Filtre utilisations appliqué.">Utilisations</button></div>
      <div class="card">
        ${transaction("+","Remboursement billet","28 juil. · 11:42","+ 9 375 F",true,"wallet-detail")}
        ${transaction("★","Points KORA BUS","22 juil. · 18:13","+ 375 pts",true)}
        ${transaction("−","Paiement billet","18 juil. · 09:26","− 6 000 F")}
        ${transaction("★","Bonus anniversaire","03 juil. · 08:00","+ 100 pts",true)}
        ${transaction("−","Paiement partiel","16 juin · 14:08","− 3 200 F")}
      </div>`, { active: "wallet" }),

    "wallet-detail": () => screen(`
      ${header("Détail du mouvement", "28 juillet · 11:42")}
      <div class="state-visual" style="margin-top:12px"><div class="state-icon">+</div></div>
      <div class="state-copy"><span class="eyebrow">Crédit reçu</span><h2>+ 9 375 FCFA</h2><p>Remboursement partiel après annulation du billet.</p></div>
      <div class="card">
        <div class="row"><span class="muted">Référence</span><strong>KE-20260804-78342</strong></div><div class="divider"></div>
        <div class="row"><span class="muted">Solde avant</span><strong>9 375 F</strong></div><div class="divider"></div>
        <div class="row"><span class="muted">Solde après</span><strong>18 750 F</strong></div>
      </div>
      <button class="primary" style="margin-top:15px" data-goto="bus-search">Utiliser pour un voyage</button>`, { active: "wallet" }),

    "wallet-use": () => screen(`
      ${header("Utiliser mes points", "2 480 points disponibles")}
      <div class="card gold"><div class="row"><div><span class="tiny">Valeur disponible</span><div class="amount">2 480 FCFA</div></div><span style="font-size:27px">★</span></div></div>
      <div class="section-title"><h3>Comment ça marche ?</h3></div>
      <div class="card">${miniTimeline(["Choisissez un service KORA","Sélectionnez Wallet au paiement","Utilisez tout ou partie de vos points"])}</div>
      <div class="card mist" style="margin-top:12px"><p style="margin:0">Minimum : 100 points. Les points ne sont ni retirables en espèces ni transférables.</p></div>
      <button class="primary" style="margin-top:15px" data-goto="bus-search">Réserver avec mes points</button>`, { active: "wallet" }),

    "wallet-expiry": () => screen(`
      ${header("Points à utiliser", "Expiration prochaine")}
      ${stateView({ title: "2 480 points expirent dans 30 jours", text: "Toute nouvelle transaction KORA prolonge l’ensemble de vos points de six mois.", symbol: "★", primary: "bus-search", primaryLabel: "Réserver un voyage", secondary: "wallet", secondaryLabel: "Retour au Wallet" })}
      <div class="card gold" style="margin-top:15px"><p style="margin:0;color:var(--kora)">Suggestion : utilisez vos points sur un billet Cotonou → Porto-Novo.</p></div>`, { active: "wallet" }),

    "wallet-recharge": () => screen(`
      ${header("Recharger mon Wallet", "Solde Cash")}
      <div class="wallet-card compact-wallet"><small>Solde actuel</small><div class="balance">18 750 <small>FCFA</small></div></div>
      <label class="field"><span>Montant de la recharge</span><input class="field-control wallet-big-input" value="10 000 FCFA" inputmode="numeric"></label>
      <div class="wallet-presets"><button data-toast="Montant 5 000 FCFA sélectionné.">5 000</button><button data-toast="Montant 10 000 FCFA sélectionné.">10 000</button><button data-toast="Montant 25 000 FCFA sélectionné.">25 000</button></div>
      <div class="card mist"><p style="margin:0">Une recharge n’accorde aucun point fidélité. Le solde Cash peut payer tous les services KORA.</p></div>
      <button class="primary" style="margin-top:14px" data-goto="wallet-recharge-method">Continuer</button>`, { active: "wallet" }),

    "wallet-recharge-method": () => screen(`
      ${header("Moyen de recharge", "10 000 FCFA")}
      <button class="delivery-option" data-goto="mobile-money"><span class="transaction-icon">M</span><span><strong>Mobile Money</strong><small>Choisissez ensuite votre opérateur</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="card-payment-wallet"><span class="transaction-icon card-symbol">▭</span><span><strong>Carte bancaire</strong><small>Visa · Mastercard</small></span><b>›</b></button>
      <button class="link-button" style="width:100%;margin-top:13px" data-goto="wallet-recharge-refused">Voir l’état refusé</button>`, { active: "wallet" }),

    "wallet-recharge-pending": () => screen(`
      ${header("", "")}${stateView({title:"Confirmez la recharge sur votre téléphone",text:"La demande de 10 000 FCFA a été envoyée. Ne partagez jamais votre code Mobile Money.",symbol:"…",primary:"wallet-recharge-success",primaryLabel:"J’ai confirmé le paiement",secondary:"wallet-recharge-method",secondaryLabel:"Changer de moyen"})}`, { noNav: true }),

    "wallet-recharge-refused": () => screen(`
      ${header("", "")}${stateView({title:"Recharge refusée",text:"Le paiement n’a pas été autorisé. Aucun montant n’a été ajouté ni débité par KORA.",symbol:"!",primary:"wallet-recharge-method",primaryLabel:"Essayer un autre moyen",secondary:"wallet",secondaryLabel:"Retour au Wallet"})}`, { noNav: true }),

    "wallet-recharge-success": () => screen(`
      ${header("", "")}${stateView({title:"Recharge confirmée",text:"10 000 FCFA ont été ajoutés à votre solde Cash. Nouveau solde : 28 750 FCFA.",symbol:"✓",primary:"wallet",primaryLabel:"Voir mon Wallet",secondary:"wallet-receipt",secondaryLabel:"Afficher le reçu"})}`, { noNav: true }),

    "wallet-receipt": () => screen(`
      ${header("Reçu de recharge", "29 juillet · 10:18")}
      <div class="wallet-receipt"><span class="state-icon">✓</span><small>Montant reçu</small><strong>10 000 FCFA</strong><p>Référence KWR-290726-10482</p></div>
      <div class="card">${detailRows([["Moyen","MTN MoMo"],["Numéro","+229 •• •• 18 60"],["Frais KORA","0 FCFA"],["Nouveau solde","28 750 FCFA"]])}</div>
      <button class="primary" style="margin-top:14px" data-toast="Reçu partagé sur WhatsApp.">Partager le reçu</button>`, { active: "wallet" }),

    "wallet-bonuses": () => screen(`
      ${header("Mes bonus KORA", "Fidélité")}
      <div class="loyalty-rate-card"><span>3 %</span><div><strong>de vos dépenses KORA en points</strong><small>Taux de lancement · passera à 2 % après le 6e mois selon le CDC</small></div></div>
      <div class="card gold"><div class="row"><div><small>Bonus obtenus</small><div class="amount">650 points</div></div><span class="transaction-icon">★</span></div></div>
      <div class="bonus-list">
        ${bonusRow("✓","Première transaction","+500 pts","Obtenu")}
        ${bonusRow("✓","Anniversaire du compte","+100 pts","Obtenu")}
        ${bonusRow("4/5","5e utilisation du mois","+50 pts","Encore 1")}
        ${bonusRow("↗","Parrainage actif","+200 pts","Inviter")}
      </div>
      <button class="primary" style="margin-top:14px" data-goto="wallet-referral">Inviter un proche</button>`, { active: "wallet" }),

    "wallet-referral": () => screen(`
      ${header("Invitez vos proches", "200 points par filleul actif")}
      <div class="referral-hero"><span>K</span><h2>Voyager ensemble rapporte plus</h2><p>Votre bonus est crédité après la première transaction validée de votre filleul.</p><div><small>Votre code</small><strong>KORA-AICHA26</strong></div></div>
      <button class="primary" style="margin-top:14px" data-toast="Invitation préparée dans WhatsApp.">Partager sur WhatsApp</button>
      <button class="ghost" style="margin-top:9px" data-toast="Code KORA-AICHA26 copié.">Copier le code</button>
      <button class="link-button" style="width:100%;margin-top:10px" data-goto="wallet-referral-status">Suivre mes invitations</button>`, { active: "wallet" }),

    "wallet-referral-status": () => screen(`
      ${header("Mes filleuls", "Parrainage")}
      <div class="card">${transaction("✓","Nadia A.","Première transaction effectuée","+ 200 pts",true)}${transaction("…","Serge K.","Inscrit · transaction attendue","En attente")}${transaction("↗","Mariam D.","Invitation envoyée","À relancer")}</div>
      <button class="primary" style="margin-top:14px" data-toast="Invitation préparée dans WhatsApp.">Inviter une autre personne</button>`, { active: "wallet" }),

    "wallet-services": () => screen(`
      ${header("Utiliser mon Wallet", "Tous les services KORA")}
      <div class="wallet-service-grid">
        ${serviceChoice("Bus","Billets interurbains","bus-search")}
        ${serviceChoice("Ride","Courses urbaines","ride-home")}
        ${serviceChoice("Colis","Envois et livraisons","parcel-create")}
        ${serviceChoice("Premium","Abonnement et avantages","premium-home")}
      </div>
      <div class="card mist"><p style="margin:0">Utilisez vos points dès 100 points, seuls ou avec votre solde Cash et Mobile Money.</p></div>
      <button class="link-button" style="width:100%;margin-top:10px" data-goto="wallet-rules">Voir toutes les règles</button>`, { active: "wallet" }),

    "wallet-combined": () => screen(`
      ${header("Répartir le paiement", "Paiement combiné")}
      <label class="range-field"><span>Points fidélité</span><strong>1 000 points</strong><input type="range" min="0" max="2480" step="100" value="1000" data-points-range></label>
      <div class="wallet-amount-control"><button data-wallet-amount="-1000">−</button><div><small>Solde Cash Wallet</small><strong>${money(state.walletUse)}</strong></div><button data-wallet-amount="1000">+</button></div>
      <div class="card" style="margin-top:12px">${detailRows([["Total","12 500 FCFA"],["Points","− 1 000 FCFA"],["Solde Cash",`− ${money(state.walletUse)}`],["Complément Mobile Money",money(Math.max(0,11500-state.walletUse))]])}</div>
      <button class="primary" style="margin-top:14px" data-goto="mobile-money">Continuer avec Mobile Money</button>
      <button class="ghost" style="margin-top:9px" data-goto="wallet-insufficient">Voir l’état solde insuffisant</button>`, { active: "wallet" }),

    "wallet-insufficient": () => screen(`
      ${header("", "")}${stateView({title:"Solde insuffisant",text:"Votre Wallet et vos points ne couvrent pas la totalité. Complétez par Mobile Money ou rechargez votre solde Cash.",symbol:"!",primary:"wallet-recharge",primaryLabel:"Recharger mon Wallet",secondary:"wallet-combined",secondaryLabel:"Compléter par Mobile Money"})}`, { noNav: true }),

    "wallet-rules": () => screen(`
      ${header("Règles du Wallet", "Transparence")}
      <div class="menu-list">${menuItem("1","1 point = 1 FCFA","Utilisable dès 100 points","Valeur confirmée.")}${menuItem("×","Aucun retrait en espèces","Les points restent dans KORA","Restriction confirmée.")}${menuItem("⇄","Aucun transfert entre comptes","Vos avantages sont personnels","Restriction confirmée.")}${menuItem("＋","Recharge sans bonus","Une recharge Cash ne génère aucun point","Règle confirmée.")}</div>
      <div class="card gold"><p style="margin:0;color:var(--kora)">Toute transaction KORA prolonge globalement vos points de six mois. Alertes automatiques à J−30, J−7 et J−1.</p></div>`, { active: "wallet" }),

    "premium-home": () => screen(`
      ${header("KORA Premium", "Voyagez avec plus d’avantages")}
      <section class="premium-hero"><span class="premium-k">K</span><small>KORA PREMIUM</small><h2>Plus de confort.<br>Plus de liberté.</h2><p>Des avantages concrets sur vos voyages, courses et envois KORA.</p><button data-goto="premium-plans">Découvrir les offres</button></section>
      <div class="section-title"><h3>Vos avantages</h3><button data-goto="premium-benefits">Voir tout</button></div>
      <div class="premium-benefit-grid"><div><i>▣</i><strong>Priorité Bus</strong><small>Accès anticipé aux meilleures places</small></div><div><i>⌁</i><strong>Ride prioritaire</strong><small>Recherche optimisée aux heures chargées</small></div><div><i>◇</i><strong>Colis avantage</strong><small>Réduction sur les livraisons locales</small></div><div><i>★</i><strong>Bonus points</strong><small>Avantages fidélité exclusifs</small></div></div>
      <button class="primary" style="margin-top:14px" data-goto="premium-plans">Choisir mon abonnement</button>`, { active: "wallet" }),

    "premium-plans": () => screen(`
      ${header("Choisir mon offre", "KORA Premium")}
      <button class="premium-plan selected" data-goto="premium-checkout"><span><small>Mensuel</small><strong>2 500 FCFA</strong><em>/ mois</em></span><b>Flexible</b><p>Sans engagement · renouvellement mensuel</p></button>
      <button class="premium-plan" data-goto="premium-checkout"><span><small>Annuel</small><strong>25 000 FCFA</strong><em>/ an</em></span><b>2 mois offerts</b><p>Le meilleur tarif pour les voyageurs réguliers</p></button>
      <div class="card">${miniTimeline(["Priorité sur les services éligibles","Réductions affichées avant paiement","Résiliation depuis votre profil"])}</div>
      <button class="link-button" style="width:100%;margin-top:9px" data-goto="premium-benefits">Comparer tous les avantages</button>`, { active: "wallet" }),

    "premium-checkout": () => screen(`
      ${header("Activer KORA Premium", "Offre mensuelle")}
      <div class="premium-order"><span class="premium-k">K</span><div><small>Abonnement mensuel</small><strong>2 500 FCFA</strong><p>Renouvellement le 29 de chaque mois</p></div></div>
      <div class="section-title"><h3>Moyen de paiement</h3></div>
      <button class="delivery-option selected" data-goto="premium-confirmed"><span class="transaction-icon">★</span><span><strong>Points fidélité</strong><small>2 480 points disponibles · complément 20 F</small></span><b>✓</b></button>
      <button class="delivery-option" data-goto="premium-confirmed"><span class="transaction-icon">K</span><span><strong>KORA Wallet</strong><small>Solde Cash : 18 750 FCFA</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="mobile-money"><span class="transaction-icon">M</span><span><strong>Mobile Money</strong><small>Paiement et renouvellement sécurisés</small></span><b>›</b></button>
      <button class="delivery-option" data-goto="card-payment-premium"><span class="transaction-icon card-symbol">▭</span><span><strong>Carte bancaire</strong><small>Visa · Mastercard</small></span><b>›</b></button>
      <button class="primary" style="margin-top:13px" data-goto="premium-confirmed">Payer et activer Premium</button>`, { active: "wallet" }),

    "premium-confirmed": () => screen(`
      ${header("", "")}${stateView({title:"Bienvenue dans KORA Premium",text:"Votre abonnement est actif jusqu’au 29 août 2026. Vos avantages s’appliquent immédiatement.",symbol:"★",primary:"premium-benefits",primaryLabel:"Voir mes avantages",secondary:"home",secondaryLabel:"Retour à l’accueil"})}
      <button class="link-button" style="width:100%;margin-top:10px" data-goto="premium-manage">Gérer mon abonnement</button>`, { noNav: true }),

    "premium-benefits": () => screen(`
      ${header("Mes avantages Premium", "Actifs jusqu’au 29 août")}
      <div class="premium-status"><span>★</span><div><small>Statut</small><strong>Membre Premium actif</strong></div><b>Actif</b></div>
      <div class="menu-list">${menuItem("▣","KORA Bus","Accès prioritaire aux sièges et offres membres","Avantage Bus actif.")}${menuItem("⌁","KORA Ride","Matching prioritaire selon disponibilité","Avantage Ride actif.")}${menuItem("◇","KORA Colis","Réduction sur la livraison locale","Avantage Colis actif.")}${menuItem("★","Fidélité","Bonus ponctuels réservés aux membres","Avantage fidélité actif.")}</div>
      <button class="primary" style="margin-top:14px" data-goto="premium-manage">Gérer l’abonnement</button>`, { active: "wallet" }),

    "premium-manage": () => screen(`
      ${header("Gérer mon abonnement", "KORA Premium")}
      <div class="wallet-card"><small>Offre actuelle</small><div class="balance">Mensuel</div><div class="wallet-foot"><span>2 500 FCFA / mois</span><span>Actif</span></div></div>
      <div class="card">${detailRows([["Prochain renouvellement","29 août 2026"],["Moyen de paiement","KORA Wallet"],["Renouvellement automatique","Activé"],["Avantages utilisés","3 ce mois"]])}</div>
      <button class="ghost" data-toast="Renouvellement automatique désactivé. Les avantages restent actifs jusqu’au 29 août.">Désactiver le renouvellement</button>
      <button class="danger-button" style="width:100%;margin-top:9px" data-toast="Demande de résiliation ouverte avec confirmation.">Résilier à la fin de la période</button>`, { active: "wallet" }),

    profile: () => screen(`
      <div class="profile-premium-header">
        <div class="profile-topline">
          <img src="${logo}" alt="KORA Express">
          <button class="profile-notification" data-goto="notifications" aria-label="Notifications"><span></span></button>
        </div>
        <div class="profile-identity">
          <div class="avatar-premium"><span>AH</span><i>★</i></div>
          <div><small>Bienvenue dans votre espace</small><h2>Aïcha HOUNKPÈ</h2><p>Membre KORA depuis 2026</p></div>
        </div>
        <div class="profile-route" aria-hidden="true"><i></i><b></b><i></i></div>
      </div>
      <div class="profile-balance-strip">
        <button data-goto="wallet"><span>Solde Wallet</span><strong>18 750 F</strong></button>
        <i></i>
        <button data-goto="wallet"><span>Points fidélité</span><strong>2 480 pts</strong></button>
      </div>
      <div class="section-title profile-section-title"><h3>Mon compte</h3></div>
      <div class="profile-action-grid">
        ${profileAction("user","Mes informations","Identité et coordonnées","profile-edit")}
        ${profileAction("shield","Sécurité","PIN, email et appareils","security")}
        ${profileAction("sliders","Préférences","Alertes, langue, thème","preferences")}
      </div>
      <div class="section-title profile-section-title"><h3>Besoin d’aide ?</h3></div>
      <div class="assistance-card">
        <div class="assistance-copy"><span class="assistance-symbol">K</span><div><strong>Nous sommes à vos côtés</strong><p>Une question sur votre voyage, votre paiement ou votre colis ?</p></div></div>
        <div class="assistance-actions">
          <button data-goto="help"><i class="profile-line-icon help-icon"></i><span>Centre d’aide</span></button>
          <button data-goto="contact"><i class="profile-line-icon chat-icon"></i><span>WhatsApp KORA</span></button>
        </div>
      </div>
      <button class="profile-logout" data-goto="welcome">Se déconnecter</button>`, { active: "profile" }),

    "profile-edit": () => screen(`
      ${header("Mes informations", "Profil")}
      <label class="field"><span>Prénom</span><input class="field-control" value="Aïcha"></label>
      <label class="field"><span>Nom</span><input class="field-control" value="HOUNKPÈ"></label>
      <label class="field"><span>Téléphone</span><input class="field-control" value="+229 01 97 42 18 60" disabled></label>
      <label class="field"><span>Email</span><input class="field-control" value="aicha.hounkpe@example.com"></label>
      <label class="field"><span>Date de naissance</span><input class="field-control" value="18 / 04 / 1994"></label>
      <button class="primary" style="margin-top:8px" data-toast="Vos informations ont été enregistrées.">Enregistrer</button>`, { active: "profile" }),

    security: () => screen(`
      ${header("Sécurité et appareils", "Compte")}
      <div class="menu-list">
        ${menuGoto("●","Modifier mon PIN","Dernière modification : jamais","create-pin")}
        ${menuGoto("▯","Autoriser un nouvel appareil","Vérification OTP obligatoire","new-device")}
      </div>
      <div class="section-title"><h3>Appareils connectés</h3></div>
      <div class="card">
        <div class="row start"><span class="transaction-icon">▯</span><div style="flex:1"><strong style="color:var(--kora)">Tecno Spark 20</strong><p>Cotonou · Cet appareil<br>Dernière activité : maintenant</p></div><span class="tag gold">Actif</span></div>
      </div>
      <div class="card"><div class="row start"><span class="transaction-icon">▯</span><div style="flex:1"><strong style="color:var(--kora)">Infinix Note</strong><p>Porto-Novo · 12 juil. 2026</p></div><button class="link-button" data-toast="Session révoquée.">Révoquer</button></div></div>`, { active: "profile" }),

    preferences: () => screen(`
      ${header("Préférences", "Application")}
      <div class="menu-list">
        ${settingRow("◉","Notifications push","Alertes de voyage et billets",true)}
        ${settingRow("W","Messages WhatsApp","Billets et alertes critiques",true)}
        ${settingRow("✉","Emails","Reçus et actualités",false)}
      </div>
      <div class="section-title"><h3>Affichage</h3></div>
      <div class="menu-list">
        ${menuItem("FR","Langue","Français","Sélecteur de langue ouvert.")}
        ${settingRow("◐","Mode sombre","Disponible, non activé",false)}
      </div>`, { active: "profile" }),

    help: () => screen(`
      ${header("Comment pouvons-nous vous aider ?", "Centre d’aide")}
      <label class="directory-search" style="margin-top:0"><span>⌕</span><input placeholder="Rechercher une réponse"></label>
      <div class="section-title"><h3>Questions fréquentes</h3></div>
      <div class="menu-list">
        ${faq("Comment retrouver mon billet ?","Vos billets sont disponibles dans l’onglet Voyages, même hors ligne.")}
        ${faq("Mon paiement est en attente","KORA vérifie automatiquement le statut toutes les 10 secondes.")}
        ${faq("Comment annuler un billet ?","Ouvrez le billet puis sélectionnez Annuler pour voir les conditions applicables.")}
        ${faq("Mon bus est en retard","Les estimations sont recalculées à chaque escale et affichées dans le suivi.")}
        ${faq("Où retirer mon colis ?","Le point de retrait apparaît dans le suivi dès que le colis est disponible.")}
      </div>
      <button class="primary" style="margin-top:15px" data-goto="contact">Contacter l’assistance</button>`, { active: "profile" }),

    contact: () => screen(`
      ${header("Contacter KORA", "Assistance")}
      <div class="card navy" style="padding:24px;text-align:center">
        <div style="width:67px;height:67px;margin:auto;display:grid;place-items:center;border-radius:21px;color:var(--kora);background:var(--gold);font-size:28px;font-weight:900">W</div>
        <h2 style="font-size:25px;margin:16px 0 6px">Parlons sur WhatsApp</h2>
        <p>Notre équipe vous répond tous les jours de 06:00 à 22:00.</p>
        <button class="primary" style="margin-top:18px" data-toast="Ouverture WhatsApp simulée.">Démarrer la conversation</button>
      </div>
      <div class="section-title"><h3>Autres moyens</h3></div>
      <div class="menu-list">
        ${menuItem("☎","Téléphone","+229 90 10 96 48","Appel téléphonique simulé.")}
        ${menuItem("@","Email","contact@kora.express","Application email simulée.")}
      </div>
      <div class="card mist" style="margin-top:13px"><p style="margin:0">Pour un paiement, préparez votre référence de réservation ou de transaction.</p></div>`, { active: "profile" })
  };

  function pinScreen(title, subtitle, next, full = false) {
    return screen(`
      ${header(title, "Sécurité")}
      <p class="lead">${subtitle}</p>
      <div class="pin-dots">${[0,1,2,3].map(i=>`<i class="${i<state.pin.length?"filled":""}"></i>`).join("")}</div>
      ${keypad()}
      <button class="primary" style="margin-top:18px" data-pin-submit="${next}">Continuer</button>`, { noNav: true });
  }

  function keypad() {
    return `<div class="pin-keypad">${["1","2","3","4","5","6","7","8","9","⌫","0","✓"].map(k => `<button data-pin-key="${k}">${k}</button>`).join("")}</div>`;
  }

  function permissionCard(symbol, title, text) {
    return `<div class="card"><div class="row start"><span class="transaction-icon">${symbol}</span><div style="flex:1"><strong style="color:var(--kora)">${title}</strong><p>${text}</p></div><button class="toggle on" data-toggle aria-label="Activer ${title}"></button></div></div>`;
  }

  function bookingProgress(step, label) {
    return `<div class="booking-progress">
      <div class="booking-progress-copy"><span>Réservation</span><strong>${label}</strong><b>${step}/5</b></div>
      <div class="booking-progress-track">${[1,2,3,4,5].map(n => `<i class="${n <= step ? "done" : ""}"></i>`).join("")}</div>
    </div>`;
  }

  function notification(symbol, title, text, date, target, unread = false) {
    return `<button class="card clickable" data-goto="${target}" style="width:100%;text-align:left"><div class="row start"><span class="transaction-icon">${symbol}</span><div style="flex:1"><div class="row"><strong style="color:var(--kora)">${title}</strong>${unread ? `<i style="width:8px;height:8px;border-radius:50%;background:var(--gold)"></i>` : ""}</div><p>${text}</p><span class="tiny muted">${date}</span></div><span>›</span></div></button>`;
  }

  function locationPicker(title, value, next, locations) {
    return screen(`
      ${header(title, "Votre trajet")}
      <label class="directory-search" style="margin:0 0 15px"><span>⌕</span><input value="${value}" autofocus></label>
      <span class="eyebrow">Suggestions</span>
      <div class="menu-list" style="margin-top:9px">
        ${locations.map((loc,i) => `<button class="menu-item" data-goto="${next}"><span class="menu-icon">${i===0?"⌖":"○"}</span><span><strong>${loc}</strong><small>${i===0?"Gare recommandée":"Arrêt KORA"}</small></span><span class="chevron">›</span></button>`).join("")}
      </div>
      <div class="section-title"><h3>Recherches récentes</h3></div>
      <div class="row" style="justify-content:flex-start;flex-wrap:wrap"><button class="tag outline" data-goto="${next}">Porto-Novo</button><button class="tag outline" data-goto="${next}">Parakou</button></div>`, { noNav: true });
  }

  function calendarScreen() {
    const days = Array.from({ length: 31 }, (_, i) => i + 1);
    return screen(`
      ${header("Quand partez-vous ?", "Août 2026")}
      <div class="card">
        <div class="row"><button class="icon-button" data-toast="Mois précédent">‹</button><strong style="color:var(--kora)">Août 2026</strong><button class="icon-button" data-toast="Mois suivant">›</button></div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:7px;margin-top:15px;text-align:center">
          ${["L","M","M","J","V","S","D"].map(d=>`<span class="tiny muted">${d}</span>`).join("")}
          ${days.map(d=>`<button data-goto="passengers" style="height:36px;border:0;border-radius:10px;color:${d===4?"var(--kora)":"var(--graphite)"};background:${d===4?"var(--gold)":"transparent"};font-weight:${d===4?900:500}">${d}</button>`).join("")}
        </div>
      </div>
      <div class="card mist" style="margin-top:13px"><p style="margin:0">Les jours marqués en or disposent de plusieurs départs.</p></div>
      <button class="primary" style="margin-top:16px" data-goto="passengers">Choisir le 04 août</button>`, { noNav: true });
  }

  function passengerScreen() {
    return screen(`
      ${header("Combien de passagers ?", "Réservation")}
      <div class="card">
        <div class="row">
          <div><strong style="color:var(--kora)">Passagers</strong><p>1 à 6 · groupe jusqu’à 10</p></div>
          <div class="row"><button class="icon-button" data-count="-1">−</button><strong id="passenger-count" style="font-size:20px;color:var(--kora)">${state.passengerCount}</strong><button class="icon-button" data-count="1">+</button></div>
        </div>
      </div>
      <div class="card mist" style="margin-top:12px"><p style="margin:0">Chaque passager recevra un siège et un QR Code individuel. Un seul paiement couvre toute la réservation.</p></div>
      <div class="button-row" style="margin-top:12px"><button class="secondary" data-goto="group-booking">Groupe 5–10</button><button class="secondary" data-goto="company-account">Compte entreprise</button></div>
      <button class="primary" style="margin-top:20px" data-goto="searching">Afficher les départs</button>`, { noNav: true });
  }

  function serviceTile(symbol, label) {
    return `<div class="quick-tile"><b>${symbol}</b><span>${label}</span></div>`;
  }

  function miniTimeline(items) {
    return `<div class="timeline">${items.map((item,i)=>`<div class="timeline-item ${i===0?"active":""}"><i class="timeline-dot"></i><strong>${item}</strong><small>${i===0?"Départ confirmé":"Heure estimée"}</small></div>`).join("")}</div>`;
  }

  function seatScreen() {
    const rows = [];
    for (let r = 1; r <= 14; r++) {
      ["A","B","aisle","C","D"].forEach((letter, index) => {
        if (letter === "aisle") rows.push(`<span class="aisle"></span>`);
        else {
          const id = `${r}${letter}`;
          if (id === "14D") return;
          const cls = state.selectedSeats.includes(id) ? "selected" : r === 1 ? "blocked" : ["3B","7A","9C"].includes(id) ? "paid" : ["4C","5D","7B","12D"].includes(id) ? "embarked" : ["2C","8A"].includes(id) ? "held" : "";
          const label = cls === "held" ? `<small>06:18</small>` : id;
          rows.push(`<button class="seat ${cls}" data-seat="${id}" aria-label="Siège ${id}${cls ? `, ${cls}` : ""}">${label}</button>`);
        }
      });
    }
    return screen(`
      ${header("Choisissez votre siège", "KORA Confort · Bus 55 places")}
      ${bookingProgress(3, "Votre siège")}
      <div class="seat-reservation-clock">
        <i class="line-clock"></i><span><small>Votre sélection est protégée</small><strong id="seat-timer">07:00 restantes</strong></span>
      </div>
      <div class="seat-legend premium-seat-legend"><span><i class="available"></i>Libre</span><span><i class="sel"></i>Votre choix</span><span><i class="held-key"></i>Réservé</span><span><i class="paid-key"></i>Payé</span><span><i class="embarked-key"></i>Embarqué</span><span><i class="blk"></i>Bloqué</span></div>
      <div class="premium-bus-shell">
        <div class="premium-bus-front">
          <span class="driver-wheel"><i></i></span>
          <strong>AVANT DU BUS</strong>
          <span class="bus-door">SORTIE</span>
        </div>
        <div class="seat-grid premium-seat-grid">${rows.join("")}</div>
        <div class="bus-rear-label"><i></i><span>Arrière du bus</span><i></i></div>
      </div>
      <div class="seat-checkout">
        <div><span>${state.passengerCount > 1 ? "Sièges sélectionnés" : "Siège sélectionné"}</span><strong id="seat-selection">${state.selectedSeats.join(", ") || "Aucun siège"} · ${state.selectedSeats.length}/${state.passengerCount}</strong></div>
        <div class="seat-price"><span>Total</span><strong>${money(12500 * Math.max(1,state.selectedSeats.length))}</strong></div>
        <button ${state.selectedSeats.length === state.passengerCount ? `data-goto="passenger-info"` : `data-toast="Sélectionnez ${state.passengerCount} siège(s) avant de continuer."`}>Continuer <b>→</b></button>
      </div>`, { noNav: true });
  }

  function passengerInfo(international) {
    return screen(`
      ${header(international ? "Identités internationales" : "Qui voyage ?", international ? "Ligne L1 · Données obligatoires" : `${state.passengerCount} passager${state.passengerCount > 1 ? "s" : ""}`)}
      ${Array.from({length:state.passengerCount},(_,i)=>`
      <section class="passenger-form-card">
      <div class="passenger-form-head"><span>Passager ${i+1}</span><strong>Siège ${state.selectedSeats[i] || "à choisir"}</strong></div>
      <label class="field"><span>Prénom</span><input class="field-control" value="${i===0?"Aïcha":`Passager ${i+1}`}"></label>
      <label class="field"><span>Nom</span><input class="field-control" value="${i===0?"HOUNKPÈ":"KORA"}"></label>
      <label class="field"><span>Téléphone</span><input class="field-control" value="+229 01 97 42 18 ${60+i}"></label>
      ${international ? `
        <label class="field"><span>Type de document</span><select class="field-control"><option>Passeport</option><option>Carte d’identité</option></select></label>
        <label class="field"><span>Numéro du document</span><input class="field-control" value="BJ0427851"></label>
        <div class="grid-2"><label class="field"><span>Nationalité</span><select class="field-control"><option>Béninoise</option><option>Togolaise</option><option>Ghanéenne</option><option>Ivoirienne</option></select></label><label class="field"><span>Naissance</span><input class="field-control" value="18/04/1994"></label></div>` : `
        <label class="field"><span>Email <small class="muted">(optionnel)</small></span><input class="field-control" value="${i===0?"aicha@example.com":""}"></label>`}
      </section>`).join("")}
      ${!international ? `<button class="link-button" style="width:100%;margin:7px 0" data-goto="international-info">Voir le formulaire international</button>`:""}
      <div class="card mist"><p style="margin:0">${international ? "Ces informations servent au manifeste de frontière." : "Ces informations seront associées uniquement à ce billet."}</p></div>
      <button class="primary" style="margin-top:15px" data-goto="summary">Enregistrer et continuer</button>`, { noNav: true });
  }

  function trackingScreen(offline) {
    return screen(`
      ${header("Suivi du bus", "Cotonou → Malanville", { action: `<button class="icon-button" data-goto="${offline ? "tracking" : "gps-offline"}">⌁</button>` })}
      ${offline ? `<div class="card gold" style="margin-bottom:11px"><div class="row"><strong>GPS indisponible</strong><span class="tiny">Depuis 15 min</span></div></div>` : ""}
      <div class="map"><div class="map-route"></div><i class="map-pin p1"></i><i class="map-pin p2"></i><span class="map-label l1">Dassa</span><span class="map-label l2">Parakou</span><div class="bus-marker">▰ KORA</div></div>
      <div class="card" style="margin-top:-25px;margin-left:12px;margin-right:12px">
        <div class="row"><div><span class="tiny muted">${offline ? "Dernière position connue à 12:48" : "En route vers Parakou"}</span><div class="amount" style="font-size:19px">${offline ? "Dassa-Zoumè" : "Arrivée vers 13:25"}</div></div><span class="tag ${offline ? "" : "gold"}">${offline ? "Hors ligne" : "À l’heure"}</span></div>
      </div>
      <div class="section-title"><h3>Prochaine étape</h3><button data-goto="stops">Toutes les étapes</button></div>
      <div class="card"><div class="row"><div><strong style="color:var(--kora)">Parakou</strong><p>Arrivée estimée entre 13:20 et 13:35</p></div><strong>82 km</strong></div></div>
      <button class="ghost" style="margin-top:12px" data-toast="Lien de suivi copié pour WhatsApp.">Partager le suivi</button>`, { active: "tickets" });
  }

  function stopsScreen() {
    const stops = [
      ["Cotonou","07:36","Parti","done"],["Allada","08:22","Parti","done"],["Bohicon","09:41","Parti","done"],
      ["Dassa","11:08","Parti","done"],["Parakou","≈ 13:25","Prochaine","active"],["Kandi","≈ 15:35","À venir",""],["Malanville","≈ 17:15","Destination",""]
    ];
    return screen(`
      ${header("Étapes du voyage", "Mise à jour à 12:54")}
      <div class="card">${stops.map(([name,time,label,cls])=>`<div class="timeline-item ${cls}"><i class="timeline-dot"></i><div class="row"><div><strong>${name}</strong><small>${label}</small></div><b style="color:var(--kora)">${time}</b></div></div>`).join("")}</div>
      <div class="card mist" style="margin-top:12px"><p style="margin:0">Les horaires futurs sont recalculés à partir des pointages réels aux escales.</p></div>`, { active: "tickets" });
  }

  function parcelTimeline() {
    const items = [
      ["Enregistré","28 juil. · 08:12","done"],["Prêt à expédier","28 juil. · 08:38","done"],["Collecté","28 juil. · 09:04","done"],
      ["En transit","Bus L2 · en route","active"],["Arrivé","Est. 16:30",""],["Disponible","À venir",""],["Retiré","À venir",""]
    ];
    return `<div class="timeline">${items.map(([a,b,c])=>`<div class="timeline-item ${c}"><i class="timeline-dot"></i><strong>${a}</strong><small>${b}</small></div>`).join("")}</div>`;
  }

  function rideVehicleCard(name, capacity, eta, price, type) {
    const selected = state.rideVehicle === name;
    return `<button class="ride-vehicle-card ${selected ? "selected" : ""}" data-ride-vehicle="${name}" data-ride-price="${price}">
      <i class="ride-vehicle-icon ${type}">${mobilityPictogram(type)}</i>
      <span><strong>${name}</strong><small>${capacity}</small><em>${eta}</em></span>
      <div><strong>${money(price)}</strong><i>${selected ? "✓" : ""}</i></div>
    </button>`;
  }

  function mobilityPictogram(type) {
    const common = `viewBox="0 0 64 40" aria-hidden="true" focusable="false"`;
    if (type === "moto") return `<svg ${common}><circle cx="15" cy="31" r="6"/><circle cx="49" cy="31" r="6"/><path d="M15 31l10-14h12l12 14M24 17l-5-6m18 6 7-8m-23 17h24"/></svg>`;
    if (type === "keke") return `<svg ${common}><circle cx="13" cy="31" r="5"/><circle cx="50" cy="31" r="5"/><path d="M11 28h42l-5-20H25L14 20zM25 8v20M29 13h15v10H29z"/></svg>`;
    if (type === "bus") return `<svg ${common}><rect x="5" y="5" width="54" height="29" rx="7"/><path d="M12 11h40v11H12zM17 34v3m30-3v3M9 27h46"/><circle cx="17" cy="31" r="3"/><circle cx="47" cy="31" r="3"/></svg>`;
    if (type === "xl") return `<svg ${common}><path d="M5 29V13q0-7 8-7h35q7 0 9 7l3 16zM13 12h35l3 10H13z"/><circle cx="17" cy="30" r="5"/><circle cx="49" cy="30" r="5"/></svg>`;
    return `<svg ${common}><path d="M5 28l5-12q2-5 8-5h24q6 0 9 5l8 12zM17 12l-5 12h40l-8-12z"/><circle cx="17" cy="29" r="5"/><circle cx="49" cy="29" r="5"/>${type === "comfort"?`<path class="picto-star" d="M32 14l2 4 5 .5-4 3 1 5-4-2-4 2 1-5-4-3 5-.5z"/>`:""}</svg>`;
  }

  function rideProgressScreen(offline) {
    return screen(`
      ${header("Course en cours", "Haie Vive → Aéroport", { action: `<button class="icon-button" data-goto="${offline ? "ride-in-progress" : "ride-offline"}" aria-label="État réseau">⌁</button>` })}
      ${offline ? `<div class="offline-ride-banner"><i>!</i><span><strong>Mode hors ligne</strong><small>Course enregistrée · synchronisation automatique au retour du réseau</small></span></div>` : ""}
      <section class="ride-full-map progress-map"><div class="ride-map-grid"></div><span class="ride-map-road road-a"></span><span class="ride-map-road road-b"></span><i class="ride-map-pin pin-destination"></i><i class="ride-car-dot moving">K</i></section>
      <section class="ride-progress-sheet"><div class="ride-sheet-handle"></div><div class="row"><div><small class="muted">${offline ? "Dernier point enregistré à 14:31" : "Arrivée estimée"}</small><div class="amount">${offline ? "Boulevard de la Marina" : "Dans 8 minutes"}</div></div><span class="tag gold">${offline ? "Hors ligne" : "En route"}</span></div>
      <div class="ride-course-progress"><i></i></div>
      <div class="row"><span class="muted">Karim · AB 4589 RB</span><strong>${money(state.ridePrice)}</strong></div>
      <div class="ride-live-actions"><button data-toast="Canal d’appel sécurisé ouvert.">☎<span>Appeler</span></button><button data-goto="ride-share">↗<span>Partager</span></button><button data-goto="ride-sos">SOS<span>Urgence</span></button></div>
      <button class="primary" style="margin-top:11px" data-goto="ride-payment">Simuler l’arrivée</button></section>`, { noNav: true });
  }

  function rideHistoryCard(date, from, to, price, target) {
    return `<button class="ride-history-card" data-goto="${target}"><span class="ride-box-mark">K</span><div><small>${date}</small><strong>${from} → ${to}</strong><span>KORA GO · ${price}</span></div><b>›</b></button>`;
  }

  function cardPaymentScreen(label, amount, successTarget, backTarget) {
    return screen(`
      ${header("Paiement par carte", label)}
      <div class="card-brand-row"><span>VISA</span><span>mastercard</span><b>Paiement sécurisé</b></div>
      <label class="field"><span>Nom sur la carte</span><input class="field-control" value="Aïcha HOUNKPÈ" autocomplete="cc-name"></label>
      <label class="field"><span>Numéro de carte</span><input class="field-control" value="4242 4242 4242 4242" inputmode="numeric" autocomplete="cc-number"></label>
      <div class="grid-2"><label class="field"><span>Expiration</span><input class="field-control" value="08 / 29" inputmode="numeric" autocomplete="cc-exp"></label><label class="field"><span>CVV</span><input class="field-control" value="•••" inputmode="numeric" autocomplete="cc-csc"></label></div>
      <label class="card-save-choice"><input type="checkbox"><span><strong>Enregistrer cette carte</strong><small>Optionnel · protégé par votre PIN KORA</small></span></label>
      <div class="card mist"><div class="row"><span>Total à payer</span><strong class="amount">${amount}</strong></div></div>
      <button class="primary" style="margin-top:14px" data-goto="${successTarget}">Payer ${amount}</button>
      <button class="ghost" style="margin-top:9px" data-goto="${backTarget}">Changer de moyen</button>`, { noNav: true });
  }

  function bonusRow(symbol, title, value, status) {
    return `<button class="bonus-row" data-toast="${title} : ${status}."><i>${symbol}</i><span><strong>${title}</strong><small>${status}</small></span><b>${value}</b></button>`;
  }

  function serviceChoice(title, subtitle, target) {
    return `<button data-goto="${target}"><i>K</i><span><strong>KORA ${title}</strong><small>${subtitle}</small></span><b>›</b></button>`;
  }

  function detailRows(rows) {
    return rows.map(([label, value], index) => `${index ? `<div class="divider"></div>` : ""}<div class="row"><span class="muted">${label}</span><strong>${value}</strong></div>`).join("");
  }

  function transaction(symbol, title, subtitle, amount, credit = false, target = "") {
    return `<div class="transaction ${target ? "clickable" : ""}" ${target ? `data-goto="${target}"` : ""}><span class="transaction-icon">${symbol}</span><div><strong>${title}</strong><small>${subtitle}</small></div><span class="transaction-amount ${credit ? "credit" : ""}">${amount}</span></div>`;
  }

  function menuGoto(symbol, title, subtitle, target) {
    return `<button class="menu-item" data-goto="${target}"><span class="menu-icon">${symbol}</span><span><strong>${title}</strong><small>${subtitle}</small></span><span class="chevron">›</span></button>`;
  }

  function profileAction(iconName, title, subtitle, target) {
    return `<button class="profile-action-card" data-goto="${target}">
      <i class="profile-line-icon ${iconName}-icon"></i>
      <span><strong>${title}</strong><small>${subtitle}</small></span>
      <b>›</b>
    </button>`;
  }

  function menuItem(symbol, title, subtitle, message, toggle = false) {
    return `<button class="menu-item" ${message ? `data-toast="${message}"` : ""} ${toggle ? "data-toggle-row" : ""}><span class="menu-icon">${symbol}</span><span><strong>${title}</strong><small>${subtitle}</small></span>${toggle ? `<span class="toggle on"></span>` : `<span class="chevron">›</span>`}</button>`;
  }

  function settingRow(symbol, title, subtitle, on) {
    return `<div class="menu-item"><span class="menu-icon">${symbol}</span><span><strong>${title}</strong><small>${subtitle}</small></span><button class="toggle ${on ? "on" : ""}" data-toggle aria-label="${title}"></button></div>`;
  }

  function faq(question, answer) {
    return `<button class="menu-item" data-faq><span class="menu-icon">?</span><span><strong>${question}</strong><small class="faq-answer" hidden>${answer}</small></span><span class="chevron">⌄</span></button>`;
  }

  function goto(id, push = true) {
    if (!registry[id]) return;
    if (push && state.current !== id) state.previous.push(state.current);
    state.current = id;
    app.innerHTML = (renderers[id] || fallbackRenderer)(registry[id]);
    enhanceInteractions();
    restoreForm();
    activateLiveTimer();
    app.scrollTop = 0;
    const currentScreen = app.querySelector(".screen");
    if (currentScreen) currentScreen.scrollTop = 0;
    const stage = document.querySelector(".device-stage");
    if (stage) {
      stage.scrollTop = 0;
      stage.scrollLeft = 0;
    }
    if (screenCode) screenCode.textContent = registry[id].code;
    if (screenName) screenName.textContent = registry[id].title;
    window.location.hash = id;
    updateDirectory();
    if (id === "searching") window.setTimeout(() => state.current === "searching" && goto("results"), 1900);
    if (id === "ride-searching") window.setTimeout(() => state.current === "ride-searching" && goto("ride-driver-found"), 2900);
    if (id === "splash") window.setTimeout(() => state.current === "splash" && goto("welcome"), 1700);
  }

  function back() {
    const id = state.previous.pop() || "home";
    goto(id, false);
  }

  function fallbackRenderer(meta) {
    return screen(`${header(meta.title, meta.group)}${stateView({ title: meta.title, text: "Retrouvez ici toutes les informations utiles à votre parcours KORA.", symbol: "K", primary: "home", primaryLabel: "Retour à l’accueil" })}`, { active: "home" });
  }

  function buildDirectory(query = "") {
    if (!directoryList) return;
    const needle = query.trim().toLowerCase();
    directoryList.innerHTML = groups.map(group => {
      const matching = group.screens.filter(([, code, title]) => `${code} ${title}`.toLowerCase().includes(needle));
      if (!matching.length) return "";
      return `<section class="directory-group"><h3>${group.name} <span class="tiny muted">· ${matching.length}</span></h3>${matching.map(([id,code,title])=>`<button data-directory-goto="${id}" class="${state.current===id?"current":""}">${title}<span>${code}</span></button>`).join("")}</section>`;
    }).join("");
  }

  function updateDirectory() {
    if (!filterInput) return;
    buildDirectory(filterInput.value);
  }

  document.addEventListener("click", (event) => {
    const gotoButton = event.target.closest("[data-goto]");
    if (gotoButton) {
      event.preventDefault();
      goto(gotoButton.dataset.goto);
      return;
    }
    const backButton = event.target.closest("[data-back]");
    if (backButton) return back();
    const toastButton = event.target.closest("[data-toast]");
    if (toastButton) return toast(toastButton.dataset.toast);
    const seatButton = event.target.closest("[data-seat]");
    if (seatButton && ["busy","held","blocked","paid","embarked"].some(cls => seatButton.classList.contains(cls))) {
      toast("Ce siège n’est pas disponible. Choisissez-en un autre.");
      return;
    }
    if (seatButton) {
      const id = seatButton.dataset.seat;
      state.selectedSeats = state.selectedSeats.includes(id) ? state.selectedSeats.filter(s => s !== id) : [...state.selectedSeats, id].slice(-state.passengerCount);
      renderCurrent();
      return;
    }
    const countButton = event.target.closest("[data-count]");
    if (countButton) {
      state.passengerCount = Math.max(1, Math.min(10, state.passengerCount + Number(countButton.dataset.count)));
      state.selectedSeats = state.selectedSeats.slice(0, state.passengerCount);
      renderCurrent();
      return;
    }
    const tripTypeButton = event.target.closest("[data-trip-type]");
    if (tripTypeButton) {
      state.tripType = tripTypeButton.dataset.tripType;
      renderCurrent();
      return;
    }
    const groupBooking = event.target.closest("[data-group-booking]");
    if (groupBooking) {
      state.passengerCount = 10;
      state.selectedSeats = [];
      goto("group-discount");
      return;
    }
    const pinSubmit = event.target.closest("[data-pin-submit]");
    if (pinSubmit) {
      submitPin(pinSubmit.dataset.pinSubmit);
      return;
    }
    const pinKey = event.target.closest("[data-pin-key]");
    if (pinKey) {
      const key = pinKey.dataset.pinKey;
      if (key === "⌫") state.pin = state.pin.slice(0, -1);
      else if (key === "✓") {
        submitPin(state.current === "login" ? "home" : state.current === "create-pin" ? "confirm-pin" : "permissions");
        return;
      } else if (state.pin.length < 4) state.pin += key;
      document.querySelectorAll(".pin-dots i").forEach((dot,index)=>dot.classList.toggle("filled",index<state.pin.length));
      return;
    }
    const walletAmount = event.target.closest("[data-wallet-amount]");
    if (walletAmount) {
      state.walletUse = Math.max(0, Math.min(18750, state.walletUse + Number(walletAmount.dataset.walletAmount)));
      renderCurrent();
      return;
    }
    const rideVehicle = event.target.closest("[data-ride-vehicle]");
    if (rideVehicle) {
      state.rideVehicle = rideVehicle.dataset.rideVehicle;
      state.ridePrice = Number(rideVehicle.dataset.ridePrice);
      renderCurrent();
      return;
    }
    const quickMessage = event.target.closest("[data-quick-message]");
    if (quickMessage) {
      sendRideMessage(quickMessage.dataset.quickMessage);
      return;
    }
    const toggle = event.target.closest("[data-toggle]");
    if (toggle) return toggle.classList.toggle("on");
    const toggleRow = event.target.closest("[data-toggle-row]");
    if (toggleRow) {
      toggleRow.querySelector(".toggle")?.classList.toggle("on");
      if (toggleRow.textContent.includes("Mode sombre")) {
        state.dark = !state.dark;
        document.body.classList.toggle("dark-mode", state.dark);
        toast(state.dark ? "Mode sombre activé." : "Mode clair activé.");
      }
      return;
    }
    const faqButton = event.target.closest("[data-faq]");
    if (faqButton) {
      const answer = faqButton.querySelector(".faq-answer");
      answer.hidden = !answer.hidden;
      faqButton.querySelector(".chevron").textContent = answer.hidden ? "⌄" : "⌃";
    }
  });

  if (backdrop && filterInput && directoryList) {
    document.querySelector("#open-directory")?.addEventListener("click", () => {
      backdrop.hidden = false;
      filterInput.focus();
    });
    document.querySelector("#close-directory")?.addEventListener("click", () => backdrop.hidden = true);
    backdrop.addEventListener("click", event => { if (event.target === backdrop) backdrop.hidden = true; });
    directoryList.addEventListener("click", event => {
      const button = event.target.closest("[data-directory-goto]");
      if (!button) return;
      goto(button.dataset.directoryGoto);
      backdrop.hidden = true;
    });
    filterInput.addEventListener("input", () => buildDirectory(filterInput.value));
  }
  document.addEventListener("keydown", event => {
    if (event.key === "Escape") {
      if (backdrop && !backdrop.hidden) backdrop.hidden = true;
      else back();
      return;
    }
    if ((event.key === "Enter" || event.key === " ") && event.target.matches(".clickable[data-goto]:not(button)")) {
      event.preventDefault();
      goto(event.target.dataset.goto);
    }
  });

  document.addEventListener("input", event => {
    if (event.target.matches("[data-points-range]")) {
      state.pointsUse = Number(event.target.value);
      renderCurrent();
    }
    if (event.target.matches("[data-otp-digit]")) {
      event.target.value = event.target.value.replace(/\D/g, "").slice(0,1);
      if (event.target.value) event.target.nextElementSibling?.focus();
    }
    if (event.target.matches(".field-control")) saveForm();
  });

  document.addEventListener("submit", event => {
    const form = event.target.closest("[data-message-form]");
    if (!form) return;
    event.preventDefault();
    const input = form.querySelector("[data-message-input]");
    sendRideMessage(input?.value || "");
  });

  function renderCurrent() {
    app.innerHTML = (renderers[state.current] || fallbackRenderer)(registry[state.current]);
    enhanceInteractions();
    restoreForm();
    activateLiveTimer();
  }

  function enhanceInteractions() {
    document.querySelectorAll("#app .clickable[data-goto]:not(button)").forEach(element => {
      element.setAttribute("role", "button");
      if (!element.hasAttribute("tabindex")) element.tabIndex = 0;
    });
  }

  function sendRideMessage(text) {
    const cleanMessage = text.trim();
    if (!cleanMessage) {
      toast("Écrivez un message avant de l’envoyer.");
      return;
    }
    state.rideMessages.push({ from: "user", text: cleanMessage });
    renderCurrent();
    const thread = document.querySelector(".ride-chat-thread");
    if (thread) thread.scrollTop = thread.scrollHeight;
    toast("Message envoyé à Karim.");
  }

  function saveForm() {
    const values = [...document.querySelectorAll("#app .field-control")].map(control => control.value);
    try { localStorage.setItem(`kora-form-${state.current}`, JSON.stringify(values)); } catch (_) {}
  }

  function restoreForm() {
    try {
      const values = JSON.parse(localStorage.getItem(`kora-form-${state.current}`) || "null");
      if (!Array.isArray(values)) return;
      document.querySelectorAll("#app .field-control").forEach((control,index) => {
        if (values[index] !== undefined) control.value = values[index];
      });
    } catch (_) {}
  }

  function submitPin(next) {
    if (state.pin.length !== 4) return toast("Saisissez les quatre chiffres de votre PIN.");
    if (state.current === "create-pin") {
      state.firstPin = state.pin;
      state.pin = "";
      return goto("confirm-pin");
    }
    if (state.current === "confirm-pin" && state.firstPin && state.pin !== state.firstPin) {
      state.pin = "";
      renderCurrent();
      return toast("Les deux codes PIN ne correspondent pas.");
    }
    state.pin = "";
    goto(next || "home");
  }

  let timerHandle;
  function activateLiveTimer() {
    clearInterval(timerHandle);
    if (!["seats","payment-method"].includes(state.current)) return;
    const paint = () => {
      const mins = String(Math.floor(state.timer / 60)).padStart(2,"0");
      const secs = String(state.timer % 60).padStart(2,"0");
      document.querySelectorAll("#seat-timer,[data-live-timer]").forEach(el => el.textContent = `${mins}:${secs} restantes`);
    };
    paint();
    timerHandle = window.setInterval(() => {
      if (!["seats","payment-method"].includes(state.current)) return clearInterval(timerHandle);
      state.timer -= 1;
      paint();
      if (state.timer <= 0) {
        clearInterval(timerHandle);
        state.selectedSeats = [];
        state.timer = 420;
        goto("expired");
      }
    },1000);
  }

  const initial = window.location.hash.slice(1);
  buildDirectory();
  goto(registry[initial] ? initial : "home", false);
})();

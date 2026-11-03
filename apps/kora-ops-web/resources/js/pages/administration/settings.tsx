import { Head, router, useForm } from '@inertiajs/react';
import {
    AppWindow,
    Activity,
    AtSign,
    BadgePercent,
    BellRing,
    Building2,
    ChevronDown,
    Clock3,
    Coins,
    CreditCard,
    Ellipsis,
    FlaskConical,
    Fingerprint,
    FileJson2,
    Globe2,
    Gauge,
    HardDrive,
    IdCard,
    Languages,
    LockKeyhole,
    MessageCircle,
    MessagesSquare,
    Phone,
    PlugZap,
    RadioTower,
    KeyRound,
    Link2,
    MapPin,
    Repeat2,
    ScrollText,
    Smartphone,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import {
    SettingsField,
    SettingsHelpRail,
    SettingsPageHeader,
    SettingsProbeStatus,
    SettingsSaveBar,
    SettingsSecretField,
    SettingsPhoneField,
    SettingsSection,
    SettingsSelectField,
    SettingsTabs,
    SettingsToggle,
} from '@/components/administration/settings/settings-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import administration from '@/routes/administration';
import type {
    ConnectionProbeResult,
    OperationsSettingsValues,
    SettingsGroup,
    SettingsLineOption,
    SettingsPageProps,
    WalletSettingsValues,
} from '@/types/settings';

type FormScalar = string | number | boolean | null;

type FormValues = {
    values: Record<
        string,
        FormScalar | string[] | number[] | Record<string, number>
    >;
    clearSecrets: string[];
    confirmed: boolean;
};

type IntegrationFieldDefinition = {
    key: string;
    label: string;
    hint: string;
    type?: 'text' | 'number' | 'url';
    suffix?: string;
    secret?: boolean;
    multiline?: boolean;
    icon?: LucideIcon;
    required?: boolean;
    source?: string;
    showConfigured?: boolean;
    fullWidth?: boolean;
};

const sensitiveKeys = new Set([
    'firebaseServiceAccountJson',
    'emecfToken',
    'fedapaySecretKey',
    'fedapayWebhookSecret',
    'mtnMomoSubscriptionKey',
    'mtnMomoUserId',
    'mtnMomoApiKey',
    'mtnMomoWebhookSecret',
    'kkiapayPrivateKey',
    'kkiapaySecretKey',
    'kkiapayWebhookSecret',
    'mapGoogleApiKey',
    'genukaToken',
    'genukaConnectionId',
    'wahaToken',
    'googleOauthClientSecret',
    'facebookOauthClientSecret',
    'tiktokOauthClientSecret',
    'tawktoApiKey',
    'conexteoApiKey',
]);

const integrationFields: Record<string, IntegrationFieldDefinition[]> = {
    firebase: [
        {
            key: 'firebaseProjectId',
            label: 'ID du projet Firebase',
            hint: 'Doit correspondre au projet du compte de service.',
            icon: Fingerprint,
            required: true,
            source: 'Firebase Console → Paramètres du projet → Général → ID du projet.',
        },
        {
            key: 'firebaseWebApiKey',
            label: 'Clé API Web',
            hint: 'Clé publique présente dans la configuration Firebase Web.',
            icon: KeyRound,
            required: true,
            source: 'Paramètres du projet → Général → Vos applications → Configuration du SDK Web → apiKey.',
        },
        {
            key: 'firebaseWebAuthDomain',
            label: 'Domaine Auth Web',
            hint: 'Exemple : mon-projet.firebaseapp.com.',
            icon: Globe2,
            source: 'Paramètres du projet → Général → Vos applications → Configuration du SDK Web → authDomain (facultatif).',
        },
        {
            key: 'firebaseWebStorageBucket',
            label: 'Bucket Storage Web',
            hint: 'Valeur fournie dans la configuration de l’application Web.',
            icon: HardDrive,
            source: 'Paramètres du projet → Général → Vos applications → Configuration du SDK Web → storageBucket (facultatif).',
        },
        {
            key: 'firebaseWebMessagingSenderId',
            label: 'Sender ID Web',
            hint: 'Identifiant numérique du projet Firebase.',
            icon: RadioTower,
            required: true,
            source: 'Paramètres du projet → Cloud Messaging → ID de l’expéditeur.',
        },
        {
            key: 'firebaseWebAppId',
            label: 'App ID Web',
            hint: 'Identifiant de l’application Web Firebase.',
            icon: AppWindow,
            required: true,
            source: 'Paramètres du projet → Général → Vos applications → Configuration du SDK Web → appId.',
        },
        {
            key: 'firebaseAndroidAppId',
            label: 'App ID Android',
            hint: 'Identifiant Firebase de l’application Android Passager.',
            icon: Smartphone,
            source: 'Paramètres du projet → Général → Vos applications Android → App ID (facultatif si le push Android n’est pas encore activé).',
        },
        {
            key: 'firebaseIosAppId',
            label: 'App ID iOS',
            hint: 'Identifiant Firebase de l’application iOS Passager.',
            icon: Smartphone,
            source: 'Paramètres du projet → Général → Vos applications iOS → App ID (facultatif si le push iOS n’est pas encore activé).',
        },
        {
            key: 'firebaseWebVapidKey',
            label: 'Clé VAPID Web',
            hint: 'Clé publique Web Push du projet Firebase.',
            icon: KeyRound,
            required: true,
            source: 'Paramètres du projet → Cloud Messaging → Certificats Web Push → paire de clés Web Push.',
        },
        {
            key: 'firebaseServiceAccountJson',
            label: 'Compte de service JSON',
            hint: 'Colle le JSON complet. Il est chiffré en base et masqué après enregistrement.',
            secret: true,
            multiline: true,
            icon: FileJson2,
            required: true,
            source: 'Paramètres du projet → Comptes de service → Firebase Admin SDK → Générer une nouvelle clé privée. Garde ce fichier confidentiel.',
        },
    ],
    emecf: [
        {
            key: 'emecfToken',
            label: 'Jeton e-MECeF',
            hint: 'Jeton d’accès fourni par la DGI.',
            secret: true,
            icon: KeyRound,
            required: true,
            source: 'Portail ou espace d’habilitation e-MECeF de la DGI.',
        },
        {
            key: 'emecfDefaultIfu',
            label: 'IFU par défaut',
            hint: 'Identifiant fiscal utilisé pour les factures.',
            type: 'text',
            icon: Fingerprint,
            required: true,
            source: 'Identifiant fiscal de la structure enregistré auprès de la DGI.',
        },
        {
            key: 'emecfTestInvoiceUrl',
            label: 'URL facturation test',
            hint: 'Endpoint de création en environnement test.',
            type: 'url',
            icon: Link2,
            required: true,
        },
        {
            key: 'emecfTestInfoUrl',
            label: 'URL informations test',
            hint: 'Endpoint de lecture utilisé par le probe.',
            type: 'url',
            icon: Link2,
            required: true,
        },
        {
            key: 'emecfProductionInvoiceUrl',
            label: 'URL facturation production',
            hint: 'Endpoint de création en production.',
            type: 'url',
            icon: Link2,
            required: true,
        },
        {
            key: 'emecfProductionInfoUrl',
            label: 'URL informations production',
            hint: 'Endpoint de lecture en production.',
            type: 'url',
            icon: Link2,
            required: true,
        },
        {
            key: 'emecfHttpTimeout',
            label: 'Délai HTTP',
            hint: 'Temps maximum d’une requête.',
            type: 'number',
            suffix: 's',
            icon: Gauge,
            required: true,
        },
        {
            key: 'emecfConnectTimeout',
            label: 'Délai de connexion',
            hint: 'Temps maximum pour établir la connexion.',
            type: 'number',
            suffix: 's',
            icon: Gauge,
            required: true,
        },
        {
            key: 'emecfRetry',
            label: 'Tentatives réseau',
            hint: 'Nombre de nouvelles tentatives.',
            type: 'number',
            icon: Repeat2,
            required: true,
        },
        {
            key: 'emecfRetryDelay',
            label: 'Délai entre tentatives',
            hint: 'Pause avant une nouvelle tentative.',
            type: 'number',
            suffix: 'ms',
            icon: Clock3,
            required: true,
        },
        {
            key: 'emecfLogLevel',
            label: 'Niveau de journalisation',
            hint: 'Niveau minimum conservé par le module.',
            icon: ScrollText,
            required: true,
        },
        {
            key: 'emecfInvoiceSubmissionTimeout',
            label: 'Délai d’envoi facture',
            hint: 'Temps maximum pour soumettre une facture.',
            type: 'number',
            suffix: 's',
            icon: Clock3,
            required: true,
        },
        {
            key: 'emecfFinalizationTimeout',
            label: 'Délai de finalisation',
            hint: 'Temps maximum pour confirmer une facture.',
            type: 'number',
            suffix: 's',
            icon: Clock3,
            required: true,
        },
        {
            key: 'emecfStatusCheckTimeout',
            label: 'Délai de statut',
            hint: 'Temps maximum pour lire un statut.',
            type: 'number',
            suffix: 's',
            icon: Clock3,
            required: true,
        },
        {
            key: 'emecfInfoRequestTimeout',
            label: 'Délai informations',
            hint: 'Temps maximum pour la lecture d’informations.',
            type: 'number',
            suffix: 's',
            icon: Clock3,
            required: true,
        },
        {
            key: 'emecfMaxRetryAttempts',
            label: 'Tentatives métier',
            hint: 'Nombre maximum de reprises fiscales.',
            type: 'number',
            icon: Repeat2,
            required: true,
        },
        {
            key: 'emecfRetryBackoffMultiplier',
            label: 'Multiplicateur backoff',
            hint: 'Accélération progressive entre reprises.',
            type: 'number',
            icon: Activity,
            required: true,
        },
    ],
    emecfAccount: [
        {
            key: 'emecfShopName',
            label: 'Nom de la structure',
            hint: 'Raison sociale affichée sur les factures normalisées.',
            icon: Building2,
        },
        {
            key: 'emecfRccm',
            label: 'RCCM',
            hint: 'Numéro RCCM affiché sous l’IFU sur les factures normalisées.',
            icon: IdCard,
        },
        {
            key: 'emecfNim',
            label: 'NIM',
            hint: 'Identifiant du MECeF attribué par la DGI.',
            icon: Fingerprint,
        },
        {
            key: 'emecfAccountStatus',
            label: 'Statut du compte',
            hint: 'Statut déclaré par la DGI (Actif, Suspendu…).',
            icon: Activity,
        },
        {
            key: 'emecfAddress1',
            label: 'Adresse 1',
            hint: 'Adresse principale enregistrée à la DGI.',
            icon: MapPin,
        },
        {
            key: 'emecfAddress2',
            label: 'Adresse 2',
            hint: 'Complément d’adresse éventuel.',
            icon: MapPin,
        },
        {
            key: 'emecfAddress3',
            label: 'Ville',
            hint: 'Ville d’enregistrement du MECeF.',
            icon: MapPin,
        },
        {
            key: 'emecfContact1',
            label: 'Téléphone',
            hint: 'Contact téléphonique déclaré à la DGI.',
            icon: Phone,
        },
        {
            key: 'emecfContact2',
            label: 'Email',
            hint: 'Adresse email déclarée à la DGI.',
            icon: AtSign,
        },
        {
            key: 'emecfContact3',
            label: 'Contact secondaire',
            hint: 'Contact additionnel éventuel.',
            icon: IdCard,
        },
        {
            key: 'emecfApiVersion',
            label: 'Version API',
            hint: 'Version de l’API e-MECeF exposée au compte.',
            icon: ScrollText,
        },
        {
            key: 'emecfTokenValidUntil',
            label: 'Jeton valide jusqu’au',
            hint: 'Date de fin de validité du jeton déclarée par la DGI.',
            icon: Clock3,
        },
    ],
    fedapay: [
        {
            key: 'fedapaySecretKey',
            label: 'Clé secrète (Secret Key)',
            hint: 'Clé privée de l’API FedaPay (sk_…), utilisée côté serveur.',
            source: 'Dashboard FedaPay → API keys → Secret key.',
            secret: true,
            icon: LockKeyhole,
            required: true,
        },
        {
            key: 'fedapayCallbackUrl',
            label: 'URL de callback',
            hint: 'URL de retour du payeur après la transaction FedaPay.',
            source: 'Route KORA : /api/v1/payments/callback/fedapay.',
            type: 'url',
            icon: Link2,
        },
        {
            key: 'fedapayWebhookSecret',
            label: 'Secret webhook',
            hint: 'Secret utilisé pour vérifier la signature des notifications FedaPay.',
            source: 'Dashboard FedaPay → Webhooks → secret de l’endpoint.',
            secret: true,
            icon: LockKeyhole,
        },
        {
            key: 'fedapayCurrency',
            label: 'Devise',
            hint: 'Devise transmise à FedaPay (XOF).',
            icon: Coins,
            required: true,
        },
        {
            key: 'fedapayDefaultPhoneCountry',
            label: 'Pays téléphone',
            hint: 'Code pays par défaut des numéros mobile money (bj, ci, tg).',
            icon: Phone,
            required: true,
        },
        {
            key: 'fedapayMtnMomoMode',
            label: 'Mode MTN MoMo',
            hint: 'Identifiant « mode » sendNow FedaPay pour MTN Bénin.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. mtn).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayMoovFloozMode',
            label: 'Mode Moov Flooz',
            hint: 'Identifiant « mode » sendNow FedaPay pour Moov Bénin.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. moov).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayCeltiisCashMode',
            label: 'Mode Celtiis Cash',
            hint: 'Identifiant « mode » sendNow FedaPay pour Celtiis Bénin.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. celtiis).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayMtnMomoCiMode',
            label: 'Mode MTN MoMo CI',
            hint: 'Identifiant « mode » sendNow FedaPay pour MTN Côte d’Ivoire.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. mtn_ci).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayMoovFloozCiMode',
            label: 'Mode Moov CI',
            hint: 'Identifiant « mode » sendNow FedaPay pour Moov Côte d’Ivoire.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. moov_ci).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayWaveCiMode',
            label: 'Mode Wave CI',
            hint: 'Identifiant « mode » sendNow FedaPay pour Wave Côte d’Ivoire.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. wave_ci).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayOrangeMoneyCiMode',
            label: 'Mode Orange Money CI',
            hint: 'Identifiant « mode » sendNow FedaPay pour Orange Côte d’Ivoire.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. orange_ci).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayMoovFloozTgMode',
            label: 'Mode Moov TG',
            hint: 'Identifiant « mode » sendNow FedaPay pour Moov Togo.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. moov_tg).',
            icon: Smartphone,
            required: true,
        },
        {
            key: 'fedapayTmoneyTgMode',
            label: 'Mode TMoney TG',
            hint: 'Identifiant « mode » sendNow FedaPay pour TMoney Togo.',
            source: 'FedaPay → docs sendNow → modes disponibles (ex. tmoney).',
            icon: Smartphone,
            required: true,
        },
    ],
    kkiapay: [
        {
            key: 'kkiapayPublicKey',
            label: 'Clé publique (Public Key)',
            hint: 'X-API-KEY — aussi transmise au widget côté client (key).',
            source: 'Dashboard KKiaPay → Paramètres → Clés API → Public key.',
            icon: KeyRound,
            required: true,
        },
        {
            key: 'kkiapayPrivateKey',
            label: 'Clé privée (Private Key)',
            hint: 'X-PRIVATE-KEY — vérification des transactions côté serveur.',
            source: 'Dashboard KKiaPay → Paramètres → Clés API → Private key.',
            secret: true,
            icon: LockKeyhole,
            required: true,
        },
        {
            key: 'kkiapaySecretKey',
            label: 'Clé secrète (Secret Key)',
            hint: 'X-SECRET-KEY — vérification des transactions côté serveur.',
            source: 'Dashboard KKiaPay → Paramètres → Clés API → Secret key.',
            secret: true,
            icon: LockKeyhole,
            required: true,
        },
        {
            key: 'kkiapayCallbackUrl',
            label: 'URL de callback',
            hint: 'Champ « callback » du widget : redirection client après paiement (optionnelle pour l’app mobile).',
            source: 'Documentation KKiaPay → SDK JavaScript → paramètre callback.',
            type: 'url',
            icon: Link2,
        },
        {
            key: 'kkiapayWebhookSecret',
            label: 'Secret webhook',
            hint: 'Valeur déclarée sur le webhook du dashboard, reçue en x-kkiapay-secret.',
            source: 'Dashboard KKiaPay → Webhook → secret (valeur à inventer, identique ici).',
            secret: true,
            icon: LockKeyhole,
        },
    ],
    mtnMomo: [
        {
            key: 'mtnMomoSubscriptionKey',
            label: 'Clé d’abonnement (Subscription Key)',
            hint: 'Ocp-Apim-Subscription-Key — souscription au produit Collection, envoyée sur tous les appels.',
            source: 'momodeveloper.mtn.com → Profil → Products → Collection → Primary key.',
            secret: true,
            icon: KeyRound,
            required: true,
        },
        {
            key: 'mtnMomoUserId',
            label: 'API User ID',
            hint: 'UUID du compte technique marchand, utilisé en Basic auth pour obtenir le token.',
            source: 'Sandbox : POST /v1_0/apiuser ou Sandbox User Provisioning du portail. Production : fourni par MTN.',
            secret: true,
            icon: IdCard,
            required: true,
        },
        {
            key: 'mtnMomoApiKey',
            label: 'Clé API User (API Key)',
            hint: 'Clé de l’API User, utilisée avec l’ID pour obtenir le token OAuth.',
            source: 'Sandbox : POST /v1_0/apiuser/{userId}/apikey. Production : fournie par MTN.',
            secret: true,
            icon: LockKeyhole,
            required: true,
        },
        {
            key: 'mtnMomoCallbackUrl',
            label: 'URL de callback',
            hint: 'X-Callback-Url — URL appelée par MTN pour notifier le résultat de la transaction.',
            source: 'Route KORA : /api/v1/payments/webhook/mtn-momo.',
            type: 'url',
            icon: Link2,
        },
        {
            key: 'mtnMomoTargetEnvironment',
            label: 'Environnement cible',
            hint: 'X-Target-Environment — marché MTN qui traite la transaction.',
            source: 'Documentation MTN MoMo → X-Target-Environment (ex. mtnbenin).',
            icon: Globe2,
            required: true,
        },
        {
            key: 'mtnMomoSandboxCurrency',
            label: 'Devise sandbox',
            hint: 'Devise imposée par le bac à sable MTN (EUR).',
            icon: Coins,
            required: true,
        },
        {
            key: 'mtnMomoProductionCurrency',
            label: 'Devise production',
            hint: 'Devise réelle des transactions (XOF).',
            icon: Coins,
            required: true,
        },
        {
            key: 'mtnMomoWebhookSecret',
            label: 'Secret webhook',
            hint: 'Token de filtrage des callbacks entrants (non fourni par MTN, à inventer).',
            secret: true,
            icon: LockKeyhole,
        },
    ],
    map: [
        {
            key: 'mapGoogleApiKey',
            label: 'Clé Google Maps',
            hint: 'Nécessaire uniquement si Google est sélectionné.',
            secret: true,
        },
        {
            key: 'mapOsmTileUrl',
            label: 'URL des tuiles OSM',
            hint: 'Modèle URL des tuiles OpenStreetMap.',
            type: 'url',
        },
        {
            key: 'mapOsmAttribution',
            label: 'Attribution OSM',
            hint: 'Mention affichée sur la carte.',
        },
    ],
    genuka: [
        {
            key: 'genukaUrl',
            label: 'URL Genuka',
            hint: 'Endpoint de l’intégration WhatsApp.',
            type: 'url',
            fullWidth: true,
        },
        {
            key: 'genukaToken',
            label: 'Jeton Genuka',
            hint: 'Jeton d’authentification Genuka.',
            secret: true,
            fullWidth: true,
        },
        {
            key: 'genukaConnectionId',
            label: 'Identifiant de connexion',
            hint: 'Connexion Genuka utilisée par KORA.',
            secret: true,
            fullWidth: true,
        },
    ],
    waha: [
        {
            key: 'wahaUrl',
            label: 'URL WAHA',
            hint: 'Endpoint du serveur WAHA.',
            type: 'url',
            fullWidth: true,
        },
        {
            key: 'wahaToken',
            label: 'Jeton WAHA',
            hint: 'Jeton d’authentification WAHA.',
            secret: true,
            fullWidth: true,
        },
        {
            key: 'wahaSession',
            label: 'Session WhatsApp',
            hint: 'Nom de la session WAHA active.',
        },
        {
            key: 'wahaNewContactDelaySeconds',
            label: 'Délai nouveau contact',
            hint: 'Pause avant le premier message.',
            type: 'number',
            suffix: 's',
        },
    ],
    conexteo: [
        {
            key: 'conexteoAppId',
            label: 'App ID Conexteo',
            hint: 'Identifiant de l’application Conexteo, transmis en en-tête X-APP-ID.',
            icon: Fingerprint,
            required: true,
            showConfigured: true,
            source: 'Conexteo → Espace développeur → votre application → App ID.',
        },
        {
            key: 'conexteoApiKey',
            label: 'Clé API Conexteo',
            hint: 'Clé secrète transmise en en-tête X-API-KEY. Elle reste chiffrée et masquée après enregistrement.',
            secret: true,
            icon: KeyRound,
            required: true,
            source: 'Conexteo → Espace développeur → votre application → Clé API. À conserver côté serveur.',
        },
        {
            key: 'conexteoSender',
            label: 'Expéditeur SMS',
            hint: 'Nom affiché sur le téléphone du destinataire, 11 caractères alphanumériques maximum.',
            icon: AtSign,
            required: true,
        },
    ],
    googleOauth: [
        {
            key: 'googleOauthClientId',
            label: 'Client ID Google',
            hint: 'Identifiant public du client OAuth Web.',
            icon: AtSign,
            required: true,
            showConfigured: true,
            source: 'Google Cloud Console → Google Auth Platform → Clients → client Web → ID client.',
        },
        {
            key: 'googleOauthClientSecret',
            label: 'Client secret Google',
            hint: 'Secret privé du client OAuth. Il reste chiffré et masqué après enregistrement.',
            secret: true,
            icon: KeyRound,
            required: true,
            source: 'Google Cloud Console → Google Auth Platform → Clients → client Web → Secret client. À conserver côté serveur.',
        },
        {
            key: 'googleOauthRedirect',
            label: 'URL de redirection Google',
            hint: 'Si vous utilisez un flux Web par redirection, enregistrez la même URI complète ici et dans la console. Le flux mobile KORA actuel transmet un jeton à l’API.',
            icon: Link2,
            required: true,
            showConfigured: true,
            source: 'Google Cloud Console → Google Auth Platform → Clients → client Web → URI de redirection autorisées.',
        },
    ],
    facebookOauth: [
        {
            key: 'facebookOauthClientId',
            label: 'Client ID Facebook',
            hint: 'Identifiant public de l’application Meta.',
            icon: AtSign,
            required: true,
            showConfigured: true,
            source: 'Meta for Developers → Mes applications → votre application → Paramètres de l’application → Basique → ID de l’application.',
        },
        {
            key: 'facebookOauthClientSecret',
            label: 'Client secret Facebook',
            hint: 'Secret privé de l’application Meta. Il reste chiffré et masqué après enregistrement.',
            secret: true,
            icon: KeyRound,
            required: true,
            source: 'Meta for Developers → Mes applications → votre application → Paramètres de l’application → Basique → Clé secrète de l’application. À conserver côté serveur.',
        },
        {
            key: 'facebookOauthRedirect',
            label: 'URL de redirection Facebook',
            hint: 'Pour un flux Web par redirection, enregistrez la même URI complète ici et dans les URI OAuth valides. Le flux mobile KORA actuel transmet un jeton à l’API.',
            icon: Link2,
            required: true,
            showConfigured: true,
            source: 'Meta for Developers → votre application → Facebook Login → Paramètres → URI de redirection OAuth valides.',
        },
    ],
    tiktokOauth: [
        {
            key: 'tiktokOauthClientId',
            label: 'Client ID TikTok',
            hint: 'Clé publique du client Login Kit Web.',
            icon: AtSign,
            required: true,
            showConfigured: true,
            source: 'TikTok for Developers → Manage apps → votre application → Login Kit → Client key.',
        },
        {
            key: 'tiktokOauthClientSecret',
            label: 'Client secret TikTok',
            hint: 'Secret privé de l’application TikTok. Il reste chiffré et masqué après enregistrement.',
            secret: true,
            icon: KeyRound,
            required: true,
            source: 'TikTok for Developers → Manage apps → votre application → Login Kit → Client secret. À conserver côté serveur.',
        },
        {
            key: 'tiktokOauthRedirect',
            label: 'URL de redirection TikTok',
            hint: 'Pour un flux Web par redirection, enregistrez la même URI complète ici et dans Login Kit. Le flux mobile KORA actuel transmet un jeton à l’API.',
            icon: Link2,
            required: true,
            showConfigured: true,
            source: 'TikTok for Developers → Manage apps → votre application → Login Kit → Redirect URI.',
        },
    ],
    tawkto: [
        {
            key: 'tawktoPropertyId',
            label: 'Property ID tawk.to',
            hint: 'Identifiant public de la propriété tawk.to.',
            icon: Fingerprint,
            required: true,
            showConfigured: true,
            source: 'tawk.to Dashboard → Administration → Channels → Chat Widget → Property ID (visible dans l’URL d’intégration du widget).',
        },
        {
            key: 'tawktoWidgetId',
            label: 'Widget ID',
            hint: 'Identifiant du widget de chat, « default » pour le premier widget.',
            icon: MessagesSquare,
            required: true,
            source: 'tawk.to Dashboard → Administration → Channels → Chat Widget → Widget ID.',
        },
        {
            key: 'tawktoApiKey',
            label: 'Clé API tawk.to',
            hint: 'Active le mode sécurisé : l’identité des utilisateurs connectés est signée en HMAC et vérifiée par tawk.to.',
            secret: true,
            icon: KeyRound,
            source: 'tawk.to Dashboard → Administration → Channels → Chat Widget → Secure Mode (JavaScript API Key). Recommandée pour identifier les agents sans réinscription.',
        },
    ],
};

const titles: Record<SettingsGroup, { title: string; description: string }> = {
    general: {
        title: 'Paramètres généraux',
        description:
            'Définissez l’identité visible de KORA, son contexte régional et les canaux de support proposés aux équipes.',
    },
    wallet: {
        title: 'Wallet & fidélité',
        description:
            'Pilotez l’ouverture du solde cash, le moteur de fidélité, l’expiration des points et les bornes de recharge du Wallet KORA BUS.',
    },
    operations: {
        title: 'Paramètres d’exploitation',
        description:
            'Ajustez les règles de réservation, de départ, de recherche, de session OPS et d’alerte sans modifier le code.',
    },
    integrations: {
        title: 'Paramètres d’intégration',
        description:
            'Centralisez les fournisseurs connectés au MVP KORA BUS, leurs environnements et l’état de leurs credentials.',
    },
};

function initialFormValues(
    values: Record<string, unknown>,
    sensitiveProperties: string[],
): FormValues['values'] {
    const sensitive = new Set(sensitiveProperties);

    return Object.fromEntries(
        Object.entries(values).map(([key, value]) => [
            key,
            sensitive.has(key)
                ? ''
                : (value as FormScalar | Record<string, number>),
        ]),
    );
}

export default function SettingsPage({
    group,
    snapshot,
    permissions,
    lineOptions = [],
    probeResults = {},
}: SettingsPageProps) {
    const initialValues = useMemo(
        () =>
            initialFormValues(
                snapshot.values as Record<string, unknown>,
                snapshot.sensitiveProperties,
            ),
        [snapshot],
    );
    const form = useForm<FormValues>({
        values: initialValues,
        clearSecrets: [],
        confirmed: false,
    });
    const testSmsForm = useForm<{ phone: string }>({ phone: '' });
    const sendTestSms = () => {
        testSmsForm.post(administration.settings.integrations.testSms.url(), {
            preserveScroll: true,
            onSuccess: () => testSmsForm.reset('phone'),
        });
    };

    useEffect(() => {
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!form.isDirty) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () =>
            window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [form.isDirty]);

    const value = (
        key: string,
    ): FormScalar | string[] | number[] | Record<string, number> | undefined =>
        form.data.values[key];
    const error = (key: string): string | undefined => {
        const errors = form.errors as Record<string, string | undefined>;

        return errors[key] ?? errors[`values.${key}`];
    };
    const setValue = (
        key: string,
        nextValue: FormScalar | string[] | number[] | Record<string, number>,
    ) => {
        form.setData('values', { ...form.data.values, [key]: nextValue });

        if (sensitiveKeys.has(key) && form.data.clearSecrets.includes(key)) {
            form.setData(
                'clearSecrets',
                form.data.clearSecrets.filter((secret) => secret !== key),
            );
        }
    };
    const setNumber = (key: string, nextValue: string) => {
        setValue(key, nextValue === '' ? '' : Number(nextValue));
    };
    const clearSecret = (key: string) => {
        if (
            !window.confirm(
                'Supprimer définitivement ce secret de la configuration ?',
            )
        ) {
            return;
        }

        form.setData('clearSecrets', [...form.data.clearSecrets, key]);
    };
    const reset = () => {
        form.setData('values', initialValues);
        form.setData('clearSecrets', []);
        form.setData('confirmed', false);
        form.clearErrors();
    };
    const updateUrl = {
        general: administration.settings.general.update.url(),
        operations: administration.settings.operations.update.url(),
        wallet: administration.settings.wallet.update.url(),
        integrations: administration.settings.integrations.update.url(),
    }[group];
    const initialValue = (
        key: string,
    ): FormScalar | string[] | number[] | Record<string, number> | undefined =>
        initialValues[key];
    const needsConfirmation =
        group === 'operations' &&
        [
            'saleCutoffMinutes',
            'saleCutoffOverrides',
            'paymentTimeoutMinutes',
            'staleDepartureHours',
            'maxDepartureDelayMinutes',
            'opsSessionTtlMinutes',
            'opsSessionMaxLifetimeMinutes',
            'opsSessionTouchIntervalSeconds',
        ].some(
            (key) =>
                JSON.stringify(value(key)) !==
                JSON.stringify(initialValue(key)),
        );
    const submit = () => {
        if (!form.isDirty || !permissions.canManage) {
            return;
        }

        let confirmed = false;

        if (needsConfirmation) {
            confirmed = window.confirm(
                'Ces paramètres peuvent modifier les ventes, les départs ou les sessions en cours. Enregistrer les changements ?',
            );

            if (!confirmed) {
                return;
            }
        }

        form.setData('confirmed', confirmed);
        form.patch(updateUrl, {
            preserveScroll: true,
            onSuccess: () => form.setData('confirmed', false),
        });
    };

    const field = (definition: IntegrationFieldDefinition) => {
        if (definition.secret) {
            return (
                <div
                    key={definition.key}
                    className={
                        definition.fullWidth ? 'md:col-span-2' : undefined
                    }
                >
                    <SettingsSecretField
                        label={definition.label}
                        hint={definition.hint}
                        source={definition.source}
                        required={definition.required}
                        icon={definition.icon}
                        value={String(value(definition.key) ?? '')}
                        configured={Boolean(
                            (snapshot.values as Record<string, unknown>)[
                                definition.key
                            ],
                        )}
                        multiline={definition.multiline}
                        disabled={!permissions.canManage}
                        cleared={form.data.clearSecrets.includes(
                            definition.key,
                        )}
                        error={error(definition.key)}
                        onChange={(nextValue) =>
                            setValue(definition.key, nextValue)
                        }
                        onClear={() => clearSecret(definition.key)}
                    />
                </div>
            );
        }

        return (
            <div
                key={definition.key}
                className={definition.fullWidth ? 'md:col-span-2' : undefined}
            >
                <SettingsField
                    label={definition.label}
                    hint={definition.hint}
                    source={definition.source}
                    required={definition.required}
                    icon={definition.icon}
                    configured={
                        String(value(definition.key) ?? '').trim() !== ''
                    }
                    showConfigured={definition.showConfigured}
                    value={
                        value(definition.key) as
                            string | number | null | undefined
                    }
                    type={definition.type}
                    suffix={definition.suffix}
                    disabled={!permissions.canManage}
                    error={error(definition.key)}
                    onChange={(nextValue) =>
                        definition.type === 'number'
                            ? setNumber(definition.key, nextValue)
                            : setValue(definition.key, nextValue)
                    }
                />
            </div>
        );
    };

    const selectField = (
        key: string,
        label: string,
        hint: string,
        options: Array<{ value: string; label: string }>,
    ) => (
        <SettingsSelectField
            key={key}
            label={label}
            hint={hint}
            value={String(value(key) ?? '')}
            options={options}
            disabled={!permissions.canManage}
            error={error(key)}
            onChange={(nextValue) => setValue(key, nextValue)}
        />
    );

    const renderGeneral = () => {
        return (
            <div className="grid gap-4">
                <SettingsSection
                    title="Identité de l’application"
                    description="Cette identité apparaît dans les écrans OPS et les notifications générées par KORA."
                    className="cash-reveal cash-reveal-delay-2"
                >
                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
                        <SettingsField
                            label="Nom d’affichage"
                            hint="Nom visible par les équipes et les passagers."
                            icon={IdCard}
                            value={value('displayName') as string}
                            disabled={!permissions.canManage}
                            error={error('displayName')}
                            onChange={(nextValue) =>
                                setValue('displayName', nextValue)
                            }
                        />
                        <div className="flex flex-col justify-center rounded-xl border border-dashed border-border bg-background/50 px-4 py-3">
                            <span className="text-[9px] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                Aperçu
                            </span>
                            <div className="mt-2.5 flex items-center gap-2.5">
                                <span className="grid size-7 shrink-0 place-items-center rounded-lg bg-kora text-[10px] font-black text-gold">
                                    K
                                </span>
                                <span className="truncate text-sm font-extrabold tracking-[-0.02em] text-foreground">
                                    {String(value('displayName') || 'KORA')}
                                </span>
                            </div>
                        </div>
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Langue et contexte régional"
                    description="Le fuseau est appliqué aux départs, aux dates métier et aux journaux runtime."
                    className="cash-reveal cash-reveal-delay-3"
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <SettingsField
                            label="Locale"
                            hint="Locale principale, par exemple fr."
                            icon={Languages}
                            value={value('locale') as string}
                            disabled={!permissions.canManage}
                            error={error('locale')}
                            onChange={(nextValue) =>
                                setValue('locale', nextValue)
                            }
                        />
                        <SettingsField
                            label="Locale de secours"
                            hint="Utilisée lorsqu’une traduction manque."
                            icon={Globe2}
                            value={value('fallbackLocale') as string}
                            disabled={!permissions.canManage}
                            error={error('fallbackLocale')}
                            onChange={(nextValue) =>
                                setValue('fallbackLocale', nextValue)
                            }
                        />
                        <SettingsField
                            label="Fuseau horaire"
                            hint="Valeur initiale : Africa/Porto-Novo."
                            icon={Clock3}
                            value={value('timezone') as string}
                            disabled={!permissions.canManage}
                            error={error('timezone')}
                            onChange={(nextValue) =>
                                setValue('timezone', nextValue)
                            }
                        />
                        <SettingsField
                            label="Devise"
                            hint="Devise par défaut des ventes et paiements."
                            icon={Coins}
                            value={value('currency') as string}
                            disabled={!permissions.canManage}
                            error={error('currency')}
                            onChange={(nextValue) =>
                                setValue('currency', nextValue.toUpperCase())
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Canaux de support"
                    description="Ces numéros sont réutilisables par les parcours de réservation et d’assistance."
                    className="cash-reveal cash-reveal-delay-4"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <SettingsPhoneField
                            label="Téléphone support appel"
                            hint="Format international recommandé."
                            icon={Phone}
                            value={value('supportCallPhone') as string | null}
                            disabled={!permissions.canManage}
                            error={error('supportCallPhone')}
                            onChange={(nextValue) =>
                                setValue('supportCallPhone', nextValue)
                            }
                        />
                        <SettingsPhoneField
                            label="Téléphone support WhatsApp"
                            hint="Numéro utilisé pour les échanges WhatsApp."
                            icon={MessageCircle}
                            value={
                                value('supportWhatsappPhone') as string | null
                            }
                            disabled={!permissions.canManage}
                            error={error('supportWhatsappPhone')}
                            onChange={(nextValue) =>
                                setValue('supportWhatsappPhone', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
            </div>
        );
    };

    const renderWallet = () => {
        const wallet = snapshot.values as WalletSettingsValues;
        const reminderDays =
            (value('pointsExpiryReminderDays') as number[]) ?? [];

        return (
            <div className="cash-reveal cash-reveal-delay-2 grid gap-4">
                <SettingsSection
                    title="Ouverture du Wallet"
                    description="Le solde cash reste fermé tant que la validation juridique du montage n’est pas prononcée."
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <SettingsToggle
                            label="Solde cash activé"
                            hint="Gate la recharge, le débit cash et le catalogue Wallet."
                            icon={Coins}
                            checked={Boolean(value('walletCashEnabled'))}
                            disabled={!permissions.canManage}
                            onChange={(checked) =>
                                setValue('walletCashEnabled', checked)
                            }
                        />
                        <SettingsToggle
                            label="Points fidélité activés"
                            hint="Gate le solde de points et le moteur de fidélité."
                            icon={BadgePercent}
                            checked={Boolean(value('walletPointsEnabled'))}
                            disabled={!permissions.canManage}
                            onChange={(checked) =>
                                setValue('walletPointsEnabled', checked)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Fidélité"
                    description="Le taux s’applique à la part monétaire des allocations figées de chaque jambe effectuée."
                >
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <SettingsField
                            label="Taux d’acquisition"
                            hint={`${(wallet.loyaltyEarnRateBasisPoints / 100).toFixed(2)} % du montant monétaire de la jambe.`}
                            value={
                                value('loyaltyEarnRateBasisPoints') as number
                            }
                            type="number"
                            suffix="pts base 10000"
                            disabled={!permissions.canManage}
                            error={error('loyaltyEarnRateBasisPoints')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'loyaltyEarnRateBasisPoints',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Valeur d’un point"
                            hint="Valeur d’usage d’un point en FCFA."
                            value={value('pointValueFcfa') as number}
                            type="number"
                            suffix="FCFA"
                            disabled={!permissions.canManage}
                            error={error('pointValueFcfa')}
                            onChange={(nextValue) =>
                                setNumber('pointValueFcfa', nextValue)
                            }
                        />
                        <SettingsField
                            label="Minimum d’utilisation"
                            hint="Points minimums par paiement."
                            value={value('pointsMinSpend') as number}
                            type="number"
                            suffix="points"
                            disabled={!permissions.canManage}
                            error={error('pointsMinSpend')}
                            onChange={(nextValue) =>
                                setNumber('pointsMinSpend', nextValue)
                            }
                        />
                        <SettingsField
                            label="Bonus bienvenue"
                            hint="Versé une fois, après la première jambe effectuée."
                            value={value('bonusWelcomePoints') as number}
                            type="number"
                            suffix="points"
                            disabled={!permissions.canManage}
                            error={error('bonusWelcomePoints')}
                            onChange={(nextValue) =>
                                setNumber('bonusWelcomePoints', nextValue)
                            }
                        />
                        <SettingsField
                            label="Bonus parrainage"
                            hint="Versé au parrain quand le filleul utilise son premier billet."
                            value={value('bonusReferralPoints') as number}
                            type="number"
                            suffix="points"
                            disabled={!permissions.canManage}
                            error={error('bonusReferralPoints')}
                            onChange={(nextValue) =>
                                setNumber('bonusReferralPoints', nextValue)
                            }
                        />
                        <SettingsField
                            label="Bonus anniversaire"
                            hint="Versé à l’anniversaire de création du compte."
                            value={value('bonusAnniversaryPoints') as number}
                            type="number"
                            suffix="points"
                            disabled={!permissions.canManage}
                            error={error('bonusAnniversaryPoints')}
                            onChange={(nextValue) =>
                                setNumber('bonusAnniversaryPoints', nextValue)
                            }
                        />
                        <SettingsField
                            label="Rang de l’utilisation bonus"
                            hint="Nième utilisation du mois calendaire déclenchant le bonus."
                            value={value('bonusUsageNth') as number}
                            type="number"
                            disabled={!permissions.canManage}
                            error={error('bonusUsageNth')}
                            onChange={(nextValue) =>
                                setNumber('bonusUsageNth', nextValue)
                            }
                        />
                        <SettingsField
                            label="Montant du bonus d’usage"
                            hint="Points versés au rang configuré."
                            value={value('bonusUsagePoints') as number}
                            type="number"
                            suffix="points"
                            disabled={!permissions.canManage}
                            error={error('bonusUsagePoints')}
                            onChange={(nextValue) =>
                                setNumber('bonusUsagePoints', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Expiration des points"
                    description="Une activité qualifiante (achat ou recharge confirmée) repousse l’échéance."
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <SettingsField
                            label="Fenêtre d’expiration"
                            hint="Mois sans activité qualifiante avant expiration globale."
                            value={value('pointsExpiryMonths') as number}
                            type="number"
                            suffix="mois"
                            disabled={!permissions.canManage}
                            error={error('pointsExpiryMonths')}
                            onChange={(nextValue) =>
                                setNumber('pointsExpiryMonths', nextValue)
                            }
                        />
                        <SettingsField
                            label="Jalons de rappel"
                            hint="Jours avant expiration, séparés par des virgules."
                            value={reminderDays.join(', ')}
                            disabled={!permissions.canManage}
                            error={error('pointsExpiryReminderDays')}
                            onChange={(nextValue) =>
                                setValue(
                                    'pointsExpiryReminderDays',
                                    nextValue
                                        .split(',')
                                        .map((entry) => Number(entry.trim()))
                                        .filter(
                                            (entry) =>
                                                Number.isFinite(entry) &&
                                                entry > 0,
                                        ),
                                )
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Recharge et plafonds"
                    description="Les bornes sont facultatives : laissez vide pour ne pas plafonner."
                >
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <SettingsField
                            label="Recharge minimum"
                            hint="Montant minimum accepté à la recharge."
                            value={
                                (value('topupMinAmount') as number | null) ?? ''
                            }
                            type="number"
                            suffix="FCFA"
                            disabled={!permissions.canManage}
                            error={error('topupMinAmount')}
                            onChange={(nextValue) =>
                                setValue(
                                    'topupMinAmount',
                                    nextValue === '' ? null : Number(nextValue),
                                )
                            }
                        />
                        <SettingsField
                            label="Recharge maximum"
                            hint="Montant maximum accepté à la recharge."
                            value={
                                (value('topupMaxAmount') as number | null) ?? ''
                            }
                            type="number"
                            suffix="FCFA"
                            disabled={!permissions.canManage}
                            error={error('topupMaxAmount')}
                            onChange={(nextValue) =>
                                setValue(
                                    'topupMaxAmount',
                                    nextValue === '' ? null : Number(nextValue),
                                )
                            }
                        />
                        <SettingsField
                            label="Plafond du solde cash"
                            hint="Solde cash maximum autorisé."
                            value={
                                (value('cashBalanceCap') as number | null) ?? ''
                            }
                            type="number"
                            suffix="FCFA"
                            disabled={!permissions.canManage}
                            error={error('cashBalanceCap')}
                            onChange={(nextValue) =>
                                setValue(
                                    'cashBalanceCap',
                                    nextValue === '' ? null : Number(nextValue),
                                )
                            }
                        />
                        <SettingsField
                            label="Paliers de recharge"
                            hint="Montants rapides proposés, séparés par des virgules."
                            value={(
                                (value('topupAmountPresets') as number[]) ?? []
                            ).join(', ')}
                            disabled={!permissions.canManage}
                            error={error('topupAmountPresets')}
                            onChange={(nextValue) =>
                                setValue(
                                    'topupAmountPresets',
                                    nextValue
                                        .split(',')
                                        .map((entry) => Number(entry.trim()))
                                        .filter(
                                            (entry) =>
                                                Number.isFinite(entry) &&
                                                entry > 0,
                                        ),
                                )
                            }
                        />
                        <SettingsField
                            label="Durée d’un hold"
                            hint="Durée de vie d’une réservation de fonds."
                            value={value('walletHoldTtlMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('walletHoldTtlMinutes')}
                            onChange={(nextValue) =>
                                setNumber('walletHoldTtlMinutes', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
            </div>
        );
    };

    const renderOperations = () => {
        const operations = snapshot.values as OperationsSettingsValues;
        const overrides =
            (value('saleCutoffOverrides') as Record<string, number>) ?? {};

        return (
            <div className="cash-reveal cash-reveal-delay-2 grid gap-4">
                <SettingsSection
                    title="Remise groupe automatique"
                    description="Une remise d’exploitation s’applique aux groupes individuels au seuil configuré."
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        <SettingsToggle
                            label="Remise groupe active"
                            hint="Les comptes entreprise utilisent leur remise négociée à la place."
                            icon={BadgePercent}
                            checked={Boolean(value('groupDiscountEnabled'))}
                            disabled={!permissions.canManage}
                            onChange={(checked) =>
                                setValue('groupDiscountEnabled', checked)
                            }
                        />
                        <SettingsField
                            label="Seuil de voyageurs"
                            hint="Doit rester dans les bornes groupe configurées."
                            value={
                                value(
                                    'groupDiscountPassengerThreshold',
                                ) as number
                            }
                            type="number"
                            disabled={!permissions.canManage}
                            error={error('groupDiscountPassengerThreshold')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'groupDiscountPassengerThreshold',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Pourcentage de remise"
                            hint="Pourcentage appliqué au panier après promotion."
                            value={value('groupDiscountPercentage') as number}
                            type="number"
                            step="0.01"
                            suffix="%"
                            disabled={!permissions.canManage}
                            error={error('groupDiscountPercentage')}
                            onChange={(nextValue) =>
                                setNumber('groupDiscountPercentage', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Réservation et vente"
                    description="Ces valeurs encadrent la durée de vie des devis et des paiements avant expiration."
                >
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <SettingsField
                            label="Validité d’un devis"
                            hint="Temps laissé au passager pour confirmer."
                            value={value('quoteTtlMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('quoteTtlMinutes')}
                            onChange={(nextValue) =>
                                setNumber('quoteTtlMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Blocage des sièges"
                            hint="Durée de blocage des sièges — bornée par la validité du devis."
                            value={value('seatHoldMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('seatHoldMinutes')}
                            onChange={(nextValue) =>
                                setNumber('seatHoldMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Coupure avant départ"
                            hint="Délai global avant fermeture de la vente."
                            value={value('saleCutoffMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('saleCutoffMinutes')}
                            onChange={(nextValue) =>
                                setNumber('saleCutoffMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Expiration d’un paiement"
                            hint="Durée maximum d’une tentative de paiement."
                            value={value('paymentTimeoutMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('paymentTimeoutMinutes')}
                            onChange={(nextValue) =>
                                setNumber('paymentTimeoutMinutes', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Départs et correspondances"
                    description="Les marges permettent au moteur de recherche et au contrôle de sellability de rester alignés avec l’exploitation."
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <SettingsField
                            label="Seuil des départs périmés"
                            hint="Après ce délai, un départ est considéré obsolète."
                            value={value('staleDepartureHours') as number}
                            type="number"
                            suffix="h"
                            disabled={!permissions.canManage}
                            error={error('staleDepartureHours')}
                            onChange={(nextValue) =>
                                setNumber('staleDepartureHours', nextValue)
                            }
                        />
                        <SettingsField
                            label="Retard maximal autorisé"
                            hint="Retard maximum avant refus métier."
                            value={value('maxDepartureDelayMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('maxDepartureDelayMinutes')}
                            onChange={(nextValue) =>
                                setNumber('maxDepartureDelayMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Marge correspondance"
                            hint="Marge appliquée entre deux tronçons."
                            value={value('connectionMarginMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('connectionMarginMinutes')}
                            onChange={(nextValue) =>
                                setNumber('connectionMarginMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Marge aller-retour"
                            hint="Marge additionnelle pour un retour."
                            value={value('roundTripMarginMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('roundTripMarginMinutes')}
                            onChange={(nextValue) =>
                                setNumber('roundTripMarginMinutes', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Itinéraires et recherche"
                    description="Ces limites protègent la lisibilité des résultats et le temps de calcul du moteur de recherche."
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <SettingsField
                            label="Nombre maximum de tronçons"
                            hint="Limite d’une proposition d’itinéraire."
                            value={value('maxLegs') as number}
                            type="number"
                            suffix="tronçons"
                            disabled={!permissions.canManage}
                            error={error('maxLegs')}
                            onChange={(nextValue) =>
                                setNumber('maxLegs', nextValue)
                            }
                        />
                        <SettingsField
                            label="Résultats maximum"
                            hint="Nombre de résultats retournés."
                            value={value('maxResults') as number}
                            type="number"
                            suffix="résultats"
                            disabled={!permissions.canManage}
                            error={error('maxResults')}
                            onChange={(nextValue) =>
                                setNumber('maxResults', nextValue)
                            }
                        />
                        <SettingsField
                            label="Fenêtre de recherche"
                            hint="Nombre de jours recherchés par défaut."
                            value={value('defaultSearchDays') as number}
                            type="number"
                            suffix="jours"
                            disabled={!permissions.canManage}
                            error={error('defaultSearchDays')}
                            onChange={(nextValue) =>
                                setNumber('defaultSearchDays', nextValue)
                            }
                        />
                        <SettingsField
                            label="Attente maximum"
                            hint="Durée tolérée pour une correspondance."
                            value={value('maxWaitHours') as number}
                            type="number"
                            suffix="h"
                            disabled={!permissions.canManage}
                            error={error('maxWaitHours')}
                            onChange={(nextValue) =>
                                setNumber('maxWaitHours', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Sessions OPS Mobile et P501"
                    description="Les sessions existantes ne sont pas capturées : les nouveaux paramètres s’appliquent aux prochains renouvellements."
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        <SettingsField
                            label="Durée de session"
                            hint="TTL d’une session active."
                            value={value('opsSessionTtlMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('opsSessionTtlMinutes')}
                            onChange={(nextValue) =>
                                setNumber('opsSessionTtlMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Durée de vie maximale"
                            hint="Plafond absolu de la session."
                            value={
                                value('opsSessionMaxLifetimeMinutes') as number
                            }
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('opsSessionMaxLifetimeMinutes')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'opsSessionMaxLifetimeMinutes',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Mise à jour d’activité"
                            hint="Intervalle de touch de session."
                            value={
                                value(
                                    'opsSessionTouchIntervalSeconds',
                                ) as number
                            }
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('opsSessionTouchIntervalSeconds')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'opsSessionTouchIntervalSeconds',
                                    nextValue,
                                )
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Alertes opérationnelles"
                    description="Contrôlez l’envoi des alertes et leur anti-duplication sans modifier les statuts internes."
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        <SettingsToggle
                            label="Alertes email actives"
                            hint="Autoriser l’envoi des alertes opérationnelles."
                            checked={Boolean(value('notificationsMailEnabled'))}
                            disabled={!permissions.canManage}
                            onChange={(checked) =>
                                setValue('notificationsMailEnabled', checked)
                            }
                        />
                        <SettingsField
                            label="Tentatives email"
                            hint="Nombre de reprises d’envoi."
                            value={value('notificationsMailTries') as number}
                            type="number"
                            suffix="essais"
                            disabled={!permissions.canManage}
                            error={error('notificationsMailTries')}
                            onChange={(nextValue) =>
                                setNumber('notificationsMailTries', nextValue)
                            }
                        />
                        <SettingsField
                            label="Anti-duplication manifeste"
                            hint="Délai avant une nouvelle alerte identique."
                            value={
                                value('manifestStaleDebounceSeconds') as number
                            }
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('manifestStaleDebounceSeconds')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'manifestStaleDebounceSeconds',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Rappel avant départ 1"
                            hint="Premier rappel Passager, initialisé à 24 h."
                            value={
                                (
                                    value(
                                        'passengerDepartureReminderHours',
                                    ) as number[]
                                )[0] ?? 24
                            }
                            type="number"
                            suffix="h"
                            disabled={!permissions.canManage}
                            onChange={(nextValue) =>
                                setValue('passengerDepartureReminderHours', [
                                    Number(nextValue),
                                    ...(
                                        value(
                                            'passengerDepartureReminderHours',
                                        ) as number[]
                                    ).slice(1),
                                ])
                            }
                        />
                        <SettingsField
                            label="Rappel avant départ 2"
                            hint="Second rappel Passager, initialisé à 2 h."
                            value={
                                (
                                    value(
                                        'passengerDepartureReminderHours',
                                    ) as number[]
                                )[1] ?? 2
                            }
                            type="number"
                            suffix="h"
                            disabled={!permissions.canManage}
                            onChange={(nextValue) =>
                                setValue('passengerDepartureReminderHours', [
                                    (
                                        value(
                                            'passengerDepartureReminderHours',
                                        ) as number[]
                                    )[0] ?? 24,
                                    Number(nextValue),
                                ])
                            }
                        />
                        <SettingsField
                            label="Seuil de retard Passager"
                            hint="Le Passager est notifié à partir de ce retard."
                            value={
                                value(
                                    'passengerDelayThresholdMinutes',
                                ) as number
                            }
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('passengerDelayThresholdMinutes')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'passengerDelayThresholdMinutes',
                                    nextValue,
                                )
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Suivi de flotte"
                    description="Seuils de fraîcheur GPS, fenêtres d’ingestion des positions P501 et tolérances de l’itinéraire affiché sur la carte OPS."
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <SettingsField
                            label="Position considérée ancienne"
                            hint="Au-delà, le bus passe du signal « en direct » à « position ancienne »."
                            value={value('trackingStaleAfterSeconds') as number}
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('trackingStaleAfterSeconds')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingStaleAfterSeconds',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Terminal hors ligne après"
                            hint="Sans activité du P501 depuis ce délai, la session est signalée hors ligne."
                            value={
                                value(
                                    'trackingTerminalOfflineAfterSeconds',
                                ) as number
                            }
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('trackingTerminalOfflineAfterSeconds')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingTerminalOfflineAfterSeconds',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Précision dégradée au-delà de"
                            hint="Rayon d’incertitude GPS à partir duquel une position est marquée dégradée."
                            value={
                                value(
                                    'trackingDegradedAccuracyMeters',
                                ) as number
                            }
                            type="number"
                            suffix="m"
                            disabled={!permissions.canManage}
                            error={error('trackingDegradedAccuracyMeters')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingDegradedAccuracyMeters',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Couloir d’itinéraire"
                            hint="Écart maximal à la polyligne des arrêts avant le badge « Écart détecté »."
                            value={
                                value('trackingRouteCorridorMeters') as number
                            }
                            type="number"
                            suffix="m"
                            disabled={!permissions.canManage}
                            error={error('trackingRouteCorridorMeters')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingRouteCorridorMeters',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Historique carte par bus"
                            hint="Positions récentes exposées par session pour dessiner la trajectoire."
                            value={
                                value('trackingRecentPositionsLimit') as number
                            }
                            type="number"
                            suffix="pts"
                            disabled={!permissions.canManage}
                            error={error('trackingRecentPositionsLimit')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingRecentPositionsLimit',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Tolérance dérive d’horloge"
                            hint="Marge acceptée pour une trame horodatée dans le futur."
                            value={
                                value('trackingMaxClockSkewSeconds') as number
                            }
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('trackingMaxClockSkewSeconds')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingMaxClockSkewSeconds',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Âge max d’une position"
                            hint="Une trame plus ancienne est rejetée à l’ingestion."
                            value={
                                value('trackingMaxPositionAgeMinutes') as number
                            }
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('trackingMaxPositionAgeMinutes')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingMaxPositionAgeMinutes',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Positions max par lot"
                            hint="Taille maximale d’un lot d’ingestion envoyé par un terminal."
                            value={
                                value('trackingMaxPositionsPerBatch') as number
                            }
                            type="number"
                            suffix="pos"
                            disabled={!permissions.canManage}
                            error={error('trackingMaxPositionsPerBatch')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'trackingMaxPositionsPerBatch',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Cache dernière position"
                            hint="TTL du cache de lecture rapide devant la base."
                            value={value('trackingCacheTtlSeconds') as number}
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('trackingCacheTtlSeconds')}
                            onChange={(nextValue) =>
                                setNumber('trackingCacheTtlSeconds', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Rentabilité et GPS"
                    description="Qualité GPS exigée pour calculer la distance réelle, le coût/km et le TCO opérationnel. Sous la couverture minimale, la distance planifiée est utilisée et marquée « estimé »."
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <SettingsField
                            label="Couverture GPS minimale"
                            hint="Part de la session couverte par des positions valides pour retenir la distance GPS."
                            value={
                                value(
                                    'profitabilityGpsMinCoveragePercent',
                                ) as number
                            }
                            type="number"
                            suffix="%"
                            disabled={!permissions.canManage}
                            error={error('profitabilityGpsMinCoveragePercent')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'profitabilityGpsMinCoveragePercent',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Trou GPS maximal"
                            hint="Au-delà, le segment est ignoré et n’entre pas dans la distance."
                            value={
                                value('profitabilityGpsMaxGapMinutes') as number
                            }
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('profitabilityGpsMaxGapMinutes')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'profitabilityGpsMaxGapMinutes',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Vitesse implicite maximale"
                            hint="Un segment plus rapide est considéré aberrant et ignoré."
                            value={
                                value('profitabilityGpsMaxSpeedKmh') as number
                            }
                            type="number"
                            suffix="km/h"
                            disabled={!permissions.canManage}
                            error={error('profitabilityGpsMaxSpeedKmh')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'profitabilityGpsMaxSpeedKmh',
                                    nextValue,
                                )
                            }
                        />
                    </div>
                </SettingsSection>
                <details
                    className="group rounded-2xl border border-border bg-card shadow-sm"
                    open={false}
                >
                    <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-extrabold text-foreground">
                        Coupures avancées par ligne
                        <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <div className="border-t border-border/70 p-5">
                        <p className="mb-4 max-w-2xl text-xs leading-5 text-muted-foreground">
                            Une ligne sans override reprend le délai global. Les
                            lignes inactives ne sont pas proposées.
                        </p>
                        <div className="grid gap-3">
                            {lineOptions.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Aucune ligne active à personnaliser.
                                </p>
                            )}
                            {lineOptions.map((line: SettingsLineOption) => (
                                <div
                                    key={line.uuid}
                                    className="grid gap-3 rounded-xl border border-border/70 bg-background/40 p-3 md:grid-cols-[minmax(0,1fr)_10rem] md:items-center"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">
                                            {line.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {line.code}
                                        </p>
                                    </div>
                                    <Input
                                        type="number"
                                        min={0}
                                        value={overrides[line.uuid] ?? ''}
                                        disabled={!permissions.canManage}
                                        placeholder={`${String(operations.saleCutoffMinutes)} min global`}
                                        onChange={(event) => {
                                            const nextOverrides = {
                                                ...overrides,
                                            };

                                            if (event.target.value === '') {
                                                delete nextOverrides[line.uuid];
                                            } else {
                                                nextOverrides[line.uuid] =
                                                    Number(event.target.value);
                                            }

                                            setValue(
                                                'saleCutoffOverrides',
                                                nextOverrides,
                                            );
                                        }}
                                        className="h-10 rounded-xl bg-background/70"
                                        aria-label={`Coupure pour ${line.name}`}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                </details>
            </div>
        );
    };

    const renderIntegrationCard = (
        key: string,
        title: string,
        description: string,
        options?: {
            enabledKey?: string;
            environmentKey?: string;
            environmentLabel?: (
                value:
                    | FormScalar
                    | string[]
                    | number[]
                    | Record<string, number>
                    | undefined,
            ) => string;
            probeProvider?: string;
            result?: ConnectionProbeResult;
            icon?: LucideIcon;
            fieldColumns?: string;
            inlineContent?: React.ReactNode;
            children?: React.ReactNode;
        },
    ) => {
        const Icon = options?.icon ?? PlugZap;
        const hasErrors = (integrationFields[key] ?? []).some((definition) =>
            Boolean(error(definition.key)),
        );

        return (
            <details className="group overflow-hidden rounded-xl border border-slate-300/80 bg-slate-200/70 shadow-sm dark:border-slate-700/80 dark:bg-slate-800/60">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 transition-colors outline-none hover:bg-slate-300/50 focus-visible:ring-[3px] focus-visible:ring-gold/30 sm:px-5 dark:hover:bg-slate-700/60">
                    <span className="flex min-w-0 items-center gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-kora/5 text-kora dark:bg-gold/10 dark:text-gold">
                            <Icon className="size-4" strokeWidth={2} />
                        </span>
                        <span className="min-w-0">
                            <span className="flex flex-wrap items-center gap-2 text-sm font-extrabold text-foreground">
                                {title}
                                {options?.environmentKey && (
                                    <span className="rounded-full bg-muted px-2 py-0.5 text-[9px] font-bold tracking-wide text-muted-foreground uppercase">
                                        Env ·{' '}
                                        {options.environmentLabel
                                            ? options.environmentLabel(
                                                  value(options.environmentKey),
                                              )
                                            : String(
                                                  value(options.environmentKey),
                                              )}
                                    </span>
                                )}
                            </span>
                            <span className="mt-1 block truncate text-[11px] text-muted-foreground">
                                {description}
                            </span>
                        </span>
                    </span>
                    {hasErrors && (
                        <span className="hidden shrink-0 rounded-full bg-destructive/10 px-2 py-1 text-[9px] font-bold text-destructive sm:inline-flex">
                            Vérifier les erreurs
                        </span>
                    )}
                    <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                </summary>
                <div className="border-t border-slate-300/70 bg-slate-50/80 p-4 sm:p-5 dark:border-slate-700/70 dark:bg-slate-900/35">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        {options?.enabledKey ? (
                            <SettingsToggle
                                label="Intégration active"
                                hint="Conserver la configuration même lorsqu’elle est désactivée."
                                checked={Boolean(value(options.enabledKey))}
                                disabled={!permissions.canManage}
                                onChange={(checked) =>
                                    setValue(
                                        options.enabledKey as string,
                                        checked,
                                    )
                                }
                            />
                        ) : (
                            <span />
                        )}
                        {options?.probeProvider && permissions.canManage && (
                            <Button
                                type="button"
                                variant="kora-secondary"
                                size="kora"
                                onClick={() =>
                                    router.post(
                                        administration.settings.integrations.test.url(
                                            {
                                                provider:
                                                    options.probeProvider as string,
                                            },
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <FlaskConical className="size-3.5" /> Tester la
                                connexion
                            </Button>
                        )}
                    </div>
                    {options?.probeProvider && (
                        <div className="mb-4">
                            <SettingsProbeStatus result={options.result} />
                        </div>
                    )}
                    <div
                        className={`grid gap-5 ${options?.fieldColumns ?? 'md:grid-cols-2'}`}
                    >
                        {(integrationFields[key] ?? []).map(field)}
                    </div>
                    {options?.inlineContent}
                    {options?.children}
                </div>
            </details>
        );
    };

    const renderIntegrationGroup = (
        title: string,
        description: string,
        children: React.ReactNode,
        className = '',
        Icon: LucideIcon = PlugZap,
    ) => (
        <section
            className={`rounded-2xl border border-slate-300/70 bg-slate-100/80 p-3.5 sm:p-4 dark:border-slate-700/70 dark:bg-slate-900/45 ${className}`}
        >
            <header className="mb-3 flex items-start gap-2.5 px-1">
                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <Icon className="size-3.5" strokeWidth={2} />
                </span>
                <div>
                    <h3 className="text-xs font-extrabold tracking-wide text-slate-700 dark:text-slate-200">
                        {title}
                    </h3>
                    <p className="mt-1 text-[10px] leading-4 text-slate-500 dark:text-slate-400">
                        {description}
                    </p>
                </div>
            </header>
            {children}
        </section>
    );

    const renderIntegrations = () => (
        <div className="cash-reveal cash-reveal-delay-2 grid gap-4 xl:grid-cols-2">
            {renderIntegrationGroup(
                'Notifications',
                'Firebase pour le push Web et Conexteo pour les SMS transactionnels.',
                <div className="grid gap-3 xl:grid-cols-2">
                    {renderIntegrationCard(
                        'firebase',
                        'Firebase Cloud Messaging',
                        'Les champs requis le sont lorsque le push est activé. La clé privée reste côté serveur.',
                        { enabledKey: 'firebasePushEnabled', icon: BellRing },
                    )}
                    {renderIntegrationCard(
                        'conexteo',
                        'Conexteo SMS',
                        'SMS transactionnels vers le payeur et repli des codes OTP.',
                        {
                            enabledKey: 'conexteoEnabled',
                            probeProvider: 'conexteo',
                            result: probeResults.conexteo,
                            icon: Smartphone,
                            children: (
                                <section className="mt-5 rounded-xl border border-slate-300/70 bg-slate-100/70 p-4 dark:border-slate-700/70 dark:bg-slate-800/40">
                                    <div className="flex items-start gap-2.5">
                                        <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                            <Smartphone className="size-3.5" />
                                        </span>
                                        <div>
                                            <h4 className="text-xs font-extrabold text-foreground">
                                                Envoyer un SMS de test
                                            </h4>
                                            <p className="mt-1 text-[11px] leading-4 text-muted-foreground">
                                                Envoi réel et facturé en crédits
                                                Conexteo, journalisé dans les
                                                logs de notification.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                        <SettingsPhoneField
                                            label="Numéro de test"
                                            hint="Format international, ex. +22997000000."
                                            icon={Phone}
                                            value={
                                                testSmsForm.data.phone || null
                                            }
                                            disabled={!permissions.canManage}
                                            error={testSmsForm.errors.phone}
                                            onChange={(nextValue) =>
                                                testSmsForm.setData(
                                                    'phone',
                                                    nextValue ?? '',
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="kora-secondary"
                                            size="kora"
                                            disabled={
                                                !permissions.canManage ||
                                                testSmsForm.processing
                                            }
                                            onClick={sendTestSms}
                                        >
                                            <Smartphone className="size-3.5" />{' '}
                                            Envoyer un SMS de test
                                        </Button>
                                    </div>
                                </section>
                            ),
                        },
                    )}
                </div>,
                'xl:col-span-2',
                BellRing,
            )}
            {renderIntegrationGroup(
                'Intégrations WhatsApp',
                'Canaux WhatsApp utilisés par les communications opérationnelles.',
                <div className="grid gap-3 xl:grid-cols-2">
                    {renderIntegrationCard(
                        'genuka',
                        'Genuka',
                        'Envoi WhatsApp via la connexion Genuka configurée.',
                        { icon: MessageCircle },
                    )}
                    {renderIntegrationCard(
                        'waha',
                        'WAHA',
                        'Session WhatsApp interne pour les notifications opérationnelles.',
                        { icon: Phone },
                    )}
                </div>,
                '',
                MessagesSquare,
            )}
            {renderIntegrationGroup(
                'Fiscalisation',
                'Connexion au service e-MECeF de la DGI.',
                renderIntegrationCard(
                    'emecf',
                    'e-MECeF',
                    'Fiscalisation des factures normalisées auprès de la DGI.',
                    {
                        environmentKey: 'emecfTestMode',
                        environmentLabel: (currentValue) =>
                            currentValue ? 'test' : 'production',
                        probeProvider: 'emecf',
                        result: probeResults.emecf,
                        icon: FileJson2,
                        inlineContent: (
                            <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                <SettingsToggle
                                    label="Mode test"
                                    hint="Utiliser l’environnement de test e-MECeF."
                                    checked={Boolean(value('emecfTestMode'))}
                                    disabled={!permissions.canManage}
                                    icon={FlaskConical}
                                    required
                                    onChange={(checked) =>
                                        setValue('emecfTestMode', checked)
                                    }
                                />
                                <SettingsToggle
                                    label="Sauvegarder les factures"
                                    hint="Conserver un snapshot local après la réponse fournisseur."
                                    checked={Boolean(
                                        value('emecfSaveInvoices'),
                                    )}
                                    disabled={!permissions.canManage}
                                    icon={FileJson2}
                                    required
                                    onChange={(checked) =>
                                        setValue('emecfSaveInvoices', checked)
                                    }
                                />
                                <SettingsToggle
                                    label="Sauvegarder les logs"
                                    hint="Conserver les journaux techniques selon le niveau choisi."
                                    checked={Boolean(value('emecfSaveLogs'))}
                                    disabled={!permissions.canManage}
                                    icon={ScrollText}
                                    required
                                    onChange={(checked) =>
                                        setValue('emecfSaveLogs', checked)
                                    }
                                />
                            </div>
                        ),
                        children: (
                            <section className="mt-5 rounded-xl border border-slate-300/70 bg-slate-100/70 p-4 dark:border-slate-700/70 dark:bg-slate-800/40">
                                <div className="flex items-start gap-2.5">
                                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                        <Building2 className="size-3.5" />
                                    </span>
                                    <div>
                                        <h4 className="text-xs font-extrabold text-foreground">
                                            Compte e-MECeF (DGI)
                                        </h4>
                                        <p className="mt-1 text-[11px] leading-4 text-muted-foreground">
                                            Identité du contribuable
                                            synchronisée depuis l’API e-MECeF et
                                            reprise sur les factures
                                            normalisées.
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                                    {(integrationFields.emecfAccount ?? []).map(
                                        field,
                                    )}
                                </div>
                            </section>
                        ),
                    },
                ),
                'xl:col-span-2',
                FileJson2,
            )}
            {renderIntegrationGroup(
                'Paiements',
                'Fournisseurs et connexions de paiement.',
                <div className="grid gap-3 xl:grid-cols-2">
                    {renderIntegrationCard(
                        'fedapay',
                        'FedaPay',
                        'Paiement en ligne et Mobile Money via FedaPay.',
                        {
                            enabledKey: 'fedapayEnabled',
                            environmentKey: 'fedapayEnvironment',
                            probeProvider: 'fedapay',
                            result: probeResults.fedapay,
                            icon: Coins,
                        },
                    )}
                    {renderIntegrationCard(
                        'mtnMomo',
                        'MTN MoMo',
                        'Paiement direct via le protocole Collection MTN MoMo.',
                        {
                            enabledKey: 'mtnMomoEnabled',
                            environmentKey: 'mtnMomoEnvironment',
                            probeProvider: 'mtn_momo',
                            result: probeResults.mtn_momo,
                            icon: Phone,
                        },
                    )}
                    {renderIntegrationCard(
                        'kkiapay',
                        'KKiaPay',
                        'Agrégateur widget : Mobile Money, carte et wallet multi-pays.',
                        {
                            enabledKey: 'kkiapayEnabled',
                            environmentKey: 'kkiapayEnvironment',
                            probeProvider: 'kkiapay',
                            result: probeResults.kkiapay,
                            icon: Smartphone,
                            children: (
                                <div className="mt-5 space-y-5">
                                    <div className="max-w-xl">
                                        {selectField(
                                            'kkiapayEnvironment',
                                            'Environnement',
                                            'Sandbox pour les tests, production pour l’encaissement réel.',
                                            [
                                                {
                                                    value: 'sandbox',
                                                    label: 'Sandbox',
                                                },
                                                {
                                                    value: 'production',
                                                    label: 'Production',
                                                },
                                            ],
                                        )}
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-bold text-foreground">
                                            Pays acceptés
                                        </p>
                                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                            {[
                                                ['BJ', 'Bénin'],
                                                ['TG', 'Togo'],
                                                ['CI', 'Côte d’Ivoire'],
                                                ['SN', 'Sénégal'],
                                                ['NE', 'Niger'],
                                            ].map(([code, countryLabel]) => {
                                                const countries =
                                                    (value(
                                                        'kkiapayCountries',
                                                    ) as
                                                        string[] | undefined) ??
                                                    [];

                                                return (
                                                    <SettingsToggle
                                                        key={code}
                                                        label={`${code} · ${countryLabel}`}
                                                        checked={countries.includes(
                                                            code,
                                                        )}
                                                        disabled={
                                                            !permissions.canManage
                                                        }
                                                        onChange={(checked) =>
                                                            setValue(
                                                                'kkiapayCountries',
                                                                checked
                                                                    ? [
                                                                          ...countries,
                                                                          code,
                                                                      ]
                                                                    : countries.filter(
                                                                          (
                                                                              country,
                                                                          ) =>
                                                                              country !==
                                                                              code,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                );
                                            })}
                                        </div>
                                        {error('kkiapayCountries') && (
                                            <p className="mt-2 text-[11px] font-semibold text-destructive">
                                                {error('kkiapayCountries')}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ),
                        },
                    )}
                </div>,
                'xl:col-span-2',
                CreditCard,
            )}
            {renderIntegrationGroup(
                'Cartographie',
                'Fournisseur cartographique runtime et paramètres associés.',
                renderIntegrationCard(
                    'map',
                    'Cartographie',
                    'Sélectionnez le fournisseur actif et gérez ses clés.',
                    {
                        icon: Globe2,
                        children: (
                            <div className="mt-5 max-w-xl">
                                {selectField(
                                    'mapProvider',
                                    'Fournisseur actif',
                                    'OpenStreetMap conserve la configuration gratuite par défaut.',
                                    [
                                        {
                                            value: 'openstreetmap',
                                            label: 'OpenStreetMap',
                                        },
                                        {
                                            value: 'google',
                                            label: 'Google Maps',
                                        },
                                    ],
                                )}
                            </div>
                        ),
                    },
                ),
                '',
                Globe2,
            )}
            {renderIntegrationGroup(
                'Support client',
                'Chat en direct tawk.to sur le back-office et l’app Passager.',
                renderIntegrationCard(
                    'tawkto',
                    'tawk.to Live Chat',
                    'Widget de chat chargé sur le back-office et exposé à l’app mobile via l’API.',
                    { enabledKey: 'tawktoEnabled', icon: MessagesSquare },
                ),
                '',
                MessagesSquare,
            )}
            {renderIntegrationGroup(
                'Autres intégrations',
                'Connexions OAuth des fournisseurs sociaux.',
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {renderIntegrationCard(
                        'googleOauth',
                        'Google OAuth',
                        'Client ID et secret requis pour utiliser le fournisseur OAuth.',
                        { icon: Globe2, fieldColumns: 'md:grid-cols-1' },
                    )}
                    {renderIntegrationCard(
                        'facebookOauth',
                        'Facebook OAuth',
                        'ID et clé secrète requis pour utiliser le fournisseur OAuth.',
                        { icon: Globe2, fieldColumns: 'md:grid-cols-1' },
                    )}
                    {renderIntegrationCard(
                        'tiktokOauth',
                        'TikTok OAuth',
                        'Client key et secret requis pour utiliser le fournisseur OAuth.',
                        { icon: Ellipsis, fieldColumns: 'md:grid-cols-1' },
                    )}
                </div>,
                'xl:col-span-2',
                Ellipsis,
            )}
            <p className="flex items-center gap-2 text-xs leading-5 text-muted-foreground">
                <LockKeyhole className="size-4 shrink-0" /> Les secrets restent
                chiffrés en base et ne sont jamais renvoyés en clair.
            </p>
        </div>
    );

    return (
        <>
            <Head title={titles[group].title} />
            <main className="cash-page w-full max-w-full overflow-x-hidden">
                <SettingsPageHeader
                    group={group}
                    title={titles[group].title}
                    description={titles[group].description}
                />
                <div className="cash-reveal cash-reveal-delay-1 mb-5">
                    <SettingsTabs active={group} />
                </div>
                <div
                    className={`grid gap-5 lg:items-start ${group === 'integrations' ? 'grid-cols-1' : 'lg:grid-cols-[minmax(0,1fr)_18rem]'}`}
                >
                    <div className="min-w-0">
                        {group === 'general' && renderGeneral()}
                        {group === 'operations' && renderOperations()}
                        {group === 'wallet' && renderWallet()}
                        {group === 'integrations' && renderIntegrations()}
                        <div className="mt-5">
                            <SettingsSaveBar
                                dirty={form.isDirty}
                                processing={form.processing}
                                canManage={permissions.canManage}
                                onReset={reset}
                                onSave={submit}
                            />
                        </div>
                    </div>
                    {group !== 'integrations' && (
                        <SettingsHelpRail
                            tips={
                                group === 'general'
                                    ? [
                                          {
                                              title: 'Nom et notifications',
                                              text: 'Le nom d’affichage est repris dans les messages et les documents générés par KORA.',
                                          },
                                          {
                                              title: 'Fuseau de référence',
                                              text: 'Conservez Africa/Porto-Novo pour garder les départs et les journaux alignés sur l’exploitation béninoise.',
                                          },
                                      ]
                                    : group === 'wallet'
                                      ? [
                                            {
                                                title: 'Ouverture progressive',
                                                text: 'Le solde cash reste fermé jusqu’à la validation juridique ; les points peuvent être actifs séparément.',
                                            },
                                            {
                                                title: 'Aucune valeur en dur',
                                                text: 'Chaque taux, bonus et plafond est appliqué aux prochaines requêtes sans redéploiement.',
                                            },
                                        ]
                                      : group === 'operations'
                                        ? [
                                              {
                                                  title: 'Confirmez les impacts',
                                                  text: 'Les délais de vente et de session peuvent modifier immédiatement les parcours en cours.',
                                              },
                                              {
                                                  title: 'Préférez les overrides',
                                                  text: 'Utilisez un override de ligne lorsque la règle globale ne décrit pas une exploitation particulière.',
                                              },
                                          ]
                                        : [
                                              {
                                                  title: 'Testez la configuration enregistrée',
                                                  text: 'Un probe utilise uniquement les valeurs déjà sauvegardées, jamais les champs encore en édition.',
                                              },
                                              {
                                                  title: 'Secrets masqués',
                                                  text: 'Laisser un secret vide le conserve. Une suppression exige une action explicite et auditée.',
                                              },
                                          ]
                            }
                        />
                    )}
                </div>
            </main>
        </>
    );
}

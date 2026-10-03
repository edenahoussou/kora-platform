export interface UserProfileData {
    id: string;
    name: string;
    email: string;
    phone: string;
    jobTitle: string;
    department: string;
    agency: string;
    location: string;
    timezone: string;
    avatar: string | null;
    status: 'active' | 'suspended' | 'pending';
    statusLabel: string;
    roleName: string;
    roleBadge: string;
    memberSince: string;
    lastActive: string;
    emailVerified: boolean;
    emailLockedReason: string;
    permissions: Array<{
        code: string;
        label: string;
        category: string;
    }>;
}

export interface UserSessionItem {
    id: string;
    device: string;
    type: 'desktop' | 'mobile' | 'pos';
    browser: string;
    ip: string;
    location: string;
    isCurrent: boolean;
    lastActive: string;
}

export interface UserPasskeyItem {
    id: string;
    name: string;
    authenticator: string;
    createdAt: string;
    lastUsedAt: string;
    type: 'biometric' | 'security_key';
}

export interface UserPreferencesData {
    language: string;
    timezone: string;
    currency: string;
    pushAlerts: boolean;
    criticalBusDispatches: boolean;
    cashierDailyReconciliation: boolean;
    emailWeeklyDigest: boolean;
    twoFactorAlerts: boolean;
}

export const INITIAL_PROFILE_DATA: UserProfileData = {
    id: 'usr_kora_99x82a1',
    name: 'Eden Ahoussou',
    email: 'eden.ahoussou@kora-ops.africa',
    phone: '+229 97 00 12 34',
    jobTitle: 'Directeur Général & Resp. Opérations',
    department: 'Direction Générale & Systèmes d\'Information',
    agency: 'Gare Routière Centrale de Cotonou (Gare A)',
    location: 'Cotonou, Littoral — Bénin',
    timezone: 'Africa/Porto-Novo (GMT+1)',
    avatar: null,
    status: 'active',
    statusLabel: 'Compte Actif & Certifié',
    roleName: 'Administrateur Système (SuperAdmin)',
    roleBadge: 'SUPER_ADMIN',
    memberSince: '14 Janvier 2024',
    lastActive: 'En ligne maintenant',
    emailVerified: true,
    emailLockedReason:
        'Pour des impératifs de conformité SYSCOHADA et de gouvernance de sécurité SOC2/KORA, l\'adresse email officielle est verrouillée au niveau du compte racine. Contactez l\'équipe Sécurité & DSI pour toute modification.',
    permissions: [
        {
            code: 'system.full_access',
            label: 'Contrôle infrastructure & audit global',
            category: 'Gouvernance',
        },
        {
            code: 'sales.pos_override',
            label: 'Dérogation caisse et validation guichet',
            category: 'Exploitation',
        },
        {
            code: 'tickets.ed25519_sign',
            label: 'Clés cryptographiques et signature QR P501',
            category: 'Sécurité',
        },
        {
            code: 'finance.audit_export',
            label: 'Exportation des bilans et réconciliation e-MECeF',
            category: 'Comptabilité',
        },
        {
            code: 'fleet.dispatch_control',
            label: 'Dispatch régulation flotte et télématique',
            category: 'Opérations',
        },
    ],
};

export const INITIAL_SESSIONS_DATA: UserSessionItem[] = [
    {
        id: 'sess_current',
        device: 'MacBook Pro 16" (Apple M3 Max)',
        type: 'desktop',
        browser: 'Google Chrome 124.0.6367 (macOS Sonoma)',
        ip: '102.164.218.42',
        location: 'Cotonou, Littoral — Bénin',
        isCurrent: true,
        lastActive: 'Actif en ce moment',
    },
    {
        id: 'sess_mobile_1',
        device: 'iPhone 15 Pro Max',
        type: 'mobile',
        browser: 'Mobile Safari 17.4 (iOS 17.4.1)',
        ip: '154.68.27.18',
        location: 'Porto-Novo, Ouémé — Bénin',
        isCurrent: false,
        lastActive: 'Il y a 42 minutes',
    },
    {
        id: 'sess_pos_terminal',
        device: 'Terminal KORA P501 Smart POS',
        type: 'pos',
        browser: 'KORA Boarding Supervisor v2.4 (Android Enterprise)',
        ip: '102.164.219.10',
        location: 'Gare Routière Parakou, Borgou — Bénin',
        isCurrent: false,
        lastActive: 'Hier à 18:25',
    },
    {
        id: 'sess_desktop_2',
        device: 'Poste Fixe Agence — Lenovo ThinkStation',
        type: 'desktop',
        browser: 'Mozilla Firefox 125.0 (Ubuntu 24.04 LTS)',
        ip: '197.234.221.5',
        location: 'Abomey-Calavi, Atlantique — Bénin',
        isCurrent: false,
        lastActive: 'Il y a 3 jours',
    },
];

export const INITIAL_PASSKEYS_DATA: UserPasskeyItem[] = [
    {
        id: 'pk_touch_id',
        name: 'MacBook Pro Touch ID / Secure Enclave',
        authenticator: 'Apple Secure Enclave (Biométrie locale)',
        createdAt: '18 Février 2024',
        lastUsedAt: 'Aujourd\'hui à 09:12',
        type: 'biometric',
    },
    {
        id: 'pk_yubikey',
        name: 'YubiKey 5C NFC Security Key',
        authenticator: 'FIDO2 / WebAuthn Hardware Token (NFC + USB-C)',
        createdAt: '10 Décembre 2023',
        lastUsedAt: 'Il y a 5 jours',
        type: 'security_key',
    },
];

export const INITIAL_PREFERENCES_DATA: UserPreferencesData = {
    language: 'fr-BJ',
    timezone: 'Africa/Porto-Novo',
    currency: 'XOF',
    pushAlerts: true,
    criticalBusDispatches: true,
    cashierDailyReconciliation: true,
    emailWeeklyDigest: false,
    twoFactorAlerts: true,
};

export const INITIAL_SECURITY_STATUS = {
    twoFactorEnabled: true,
    secretKey: 'KORA-7X9B-M4P2-Q8Z1-W3E5-TOTP',
    qrCodeValue:
        'otpauth://totp/KORA-OPS:eden.ahoussou@kora-ops.africa?secret=KORA7X9BM4P2Q8Z1W3E5TOTP&issuer=KORA%20OPS%20Africa',
    backupCodesLeft: 8,
    lastPasswordChange: 'Il y a 45 jours',
    passwordScore: 4,
};

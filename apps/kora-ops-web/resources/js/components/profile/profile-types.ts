import type { Passkey } from '@/types/auth';

export interface ProfilePermission {
    code: string;
    label: string;
    category: string;
}

export interface UserProfileData {
    uuid: string;
    name: string;
    email: string;
    emailVerified: boolean;
    emailLockedReason: string;
    phone: string | null;
    countryCode: string | null;
    jobTitle: string | null;
    department: string | null;
    location: string | null;
    timezone: string | null;
    avatar: string | null;
    status: string;
    statusLabel: string;
    roleCode: string | null;
    roleLabel: string;
    agency: string;
    memberSince: string | null;
    permissions: ProfilePermission[];
}

export interface UserSessionItem {
    id: string;
    kind: 'web' | 'terminal' | 'device';
    device: string;
    type: 'desktop' | 'mobile' | 'pos';
    browser: string | null;
    ip: string | null;
    location: string | null;
    isCurrent: boolean;
    lastActive: string | null;
}

export interface UserSecurityData {
    canManageTwoFactor: boolean;
    twoFactorEnabled: boolean;
    requiresConfirmation: boolean;
    canManagePasskeys: boolean;
    passkeys: Passkey[];
    passwordRules: string;
    lastPasswordChange: string | null;
}

export interface UserPreferencesData {
    locale: string;
    timezone: string;
    pushAlerts: boolean;
    whatsappAlerts: boolean;
    emailDigest: boolean;
    dispatchAlerts: boolean;
    cashierAlerts: boolean;
    securityAlerts: boolean;
}

export interface ProfilePageProps {
    profile: UserProfileData;
    security: UserSecurityData;
    sessions: UserSessionItem[];
    preferences: UserPreferencesData;
    status?: string;
}

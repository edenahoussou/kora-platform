export type User = {
    uuid: string;
    name: string;
    email: string;
    avatar?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    canViewCash?: boolean;
    canViewTickets?: boolean;
    permissions?: string[];
    roles?: string[];
    walletCapabilities?: {
        walletOpsView?: boolean;
        walletRulesView?: boolean;
        walletSettingsView?: boolean;
        walletRestitutionQueueView?: boolean;
        walletRestitutionOwnView?: boolean;
        walletRestitutionRequest?: boolean;
    };
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export type AuthenticatedAuth = {
    user: User;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

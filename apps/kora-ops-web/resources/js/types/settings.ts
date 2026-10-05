export type SettingsGroup = 'general' | 'operations' | 'integrations';

export type GeneralSettingsValues = {
    displayName: string;
    locale: string;
    fallbackLocale: string;
    timezone: string;
    currency: string;
    supportCallPhone: string | null;
    supportWhatsappPhone: string | null;
};

export type OperationsSettingsValues = {
    quoteTtlMinutes: number;
    saleCutoffMinutes: number;
    saleCutoffOverrides: Record<string, number>;
    paymentTimeoutMinutes: number;
    staleDepartureHours: number;
    maxDepartureDelayMinutes: number;
    connectionMarginMinutes: number;
    roundTripMarginMinutes: number;
    maxLegs: number;
    maxResults: number;
    defaultSearchDays: number;
    maxWaitHours: number;
    opsSessionTtlMinutes: number;
    opsSessionMaxLifetimeMinutes: number;
    opsSessionTouchIntervalSeconds: number;
    notificationsMailEnabled: boolean;
    notificationsMailTries: number;
    manifestStaleDebounceSeconds: number;
    passengerDepartureReminderHours: number[];
    passengerDelayThresholdMinutes: number;
    groupDiscountEnabled: boolean;
    groupDiscountPassengerThreshold: number;
    groupDiscountPercentage: number;
};

export type IntegrationsSettingsValues = Record<
    string,
    string | number | boolean | null
>;

export type SettingsSnapshot<T extends Record<string, unknown>> = {
    group: SettingsGroup;
    values: T;
    sensitiveProperties: string[];
    capturedAt: string;
};

export type SettingsLineOption = {
    uuid: string;
    code: string;
    name: string;
};

export type ConnectionProbeResult = {
    provider: string;
    status: 'success' | 'failed' | 'unavailable';
    environment: string;
    checkedAt: string | null;
    durationMs: number;
    safeMessage: string;
};

type SettingsPagePermissions = {
    permissions: {
        canView: boolean;
        canManage: boolean;
    };
    lineOptions?: SettingsLineOption[];
    probeResults?: Record<string, ConnectionProbeResult>;
};

export type GeneralSettingsPageProps = SettingsPagePermissions & {
    group: 'general';
    snapshot: SettingsSnapshot<GeneralSettingsValues>;
};

export type OperationsSettingsPageProps = SettingsPagePermissions & {
    group: 'operations';
    snapshot: SettingsSnapshot<OperationsSettingsValues>;
};

export type IntegrationsSettingsPageProps = SettingsPagePermissions & {
    group: 'integrations';
    snapshot: SettingsSnapshot<IntegrationsSettingsValues>;
};

export type SettingsPageProps =
    | GeneralSettingsPageProps
    | OperationsSettingsPageProps
    | IntegrationsSettingsPageProps;

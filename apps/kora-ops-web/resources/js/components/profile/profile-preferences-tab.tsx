import { router } from '@inertiajs/react';
import {
    BellRing,
    Check,
    DollarSign,
    Info,
    Languages,
    Moon,
    Radio,
    Shield,
    ShieldAlert,
    Smartphone,
    Sun,
    SunMoon,
} from 'lucide-react';
import { useState } from 'react';
import ProfilePreferenceController from '@/actions/App/Http/Controllers/Settings/ProfilePreferenceController';
import {
    SettingsSection,
    SettingsSelectField,
    SettingsToggle,
} from '@/components/administration/settings/settings-ui';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { PanelAlert } from '@/components/panel-alert';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import type { UserPreferencesData } from './profile-types';

interface ProfilePreferencesTabProps {
    preferences: UserPreferencesData;
}

type PreferenceFlag =
    | 'push_notifications'
    | 'whatsapp_notifications'
    | 'email_notifications'
    | 'dispatch_alerts'
    | 'cashier_alerts'
    | 'security_alerts';

/**
 * Onglet Préférences & Affichage KORA OPS.
 */
export function ProfilePreferencesTab({
    preferences,
}: ProfilePreferencesTabProps) {
    const { appearance, updateAppearance } = useAppearance();
    const [savingKey, setSavingKey] = useState<string | null>(null);

    const flags: Record<PreferenceFlag, boolean> = {
        push_notifications: preferences.pushAlerts,
        whatsapp_notifications: preferences.whatsappAlerts,
        email_notifications: preferences.emailDigest,
        dispatch_alerts: preferences.dispatchAlerts,
        cashier_alerts: preferences.cashierAlerts,
        security_alerts: preferences.securityAlerts,
    };

    const save = (payload: Record<string, string | boolean>) => {
        const key = Object.keys(payload)[0] ?? 'preferences';
        setSavingKey(key);

        router.put(ProfilePreferenceController.update.url(), payload, {
            preserveScroll: true,
            onFinish: () => setSavingKey(null),
        });
    };

    const toggle = (key: PreferenceFlag) => {
        save({ [key]: !flags[key] });
    };

    const selectTheme = (theme: 'light' | 'dark' | 'system') => {
        updateAppearance(theme);
        save({ theme });
    };

    const themeCard = (
        theme: 'light' | 'dark' | 'system',
        icon: React.ReactNode,
        title: string,
        description: string,
    ) => (
        <div
            role="button"
            tabIndex={0}
            onClick={() => selectTheme(theme)}
            onKeyDown={(e) => e.key === 'Enter' && selectTheme(theme)}
            className={cn(
                'flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all duration-200',
                appearance === theme
                    ? 'border-gold bg-muted/60 shadow-xs ring-2 ring-gold/40'
                    : 'border-border bg-card hover:bg-muted/40',
            )}
        >
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    {icon}
                    <span className="text-sm font-bold text-foreground">
                        {title}
                    </span>
                </div>
                {appearance === theme && (
                    <span className="grid size-4 place-items-center rounded-full bg-gold text-kora">
                        <Check className="size-2.5 stroke-[3]" />
                    </span>
                )}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">{description}</p>
        </div>
    );

    return (
        <div className="space-y-6">
            {/* Alerte contextuelle de diffusion */}
            <PanelAlert tone="info" icon={Info}>
                <span className="font-bold">
                    Notifications en temps réel :{' '}
                </span>
                Les alertes critiques de régulation (retards de bus, pannes de
                lignes) sont diffusées en priorité même si l'application est en
                arrière-plan. Les alertes de sécurité du compte restent toujours
                envoyées par e-mail.
            </PanelAlert>

            {/* 1. Thème d'interface */}
            <SettingsSection
                title="Thème d'interface"
                description="Choisissez le mode d'affichage adapté à votre poste de travail."
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    {themeCard(
                        'light',
                        <Sun className="size-4 text-amber-500" />,
                        'Clair',
                        'Interface claire pour les postes en plein jour.',
                    )}
                    {themeCard(
                        'dark',
                        <Moon className="size-4 text-digital" />,
                        'Sombre',
                        'Palette de nuit pour limiter la fatigue oculaire.',
                    )}
                    {themeCard(
                        'system',
                        <SunMoon className="size-4 text-muted-foreground" />,
                        'Système',
                        "S'adapte automatiquement au système hôte.",
                    )}
                </div>
            </SettingsSection>

            {/* 2. Notifications d'exploitation */}
            <SettingsSection
                title="Notifications d'exploitation"
                description="Réglages des alertes opérationnelles en temps réel."
            >
                <div className="grid gap-2.5">
                    <SettingsToggle
                        label="Alertes critiques de dispatch & retards de lignes"
                        icon={Radio}
                        hint="Retards supérieurs au seuil d'alerte, déviations d'itinéraire ou alertes P501."
                        checked={flags.dispatch_alerts}
                        onChange={() => toggle('dispatch_alerts')}
                        disabled={savingKey === 'dispatch_alerts'}
                    />
                    <SettingsToggle
                        label="Notifications Web Push navigateur"
                        icon={BellRing}
                        hint="Réception des alertes même lorsque l'onglet KORA n'est pas actif."
                        checked={flags.push_notifications}
                        onChange={() => toggle('push_notifications')}
                        disabled={savingKey === 'push_notifications'}
                    />
                    <SettingsToggle
                        label="Alertes WhatsApp"
                        icon={Smartphone}
                        hint="Envoi des notifications opérationnelles sur votre ligne WhatsApp professionnelle."
                        checked={flags.whatsapp_notifications}
                        onChange={() => toggle('whatsapp_notifications')}
                        disabled={savingKey === 'whatsapp_notifications'}
                    />
                    <SettingsToggle
                        label="Clôture de caisse & états journaliers"
                        icon={Shield}
                        hint="Notification lors de la validation des bordereaux d'encaissement et de reversement."
                        checked={flags.cashier_alerts}
                        onChange={() => toggle('cashier_alerts')}
                        disabled={savingKey === 'cashier_alerts'}
                    />
                    <SettingsToggle
                        label="Digest e-mail hebdomadaire"
                        icon={BellRing}
                        hint="Synthèse hebdomadaire de l'exploitation envoyée sur votre e-mail certifié."
                        checked={flags.email_notifications}
                        onChange={() => toggle('email_notifications')}
                        disabled={savingKey === 'email_notifications'}
                    />
                    <SettingsToggle
                        label="Alertes de sécurité du compte"
                        icon={ShieldAlert}
                        hint="Notifications contextuelles sur les événements sensibles (connexions, révocations, changements critiques)."
                        checked={flags.security_alerts}
                        onChange={() => toggle('security_alerts')}
                        disabled={savingKey === 'security_alerts'}
                    />
                </div>
            </SettingsSection>

            {/* 3. Paramètres régionaux */}
            <SettingsSection
                title="Paramètres régionaux"
                description="Contexte linguistique et devise légale."
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <SettingsSelectField
                        label="Langue de travail"
                        icon={Languages}
                        value={preferences.locale}
                        onChange={(v) => save({ locale: v })}
                        disabled={savingKey === 'locale'}
                        options={[
                            { value: 'fr', label: 'Français (Bénin)' },
                            { value: 'en', label: 'English' },
                        ]}
                        hint="Langue appliquée aux libellés d'interface et états d'embarquement."
                    />

                    <div className="grid content-start gap-1.5">
                        <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                            <DollarSign className="size-3.5 text-muted-foreground" />
                            Devise d'exploitation
                        </span>
                        <div className="flex items-center justify-between rounded-[10px] border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                            <div className="flex items-center gap-2">
                                <DollarSign className="size-4 text-emerald-500" />
                                <span>Franc CFA (XOF / FCFA)</span>
                            </div>
                            <KoraStatusBadge label="UEMOA" tone="muted" />
                        </div>
                        <span className="text-[11px] leading-4 text-muted-foreground">
                            Monnaie des tarifs, reçus de vente et clôtures de
                            caisse.
                        </span>
                    </div>
                </div>
            </SettingsSection>
        </div>
    );
}

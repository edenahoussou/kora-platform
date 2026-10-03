import {
    BellRing,
    Check,
    DollarSign,
    Info,
    Languages,
    Moon,
    Radio,
    Shield,
    Sun,
    SunMoon,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PanelAlert } from '@/components/panel-alert';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import {
    INITIAL_PREFERENCES_DATA
    
} from './profile-mock-data';
import type {UserPreferencesData} from './profile-mock-data';

/**
 * Onglet Préférences & Affichage KORA OPS.
 */
export function ProfilePreferencesTab() {
    const { appearance, updateAppearance } = useAppearance();
    const [preferences, setPreferences] =
        useState<UserPreferencesData>(INITIAL_PREFERENCES_DATA);

    const toggleNotification = (key: keyof UserPreferencesData) => {
        setPreferences((prev) => {
            const nextValue = !prev[key];
            const updated = { ...prev, [key]: nextValue };
            toast.success(
                nextValue
                    ? 'Canal de notification activé'
                    : 'Canal de notification désactivé',
            );

            return updated;
        });
    };

    return (
        <div className="space-y-6">
            {/* Alerte contextuelle de diffusion */}
            <PanelAlert tone="info" icon={Info}>
                <span className="font-bold">Notifications en temps réel : </span>
                Les alertes critiques de régulation (retards de bus, pannes de lignes) sont diffusées en priorité même si l'application est en arrière-plan.
            </PanelAlert>

            {/* 1. Thème d'interface */}
            <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                        <div>
                            <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                Thème d'interface
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Choisissez le mode d'affichage adapté à votre poste de travail.
                            </p>
                        </div>
                    </div>
                </header>

                <div className="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {/* Clair */}
                    <div
                        role="button"
                        tabIndex={0}
                        onClick={() => {
                            updateAppearance('light');
                            toast.info('Mode clair activé');
                        }}
                        className={cn(
                            'flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all duration-200',
                            appearance === 'light'
                                ? 'border-gold bg-muted/60 shadow-xs ring-2 ring-gold/40'
                                : 'border-border bg-card hover:bg-muted/40',
                        )}
                    >
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Sun className="size-4 text-amber-500" />
                                <span className="text-sm font-bold text-foreground">Clair</span>
                            </div>
                            {appearance === 'light' && (
                                <span className="grid size-4 place-items-center rounded-full bg-gold text-kora">
                                    <Check className="size-2.5 stroke-[3]" />
                                </span>
                            )}
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Interface claire pour les postes en plein jour.
                        </p>
                    </div>

                    {/* Sombre */}
                    <div
                        role="button"
                        tabIndex={0}
                        onClick={() => {
                            updateAppearance('dark');
                            toast.info('Mode sombre activé');
                        }}
                        className={cn(
                            'flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all duration-200',
                            appearance === 'dark'
                                ? 'border-gold bg-muted/60 shadow-xs ring-2 ring-gold/40'
                                : 'border-border bg-card hover:bg-muted/40',
                        )}
                    >
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Moon className="size-4 text-digital" />
                                <span className="text-sm font-bold text-foreground">Sombre</span>
                            </div>
                            {appearance === 'dark' && (
                                <span className="grid size-4 place-items-center rounded-full bg-gold text-kora">
                                    <Check className="size-2.5 stroke-[3]" />
                                </span>
                            )}
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Palette de nuit pour limiter la fatigue oculaire.
                        </p>
                    </div>

                    {/* Système */}
                    <div
                        role="button"
                        tabIndex={0}
                        onClick={() => {
                            updateAppearance('system');
                            toast.info('Synchronisation système activée');
                        }}
                        className={cn(
                            'flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all duration-200',
                            appearance === 'system'
                                ? 'border-gold bg-muted/60 shadow-xs ring-2 ring-gold/40'
                                : 'border-border bg-card hover:bg-muted/40',
                        )}
                    >
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <SunMoon className="size-4 text-muted-foreground" />
                                <span className="text-sm font-bold text-foreground">Système</span>
                            </div>
                            {appearance === 'system' && (
                                <span className="grid size-4 place-items-center rounded-full bg-gold text-kora">
                                    <Check className="size-2.5 stroke-[3]" />
                                </span>
                            )}
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            S'adapte automatiquement au système hôte.
                        </p>
                    </div>
                </div>
            </section>

            {/* 2. Notifications d'exploitation */}
            <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                        <div>
                            <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                Notifications d'exploitation
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Réglages des alertes opérationnelles en temps réel.
                            </p>
                        </div>
                    </div>
                </header>

                <div className="divide-y divide-border/60">
                    <div className="flex items-center justify-between px-5 py-4">
                        <div className="space-y-0.5">
                            <span className="flex items-center gap-1.5 text-sm font-bold text-foreground">
                                <Radio className="size-4 text-amber-500" />
                                <span>Alertes critiques de dispatch & retards de lignes</span>
                            </span>
                            <p className="text-xs text-muted-foreground">
                                Retards supérieurs au seuil d'alerte, déviations d'itinéraire ou alertes P501.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={preferences.criticalBusDispatches}
                            onClick={() => toggleNotification('criticalBusDispatches')}
                            className={cn(
                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                preferences.criticalBusDispatches ? 'bg-kora dark:bg-gold' : 'bg-muted-foreground/30',
                            )}
                        >
                            <span
                                className={cn(
                                    'pointer-events-none inline-block size-5 rounded-full bg-white shadow-sm transition-transform duration-200',
                                    preferences.criticalBusDispatches ? 'translate-x-5' : 'translate-x-0',
                                )}
                            />
                        </button>
                    </div>

                    <div className="flex items-center justify-between px-5 py-4">
                        <div className="space-y-0.5">
                            <span className="flex items-center gap-1.5 text-sm font-bold text-foreground">
                                <BellRing className="size-4 text-digital" />
                                <span>Notifications Web Push navigateur</span>
                            </span>
                            <p className="text-xs text-muted-foreground">
                                Réception des alertes même lorsque l'onglet KORA n'est pas actif.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={preferences.pushAlerts}
                            onClick={() => toggleNotification('pushAlerts')}
                            className={cn(
                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                preferences.pushAlerts ? 'bg-kora dark:bg-gold' : 'bg-muted-foreground/30',
                            )}
                        >
                            <span
                                className={cn(
                                    'pointer-events-none inline-block size-5 rounded-full bg-white shadow-sm transition-transform duration-200',
                                    preferences.pushAlerts ? 'translate-x-5' : 'translate-x-0',
                                )}
                            />
                        </button>
                    </div>

                    <div className="flex items-center justify-between px-5 py-4">
                        <div className="space-y-0.5">
                            <span className="flex items-center gap-1.5 text-sm font-bold text-foreground">
                                <Shield className="size-4 text-emerald-500" />
                                <span>Clôture de caisse & états journaliers</span>
                            </span>
                            <p className="text-xs text-muted-foreground">
                                Notification lors de la validation des bordereaux d'encaissement et de reversement.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={preferences.cashierDailyReconciliation}
                            onClick={() => toggleNotification('cashierDailyReconciliation')}
                            className={cn(
                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                preferences.cashierDailyReconciliation ? 'bg-kora dark:bg-gold' : 'bg-muted-foreground/30',
                            )}
                        >
                            <span
                                className={cn(
                                    'pointer-events-none inline-block size-5 rounded-full bg-white shadow-sm transition-transform duration-200',
                                    preferences.cashierDailyReconciliation ? 'translate-x-5' : 'translate-x-0',
                                )}
                            />
                        </button>
                    </div>
                </div>
            </section>

            {/* 3. Paramètres régionaux */}
            <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                        <div>
                            <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                Paramètres régionaux
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Contexte linguistique et devise légale.
                            </p>
                        </div>
                    </div>
                </header>

                <div className="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                        <Label className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                            <Languages className="size-3.5 text-muted-foreground" />
                            <span>Langue de travail</span>
                        </Label>
                        <div className="flex items-center justify-between rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                            <div className="flex items-center gap-2">
                                <Languages className="size-4 text-digital" />
                                <span>Français (Bénin)</span>
                            </div>
                            <Badge variant="outline" className="text-[10px] font-mono">Défaut</Badge>
                        </div>
                        <span className="block text-[11px] leading-4 text-muted-foreground">
                            Langue appliquée aux libellés d'interface et états d'embarquement.
                        </span>
                    </div>

                    <div className="space-y-1.5">
                        <Label className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                            <DollarSign className="size-3.5 text-muted-foreground" />
                            <span>Devise d'exploitation</span>
                        </Label>
                        <div className="flex items-center justify-between rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                            <div className="flex items-center gap-2">
                                <DollarSign className="size-4 text-emerald-500" />
                                <span>Franc CFA (XOF / FCFA)</span>
                            </div>
                            <Badge variant="outline" className="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 border-emerald-500/30">UEMOA</Badge>
                        </div>
                        <span className="block text-[11px] leading-4 text-muted-foreground">
                            Monnaie des tarifs, reçus de vente et clôtures de caisse.
                        </span>
                    </div>
                </div>
            </section>
        </div>
    );
}

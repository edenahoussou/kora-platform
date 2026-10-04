import { useForm } from '@inertiajs/react';
import { KeyRound, Lock, LockKeyhole, ShieldAlert } from 'lucide-react';
import { useMemo, useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { SettingsSection } from '@/components/administration/settings/settings-ui';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import ManagePasskeys from '@/components/manage-passkeys';
import ManageTwoFactor from '@/components/manage-two-factor';
import { PanelAlert } from '@/components/panel-alert';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { UserSecurityData } from './profile-types';

interface ProfileSecurityTabProps {
    security: UserSecurityData;
}

/**
 * Onglet Sécurité, Double Facteur & Passkeys KORA OPS.
 */
export function ProfileSecurityTab({ security }: ProfileSecurityTabProps) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const passwordEvaluation = useMemo(() => {
        const hasLength = form.data.password.length >= 8;
        const hasUpper = /[A-Z]/.test(form.data.password);
        const hasNumber = /[0-9]/.test(form.data.password);
        const hasSpecial = /[^A-Za-z0-9]/.test(form.data.password);

        const score = [hasLength, hasUpper, hasNumber, hasSpecial].filter(
            Boolean,
        ).length;

        let label = 'Faible';
        let colorClass = 'bg-destructive';
        let textColor = 'text-destructive';

        if (score === 2) {
            label = 'Moyen';
            colorClass = 'bg-amber-500';
            textColor = 'text-amber-500';
        } else if (score === 3) {
            label = 'Robuste';
            colorClass = 'bg-digital';
            textColor = 'text-digital';
        } else if (score === 4) {
            label = 'Excellent';
            colorClass = 'bg-emerald-500';
            textColor = 'text-emerald-500';
        }

        return { score, label, colorClass, textColor };
    }, [form.data.password]);

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        form.put(SecurityController.update.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () => {
                if (form.errors.password) {
                    passwordInput.current?.focus();
                }

                if (form.errors.current_password) {
                    currentPasswordInput.current?.focus();
                }

                form.reset(
                    'password',
                    'password_confirmation',
                    'current_password',
                );
            },
        });
    };

    return (
        <div className="space-y-6">
            {/* Alerte contextuelle de politique de sécurité */}
            <PanelAlert tone="warning" icon={ShieldAlert}>
                <span className="font-bold">Politique de sécurité KORA : </span>
                Les mots de passe des comptes superviseurs doivent être
                renouvelés régulièrement. L'activation du double facteur (2FA)
                est obligatoire pour les opérations sensibles et les caisses.
            </PanelAlert>

            {/* 1. Mot de passe */}
            <SettingsSection
                title="Mot de passe de connexion"
                description="Définissez une phrase secrète complexe pour protéger votre session de travail."
                actions={
                    security.lastPasswordChange ? (
                        <KoraStatusBadge
                            label={`Renouvelé ${security.lastPasswordChange}`}
                            tone="muted"
                        />
                    ) : undefined
                }
            >
                <form onSubmit={handlePasswordSubmit} className="space-y-5">
                    {/* Mot de passe actuel */}
                    <div className="max-w-md space-y-1.5">
                        <Label
                            htmlFor="current_password"
                            className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                        >
                            <LockKeyhole className="size-3.5 text-muted-foreground" />
                            <span>Mot de passe actuel</span>
                            <span className="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-amber-700 dark:text-amber-300">
                                Requis
                            </span>
                        </Label>
                        <PasswordInput
                            id="current_password"
                            ref={currentPasswordInput}
                            value={form.data.current_password}
                            onChange={(e) =>
                                form.setData('current_password', e.target.value)
                            }
                            placeholder="••••••••••••"
                            autoComplete="current-password"
                            required
                            aria-invalid={Boolean(form.errors.current_password)}
                            className="h-10 rounded-[10px] font-mono"
                        />
                        {form.errors.current_password ? (
                            <span className="text-[11px] font-semibold text-destructive">
                                {form.errors.current_password}
                            </span>
                        ) : (
                            <span className="block text-[11px] leading-4 text-muted-foreground">
                                Indispensable pour confirmer votre identité
                                avant d'enregistrer la nouvelle phrase de passe.
                            </span>
                        )}
                    </div>

                    {/* Nouveau mot de passe & Confirmation */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label
                                htmlFor="password"
                                className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                            >
                                <KeyRound className="size-3.5 text-muted-foreground" />
                                <span>Nouveau mot de passe</span>
                                <span className="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-amber-700 dark:text-amber-300">
                                    Requis
                                </span>
                            </Label>
                            <PasswordInput
                                id="password"
                                ref={passwordInput}
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                                placeholder="Nouveau mot de passe"
                                autoComplete="new-password"
                                required
                                aria-invalid={Boolean(form.errors.password)}
                                className="h-10 rounded-[10px] font-mono"
                            />
                            {form.errors.password ? (
                                <span className="text-[11px] font-semibold text-destructive">
                                    {form.errors.password}
                                </span>
                            ) : (
                                <span className="block text-[11px] leading-4 text-muted-foreground">
                                    Au moins 8 caractères incluant majuscules,
                                    chiffres et caractères spéciaux.
                                </span>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label
                                htmlFor="password_confirmation"
                                className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                            >
                                <Lock className="size-3.5 text-muted-foreground" />
                                <span>
                                    Confirmation du nouveau mot de passe
                                </span>
                                <span className="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-amber-700 dark:text-amber-300">
                                    Requis
                                </span>
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                value={form.data.password_confirmation}
                                onChange={(e) =>
                                    form.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                placeholder="Retapez le mot de passe"
                                autoComplete="new-password"
                                required
                                aria-invalid={Boolean(
                                    form.errors.password_confirmation,
                                )}
                                className="h-10 rounded-[10px] font-mono"
                            />
                            {form.errors.password_confirmation ? (
                                <span className="text-[11px] font-semibold text-destructive">
                                    {form.errors.password_confirmation}
                                </span>
                            ) : (
                                <span className="block text-[11px] leading-4 text-muted-foreground">
                                    Retapez à l'identique pour éviter toute
                                    faute de frappe accidentelle.
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Jauge de robustesse */}
                    {form.data.password.length > 0 && (
                        <div className="space-y-2 rounded-xl border border-border bg-muted/30 p-3.5">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-muted-foreground">
                                    Indicateur de complexité :
                                </span>
                                <span
                                    className={cn(
                                        'font-bold',
                                        passwordEvaluation.textColor,
                                    )}
                                >
                                    {passwordEvaluation.label}
                                </span>
                            </div>

                            <div className="grid h-1.5 grid-cols-4 gap-1.5 overflow-hidden rounded-full bg-muted">
                                {[1, 2, 3, 4].map((step) => (
                                    <div
                                        key={step}
                                        className={cn(
                                            'h-full rounded-full transition-all duration-300',
                                            passwordEvaluation.score >= step
                                                ? passwordEvaluation.colorClass
                                                : 'bg-transparent',
                                        )}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end pt-2">
                        <Button
                            type="submit"
                            variant="kora"
                            size="kora"
                            disabled={form.processing || !form.data.password}
                        >
                            <KeyRound className="size-3.5" />
                            {form.processing
                                ? 'Mise à jour en cours…'
                                : 'Mettre à jour le mot de passe'}
                        </Button>
                    </div>
                </form>
            </SettingsSection>

            {/* 2. Double facteur (2FA / TOTP) — Fortify */}
            {security.canManageTwoFactor && (
                <SettingsSection
                    title="Double facteur (2FA / TOTP)"
                    description="Code de validation temporaire à 6 chiffres via application mobile."
                    actions={
                        <KoraStatusBadge
                            label={
                                security.twoFactorEnabled
                                    ? '2FA activé'
                                    : '2FA inactif'
                            }
                            tone={security.twoFactorEnabled ? 'blue' : 'muted'}
                        />
                    }
                >
                    <ManageTwoFactor
                        canManageTwoFactor={security.canManageTwoFactor}
                        requiresConfirmation={security.requiresConfirmation}
                        twoFactorEnabled={security.twoFactorEnabled}
                    />
                </SettingsSection>
            )}

            {/* 3. Passkeys & Biométrie — Fortify */}
            {security.canManagePasskeys && (
                <SettingsSection
                    title="Clés de sécurité & Passkeys (FIDO2)"
                    description="Authentification cryptographique sans mot de passe via biométrie locale ou clé matérielle."
                >
                    <ManagePasskeys
                        canManagePasskeys={security.canManagePasskeys}
                        passkeys={security.passkeys}
                    />
                </SettingsSection>
            )}
        </div>
    );
}

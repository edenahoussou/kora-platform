import {
    Check,
    Copy,
    Eye,
    EyeOff,
    Fingerprint,
    KeyRound,
    Lock,
    LockKeyhole,
    QrCode,
    RefreshCw,
    ShieldAlert,
    Smartphone,
    Trash2,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { PanelAlert } from '@/components/panel-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { UserPasskeyItem } from './profile-mock-data';
import {
    INITIAL_PASSKEYS_DATA,
    INITIAL_SECURITY_STATUS,
} from './profile-mock-data';

/**
 * Onglet Sécurité, Double Facteur & Passkeys KORA OPS.
 */
export function ProfileSecurityTab() {
    // Password state
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isSavingPassword, setIsSavingPassword] = useState(false);

    // 2FA state
    const twoFactorEnabled = INITIAL_SECURITY_STATUS.twoFactorEnabled;
    const [showQrModal, setShowQrModal] = useState(false);
    const [showRecoveryModal, setShowRecoveryModal] = useState(false);
    const [copiedKey, setCopiedKey] = useState(false);
    const [recoveryCodes, setRecoveryCodes] = useState([
        'KORA-98A1-42F7',
        'KORA-B3C8-11E9',
        'KORA-74D2-88FA',
        'KORA-E501-99C3',
        'KORA-12FE-4490',
        'KORA-67AA-22B1',
        'KORA-8809-FF43',
        'KORA-33D1-9870',
    ]);

    // Passkeys state
    const [passkeys, setPasskeys] =
        useState<UserPasskeyItem[]>(INITIAL_PASSKEYS_DATA);
    const [isAddingPasskey, setIsAddingPasskey] = useState(false);

    // Password strength logic
    const passwordEvaluation = useMemo(() => {
        const hasLength = newPassword.length >= 8;
        const hasUpper = /[A-Z]/.test(newPassword);
        const hasNumber = /[0-9]/.test(newPassword);
        const hasSpecial = /[^A-Za-z0-9]/.test(newPassword);

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

        return {
            score,
            label,
            colorClass,
            textColor,
            hasLength,
            hasUpper,
            hasNumber,
            hasSpecial,
        };
    }, [newPassword]);

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (newPassword !== confirmPassword) {
            toast.error('Erreur de confirmation', {
                description: 'Les deux mots de passe saisis ne correspondent pas.',
            });

            return;
        }

        if (passwordEvaluation.score < 2) {
            toast.error('Mot de passe trop vulnérable', {
                description: 'Veuillez respecter au moins 2 critères de complexité.',
            });

            return;
        }

        setIsSavingPassword(true);
        setTimeout(() => {
            setIsSavingPassword(false);
            setCurrentPassword('');
            setNewPassword('');
            setConfirmPassword('');
            toast.success('Mot de passe mis à jour avec succès', {
                description: 'Votre nouvelle phrase de passe a été chiffrée et enregistrée.',
            });
        }, 400);
    };

    const handleCopySecretKey = () => {
        navigator.clipboard.writeText(INITIAL_SECURITY_STATUS.secretKey);
        setCopiedKey(true);
        toast.success('Clé secrète copiée');
        setTimeout(() => setCopiedKey(false), 2000);
    };

    const handleAddPasskey = () => {
        setIsAddingPasskey(true);
        setTimeout(() => {
            const newKey: UserPasskeyItem = {
                id: `pk_${Date.now()}`,
                name: 'Clé de sécurité FIDO2 / Touch ID',
                authenticator: 'Navigateur Web / Biométrie matérielle',
                createdAt: 'À l\'instant',
                lastUsedAt: 'Jamais',
                type: 'security_key',
            };
            setPasskeys((prev) => [newKey, ...prev]);
            setIsAddingPasskey(false);
            toast.success('Passkey enregistrée', {
                description: 'Votre nouvelle clé biométrique a été liée à votre compte.',
            });
        }, 500);
    };

    return (
        <div className="space-y-6">
            {/* Alerte contextuelle de politique de sécurité */}
            <PanelAlert tone="warning" icon={ShieldAlert}>
                <span className="font-bold">Politique de sécurité KORA : </span>
                Les mots de passe des comptes superviseurs doivent être renouvelés régulièrement. L'activation du double facteur (2FA) est obligatoire pour les opérations sensibles et les caisses.
            </PanelAlert>

            {/* 1. Mot de passe */}
            <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                        <div>
                            <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                Mot de passe de connexion
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Définissez une phrase secrète complexe pour protéger votre session de travail.
                            </p>
                        </div>
                    </div>
                    <Badge variant="outline" className="border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-mono text-[10px]">
                        Actif (renouvelé il y a 45j)
                    </Badge>
                </header>

                <form onSubmit={handlePasswordSubmit} className="p-5 sm:p-6 space-y-5">
                    {/* Mot de passe actuel */}
                    <div className="max-w-md space-y-1.5">
                        <Label htmlFor="current_password" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                            <LockKeyhole className="size-3.5 text-muted-foreground" />
                            <span>Mot de passe actuel</span>
                            <span className="rounded-full bg-amber-500/10 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 dark:text-amber-300">
                                Requis
                            </span>
                        </Label>
                        <div className="relative">
                            <Input
                                id="current_password"
                                type={showCurrentPassword ? 'text' : 'password'}
                                value={currentPassword}
                                onChange={(e) => setCurrentPassword(e.target.value)}
                                placeholder="••••••••••••"
                                required
                                className="h-10 rounded-xl pr-10 font-mono"
                            />
                            <button
                                type="button"
                                onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                className="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                tabIndex={-1}
                            >
                                {showCurrentPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                        <span className="block text-[11px] leading-4 text-muted-foreground">
                            Indispensable pour confirmer votre identité avant d'enregistrer la nouvelle phrase de passe.
                        </span>
                    </div>

                    {/* Nouveau mot de passe & Confirmation */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="new_password" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                <KeyRound className="size-3.5 text-muted-foreground" />
                                <span>Nouveau mot de passe</span>
                                <span className="rounded-full bg-amber-500/10 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 dark:text-amber-300">
                                    Requis
                                </span>
                            </Label>
                            <div className="relative">
                                <Input
                                    id="new_password"
                                    type={showNewPassword ? 'text' : 'password'}
                                    value={newPassword}
                                    onChange={(e) => setNewPassword(e.target.value)}
                                    placeholder="Nouveau mot de passe"
                                    required
                                    className="h-10 rounded-xl pr-10 font-mono"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowNewPassword(!showNewPassword)}
                                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                    tabIndex={-1}
                                >
                                    {showNewPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                            <span className="block text-[11px] leading-4 text-muted-foreground">
                                Au moins 8 caractères incluant majuscules, chiffres et caractères spéciaux.
                            </span>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="confirm_password" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                <Lock className="size-3.5 text-muted-foreground" />
                                <span>Confirmation du nouveau mot de passe</span>
                                <span className="rounded-full bg-amber-500/10 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 dark:text-amber-300">
                                    Requis
                                </span>
                            </Label>
                            <div className="relative">
                                <Input
                                    id="confirm_password"
                                    type={showConfirmPassword ? 'text' : 'password'}
                                    value={confirmPassword}
                                    onChange={(e) => setConfirmPassword(e.target.value)}
                                    placeholder="Retapez le mot de passe"
                                    required
                                    className="h-10 rounded-xl pr-10 font-mono"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                    tabIndex={-1}
                                >
                                    {showConfirmPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                            <span className="block text-[11px] leading-4 text-muted-foreground">
                                Retapez à l'identique pour éviter toute faute de frappe accidentelle.
                            </span>
                        </div>
                    </div>

                    {/* Jauge de robustesse */}
                    {newPassword.length > 0 && (
                        <div className="rounded-xl border border-border bg-muted/30 p-3.5 space-y-2">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-muted-foreground">Indicateur de complexité :</span>
                                <span className={cn('font-bold', passwordEvaluation.textColor)}>
                                    {passwordEvaluation.label}
                                </span>
                            </div>

                            <div className="grid grid-cols-4 gap-1.5 h-1.5 rounded-full overflow-hidden bg-muted">
                                {[1, 2, 3, 4].map((step) => (
                                    <div
                                        key={step}
                                        className={cn(
                                            'h-full transition-all duration-300 rounded-full',
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
                            disabled={isSavingPassword || !newPassword}
                            className="rounded-xl bg-kora text-white hover:bg-[#002d75] dark:bg-gold dark:text-kora text-xs font-bold px-5"
                        >
                            <KeyRound className="mr-1.5 size-4" />
                            {isSavingPassword ? 'Mise à jour en cours...' : 'Mettre à jour le mot de passe'}
                        </Button>
                    </div>
                </form>
            </section>

            {/* 2. Authentification 2FA */}
            <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                        <div>
                            <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                Double facteur (2FA / TOTP)
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Code de validation temporaire à 6 chiffres via application mobile.
                            </p>
                        </div>
                    </div>
                    <Badge variant="outline" className={twoFactorEnabled ? 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-mono text-[10px]' : 'text-[10px]'}>
                        {twoFactorEnabled ? '2FA Activé' : '2FA Inactif'}
                    </Badge>
                </header>

                <div className="p-5 sm:p-6 space-y-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-border bg-muted/40 p-4">
                        <div className="flex items-center gap-3">
                            <Smartphone className="size-5 text-digital shrink-0" />
                            <div className="space-y-0.5">
                                <span className="block text-sm font-semibold text-foreground">
                                    Générateur de code (Application TOTP)
                                </span>
                                <span className="text-xs text-muted-foreground block">
                                    Utilisez Google Authenticator, Microsoft Authenticator ou 1Password lors de la connexion.
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 shrink-0">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setShowQrModal(true)}
                                className="rounded-xl text-xs"
                            >
                                <QrCode className="mr-1.5 size-3.5 text-digital" />
                                Afficher le QR Code
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setShowRecoveryModal(true)}
                                className="rounded-xl text-xs"
                            >
                                <Lock className="mr-1.5 size-3.5" />
                                Codes de secours ({recoveryCodes.length})
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            {/* 3. Passkeys & Biométrie */}
            <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                        <div>
                            <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                Clés de sécurité & Passkeys (FIDO2)
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Authentification cryptographique sans mot de passe via biométrie locale ou clé matérielle.
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={isAddingPasskey}
                        onClick={handleAddPasskey}
                        className="rounded-xl text-xs"
                    >
                        <Fingerprint className="mr-1.5 size-3.5 text-digital" />
                        {isAddingPasskey ? 'Enregistrement...' : 'Ajouter une passkey'}
                    </Button>
                </header>

                <div className="divide-y divide-border/60">
                    {passkeys.map((pk) => (
                        <div key={pk.id} className="flex items-center justify-between px-5 py-4 text-xs">
                            <div className="flex items-center gap-3">
                                <span className="grid size-8 place-items-center rounded-lg bg-muted text-foreground">
                                    <Fingerprint className="size-4 text-digital" />
                                </span>
                                <div>
                                    <span className="block font-semibold text-foreground text-sm">
                                        {pk.name}
                                    </span>
                                    <span className="text-muted-foreground text-[11px]">
                                        {pk.authenticator} • {pk.lastUsedAt}
                                    </span>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setPasskeys((prev) => prev.filter((k) => k.id !== pk.id));
                                    toast.info('Passkey révoquée');
                                }}
                                className="rounded-lg text-xs text-muted-foreground hover:text-destructive"
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </div>
                    ))}
                </div>
            </section>

            {/* QR Code Modal */}
            <Dialog open={showQrModal} onOpenChange={setShowQrModal}>
                <DialogContent className="max-w-sm rounded-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">QR Code de configuration TOTP</DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Scannez ce code dans votre application d'authentification.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-4 flex flex-col items-center justify-center gap-4">
                        <div className="rounded-xl border border-border bg-white p-3 shadow-xs">
                            <QRCodeSVG value={INITIAL_SECURITY_STATUS.qrCodeValue} size={160} />
                        </div>
                        <div className="w-full flex items-center justify-between rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs font-mono">
                            <span className="truncate">{INITIAL_SECURITY_STATUS.secretKey}</span>
                            <button
                                type="button"
                                onClick={handleCopySecretKey}
                                className="ml-2 text-muted-foreground hover:text-foreground"
                            >
                                {copiedKey ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
                            </button>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" onClick={() => setShowQrModal(false)} className="w-full rounded-xl text-xs">
                            Terminer la configuration
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Recovery Codes Modal */}
            <Dialog open={showRecoveryModal} onOpenChange={setShowRecoveryModal}>
                <DialogContent className="max-w-md rounded-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Codes de secours d'urgence</DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Conservez ces codes dans un gestionnaire de mots de passe (1Password, Bitwarden).
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-3 grid grid-cols-2 gap-2 rounded-xl border border-border bg-muted/30 p-3 font-mono text-xs">
                        {recoveryCodes.map((code, idx) => (
                            <div key={idx} className="rounded-lg border border-border bg-background p-2 text-center font-bold">
                                {code}
                            </div>
                        ))}
                    </div>

                    <DialogFooter className="flex justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                const generated = Array.from({ length: 8 }, () => {
                                    const r1 = Math.random().toString(16).substring(2, 6).toUpperCase();
                                    const r2 = Math.random().toString(16).substring(2, 6).toUpperCase();

                                    return `KORA-${r1}-${r2}`;
                                });
                                setRecoveryCodes(generated);
                                toast.success('Nouveaux codes générés');
                            }}
                            className="rounded-xl text-xs"
                        >
                            <RefreshCw className="mr-1.5 size-3.5" />
                            Régénérer
                        </Button>
                        <Button type="button" size="sm" onClick={() => setShowRecoveryModal(false)} className="rounded-xl text-xs">
                            Fermer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

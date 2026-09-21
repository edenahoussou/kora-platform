import {
    Archive,
    Building2,
    Calendar,
    Edit3,
    KeyRound,
    Lock,
    Mail,
    Phone,
    RefreshCw,
    RotateCcw,
    Shield,
    Unlock,
    X,
} from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type { AdministrationUser } from '@/types/administration';

type Props = {
    open: boolean;
    user: AdministrationUser | null;
    canManage?: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: (user: AdministrationUser) => void;
    onPasswordReset: (user: AdministrationUser) => void;
    onPinReset: (user: AdministrationUser) => void;
    onUnlock: (user: AdministrationUser) => void;
    onArchive: (user: AdministrationUser) => void;
    onRestore: (user: AdministrationUser) => void;
};

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('fr-FR', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

export function UserDetailDrawer({
    open,
    user,
    canManage = false,
    onOpenChange,
    onEdit,
    onPasswordReset,
    onPinReset,
    onUnlock,
    onArchive,
    onRestore,
}: Props) {
    if (!open || !user) {
        return null;
    }

    const isArchived = Boolean(user.deleted_at);
    const isLocked = user.status === 'locked';

    const statusTone = isArchived
        ? 'muted'
        : isLocked
          ? 'destructive'
          : user.status === 'suspended'
            ? 'gold'
            : 'blue';

    const statusLabel = isArchived
        ? 'Archivé'
        : isLocked
          ? 'Verrouillé (Tentatives dépassées)'
          : user.status === 'suspended'
            ? 'Suspendu'
            : 'Actif';

    return (
        <div className="fixed inset-0 z-50 overflow-hidden" role="presentation">
            {/* Backdrop */}
            <div
                className="companies-panel-backdrop absolute inset-0 bg-[rgba(0,29,78,0.3)] backdrop-blur-[9px]"
                onClick={() => onOpenChange(false)}
            />

            {/* Sliding Panel */}
            <aside
                className="companies-panel absolute top-0 right-0 flex h-full w-full max-w-[34rem] flex-col border-l border-border bg-card shadow-[-20px_0_60px_rgba(0,29,78,0.18)]"
                role="dialog"
                aria-modal="true"
                aria-label={`Détail utilisateur ${user.name}`}
            >
                {/* Header */}
                <header className="flex items-start justify-between gap-4 border-b border-border px-6 py-5">
                    <div className="flex items-center gap-3.5">
                        <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-gold/20 text-sm font-black text-gold">
                            {getInitials(user.name)}
                        </span>
                        <div>
                            <span className="section-kicker">
                                Compte utilisateur
                            </span>
                            <h3 className="mt-0.5 text-lg font-black tracking-tight text-foreground">
                                {user.name}
                            </h3>
                            <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Mail className="size-3 text-muted-foreground" />
                                {user.email}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <KoraStatusBadge
                            label={statusLabel}
                            tone={statusTone}
                        />
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className="cash-modal-close"
                            aria-label="Fermer"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                </header>

                {/* Body Content */}
                <div className="flex-1 space-y-6 overflow-y-auto px-6 py-6">
                    {/* Alerte si compte verrouillé */}
                    {isLocked && (
                        <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-xs text-destructive">
                            <div className="flex items-start gap-3">
                                <Lock className="mt-0.5 size-4 shrink-0" />
                                <div>
                                    <strong className="block font-bold">
                                        Compte verrouillé
                                    </strong>
                                    <p className="mt-0.5 text-[0.6875rem] leading-relaxed">
                                        Ce compte a été verrouillé suite à
                                        plusieurs tentatives d’authentification
                                        erronées. L’agent ne peut plus se
                                        connecter sur le web ou l’application
                                        P501.
                                    </p>
                                    {canManage && (
                                        <button
                                            type="button"
                                            onClick={() => onUnlock(user)}
                                            className="btn btn-primary mt-3 gap-1.5 text-xs font-black shadow-md"
                                        >
                                            <Unlock className="size-3.5" />
                                            Déverrouiller le compte
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Alerte si archivé */}
                    {isArchived && (
                        <div className="rounded-xl border border-border bg-muted/40 p-4 text-xs text-muted-foreground">
                            <div className="flex items-start gap-3">
                                <Archive className="mt-0.5 size-4 shrink-0" />
                                <div>
                                    <strong className="block font-bold text-foreground">
                                        Utilisateur archivé
                                    </strong>
                                    <p className="mt-0.5 text-[0.6875rem] leading-relaxed">
                                        Ce compte a été désactivé et archivé le{' '}
                                        {formatDate(user.deleted_at)}. Les
                                        historiques de vente et de transactions
                                        sont conservés.
                                    </p>
                                    {canManage && (
                                        <button
                                            type="button"
                                            onClick={() => onRestore(user)}
                                            className="btn btn-ghost mt-3 gap-1.5 text-xs"
                                        >
                                            <RotateCcw className="size-3.5 text-gold" />
                                            Restaurer cet utilisateur
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Fiche récapitulative Bento */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Shield className="size-3.5 text-gold" />
                                <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                    Rôle & Accès
                                </span>
                            </div>
                            <p className="mt-2 text-sm font-extrabold text-foreground">
                                {user.role?.label ?? 'Aucun rôle'}
                            </p>
                            <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                Périmètre :{' '}
                                {user.role?.category === 'central'
                                    ? 'Direction Centrale'
                                    : user.role?.category === 'agency'
                                      ? 'Agence Locale'
                                      : 'Équipage Terrain'}
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Building2 className="size-3.5 text-gold" />
                                <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                    Affectation
                                </span>
                            </div>
                            <p className="mt-2 text-sm font-extrabold text-foreground">
                                {user.agency
                                    ? user.agency.name
                                    : 'Siège Central'}
                            </p>
                            <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                {user.agency
                                    ? `Code site : ${user.agency.code}`
                                    : 'Réseau Global'}
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Phone className="size-3.5 text-gold" />
                                <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                    Téléphone
                                </span>
                            </div>
                            <p className="mt-2 font-mono text-sm font-extrabold text-foreground">
                                {user.phone ? user.phone : 'Non renseigné'}
                            </p>
                            <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                Indicatif :{' '}
                                {user.country_code ? user.country_code : 'BJ'}
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Calendar className="size-3.5 text-gold" />
                                <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                    Naissance
                                </span>
                            </div>
                            <p className="mt-2 text-sm font-extrabold text-foreground">
                                {formatDate(user.birth_date)}
                            </p>
                            <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                Donnée d’état civil
                            </span>
                        </div>
                    </div>

                    {/* Actions de Sécurité */}
                    {canManage && (
                        <div className="space-y-3 pt-2">
                            <h4 className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                Actions d’administration
                            </h4>

                            <div className="grid gap-2">
                                {!isArchived && (
                                    <>
                                        <button
                                            type="button"
                                            onClick={() => onEdit(user)}
                                            className="flex w-full items-center justify-between rounded-xl border border-border bg-card p-3 text-left transition hover:bg-muted/40"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="grid size-8 place-items-center rounded-lg bg-muted text-foreground">
                                                    <Edit3 className="size-4" />
                                                </span>
                                                <div>
                                                    <span className="block text-xs font-bold text-foreground">
                                                        Modifier le profil
                                                    </span>
                                                    <small className="text-[0.6875rem] text-muted-foreground">
                                                        Changer nom, email,
                                                        téléphone, rôle ou
                                                        agence
                                                    </small>
                                                </div>
                                            </div>
                                            <span className="text-xs font-bold text-gold">
                                                Éditer →
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                onPasswordReset(user)
                                            }
                                            className="flex w-full items-center justify-between rounded-xl border border-border bg-card p-3 text-left transition hover:bg-muted/40"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="grid size-8 place-items-center rounded-lg bg-gold/15 text-gold">
                                                    <KeyRound className="size-4" />
                                                </span>
                                                <div>
                                                    <span className="block text-xs font-bold text-foreground">
                                                        Réinitialiser le mot de
                                                        passe
                                                    </span>
                                                    <small className="text-[0.6875rem] text-muted-foreground">
                                                        Génère un nouveau mot de
                                                        passe temporaire
                                                    </small>
                                                </div>
                                            </div>
                                            <span className="text-xs font-bold text-gold">
                                                Réinitialiser →
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => onPinReset(user)}
                                            className="flex w-full items-center justify-between rounded-xl border border-border bg-card p-3 text-left transition hover:bg-muted/40"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="grid size-8 place-items-center rounded-lg bg-blue-500/15 text-blue-500">
                                                    <RefreshCw className="size-4" />
                                                </span>
                                                <div>
                                                    <span className="block text-xs font-bold text-foreground">
                                                        Réinitialiser le code
                                                        PIN POS
                                                    </span>
                                                    <small className="text-[0.6875rem] text-muted-foreground">
                                                        Déclenche un défi OTP
                                                        vers le téléphone de
                                                        l’agent
                                                    </small>
                                                </div>
                                            </div>
                                            <span className="text-xs font-bold text-blue-500">
                                                Envoyer OTP →
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => onArchive(user)}
                                            className="flex w-full items-center justify-between rounded-xl border border-destructive/20 bg-destructive/5 p-3 text-left transition hover:bg-destructive/10"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="grid size-8 place-items-center rounded-lg bg-destructive/15 text-destructive">
                                                    <Archive className="size-4" />
                                                </span>
                                                <div>
                                                    <span className="block text-xs font-bold text-destructive">
                                                        Archiver le compte
                                                    </span>
                                                    <small className="text-[0.6875rem] text-muted-foreground">
                                                        Désactive l’accès sans
                                                        supprimer les données
                                                        historiques
                                                    </small>
                                                </div>
                                            </div>
                                            <span className="text-xs font-bold text-destructive">
                                                Archiver
                                            </span>
                                        </button>
                                    </>
                                )}

                                {isArchived && (
                                    <button
                                        type="button"
                                        onClick={() => onRestore(user)}
                                        className="flex w-full items-center justify-between rounded-xl border border-emerald-500/25 bg-emerald-500/5 p-3 text-left transition hover:bg-emerald-500/10"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="grid size-8 place-items-center rounded-lg bg-emerald-500/15 text-emerald-600">
                                                <RotateCcw className="size-4" />
                                            </span>
                                            <div>
                                                <span className="block text-xs font-bold text-emerald-600">
                                                    Restaurer le compte
                                                </span>
                                                <small className="text-[0.6875rem] text-muted-foreground">
                                                    Réactive l’utilisateur et
                                                    restaure ses droits
                                                </small>
                                            </div>
                                        </div>
                                        <span className="text-xs font-bold text-emerald-600">
                                            Restaurer
                                        </span>
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer Bar */}
                <footer className="flex items-center justify-between border-t border-border bg-muted/10 px-6 py-4">
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="btn btn-ghost text-xs"
                    >
                        Fermer
                    </button>
                    {canManage && !isArchived && (
                        <button
                            type="button"
                            onClick={() => onEdit(user)}
                            className="btn btn-primary gap-1.5 text-xs font-black shadow-md"
                        >
                            <Edit3 className="size-3.5" />
                            Modifier
                        </button>
                    )}
                </footer>
            </aside>
        </div>
    );
}

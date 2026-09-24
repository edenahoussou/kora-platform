import { useHttp } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Check,
    ChevronDown,
    Handshake,
    Laptop,
    ShieldCheck,
    UserRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    destructiveActions,
    errorMessage,
    terminalActionRoute,
} from '@/components/terminals/shared';
import type {
    AgencySummary,
    TerminalSummary,
    UserCandidate,
} from '@/components/terminals/shared';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

type ActionPayload = Record<string, string | null>;

function UserBadges({ user }: { user: UserCandidate }) {
    return (
        <span className="flex shrink-0 items-center gap-1">
            {user.role && (
                <span className="rounded-full bg-muted px-2 py-0.5 text-[0.5625rem] font-extrabold whitespace-nowrap text-muted-foreground ring-1 ring-border ring-inset">
                    {user.role.label}
                </span>
            )}
            {user.agency && (
                <span className="rounded-full bg-digital/10 px-2 py-0.5 text-[0.5625rem] font-extrabold whitespace-nowrap text-digital ring-1 ring-digital/25 ring-inset">
                    {user.agency.name}
                </span>
            )}
        </span>
    );
}

const actionMessages: Record<string, { title: string; description: string }> = {
    activate: {
        title: 'Activer ce terminal',
        description: 'Le terminal pourra à nouveau ouvrir une session OPS.',
    },
    deactivate: {
        title: 'Désactiver ce terminal',
        description: 'Les sessions actives seront révoquées immédiatement.',
    },
    maintenance: {
        title: 'Mettre en maintenance',
        description:
            'Le terminal ne pourra plus effectuer d’opérations terrain.',
    },
    lost: {
        title: 'Déclarer le terminal perdu',
        description:
            'Le terminal sera bloqué et ses sessions seront révoquées.',
    },
    recover: {
        title: 'Récupérer ce terminal',
        description: 'Une récupération explicite sera inscrite dans l’audit.',
    },
    unassign: {
        title: 'Désaffecter ce terminal',
        description:
            'L’affectation courante sera clôturée, sans supprimer son historique.',
    },
    stock: {
        title: 'Retourner au stock',
        description:
            'Le terminal sera désactivé et retiré de toute affectation.',
    },
    revoke: {
        title: 'Révoquer les sessions',
        description: 'Toutes les sessions OPS actives seront fermées.',
    },
    assign: {
        title: 'Affecter le terminal',
        description: 'Choisissez l’utilisateur et/ou l’agence destinataire.',
    },
};

export function TerminalActionModal({
    action,
    terminal,
    users,
    agencies,
    onClose,
    onSuccess,
}: {
    action: string;
    terminal: TerminalSummary;
    users: UserCandidate[];
    agencies: AgencySummary[];
    onClose: () => void;
    onSuccess: (message: string) => void;
}) {
    const [reason, setReason] = useState('');
    const [selectedUserUuid, setSelectedUserUuid] = useState(
        terminal.assigned_user?.uuid ?? '',
    );
    const [selectedAgencyUuid, setSelectedAgencyUuid] = useState(
        terminal.agency?.uuid ?? '',
    );
    const [showAllUsers, setShowAllUsers] = useState(() => {
        const assignedUser = users.find(
            (user) => user.uuid === terminal.assigned_user?.uuid,
        );

        return Boolean(
            terminal.agency &&
            assignedUser &&
            assignedUser.agency?.uuid !== terminal.agency.uuid,
        );
    });
    const [modalError, setModalError] = useState<string | null>(null);
    const {
        submit: submitAction,
        setData: setActionData,
        processing: actionProcessing,
        errors: actionErrors,
    } = useHttp<ActionPayload, unknown>({});

    const messages = actionMessages[action] ?? {
        title: 'Action terminal',
        description: 'Confirmez l’opération sur ce terminal.',
    };
    const actionDestructive = destructiveActions.includes(action);
    const needsReason = [
        'lost',
        'recover',
        'unassign',
        'stock',
        'revoke',
    ].includes(action);
    const needsAssignment = action === 'assign' || action === 'recover';

    const agencyUsers = selectedAgencyUuid
        ? users.filter((user) => user.agency?.uuid === selectedAgencyUuid)
        : users;
    const visibleUsers =
        selectedAgencyUuid && !showAllUsers ? agencyUsers : users;
    const selectedAgency = agencies.find(
        (agency) => agency.uuid === selectedAgencyUuid,
    );
    const selectedUser = users.find((user) => user.uuid === selectedUserUuid);

    const onAgencyChange = (uuid: string) => {
        setSelectedAgencyUuid(uuid);
        setShowAllUsers(false);

        if (uuid && selectedUserUuid) {
            const current = users.find(
                (user) => user.uuid === selectedUserUuid,
            );

            if (current && current.agency?.uuid !== uuid) {
                setSelectedUserUuid('');
            }
        }
    };

    const close = () => {
        if (!actionProcessing) {
            onClose();
        }
    };

    const execute = async () => {
        setModalError(null);
        const payload: ActionPayload = {};

        if (needsReason) {
            payload.reason = reason.trim() || null;
        }

        if (needsAssignment) {
            payload.assigned_user_uuid = selectedUserUuid || null;
            payload.agency_uuid = selectedAgencyUuid || null;

            if (
                action === 'assign' &&
                !payload.assigned_user_uuid &&
                !payload.agency_uuid
            ) {
                setModalError('Choisissez un utilisateur ou une agence.');

                return;
            }
        }

        setActionData(payload);

        try {
            await submitAction(terminalActionRoute(action, terminal.uuid));
            onSuccess('Le terminal a été mis à jour.');
        } catch (error) {
            setModalError(errorMessage(error));
        }
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && close()
            }
        >
            <div
                className="cash-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="terminal-action-title"
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span
                            className={cn(
                                'cash-opening-icon',
                                actionDestructive && 'bg-destructive/15!',
                            )}
                        >
                            {actionDestructive ? (
                                <AlertTriangle className="size-5 text-destructive" />
                            ) : action === 'assign' ? (
                                <Handshake className="size-5" />
                            ) : (
                                <ShieldCheck className="size-5" />
                            )}
                        </span>
                        <div>
                            <span className="section-kicker">
                                Action terminal
                            </span>
                            <h3 id="terminal-action-title">{messages.title}</h3>
                            <p>{messages.description}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={close}
                        className="cash-modal-close"
                        aria-label="Fermer"
                        disabled={actionProcessing}
                    >
                        <X className="size-4" />
                    </button>
                </div>

                {needsAssignment && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-3 rounded-xl border border-border bg-muted/40 px-3.5 py-3">
                            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-card text-muted-foreground shadow-[inset_0_0_0_1px_var(--border)]">
                                <Laptop className="size-4" />
                            </span>
                            <div className="min-w-0">
                                <span className="block text-[0.625rem] font-black tracking-[0.12em] text-muted-foreground uppercase">
                                    Affectation actuelle
                                </span>
                                <span className="mt-0.5 block truncate text-xs font-bold text-foreground">
                                    {terminal.assigned_user?.name ??
                                        'Aucun utilisateur'}{' '}
                                    · {terminal.agency?.name ?? 'Aucune agence'}
                                </span>
                            </div>
                        </div>

                        <label className="field">
                            <span className="flex items-center gap-1.5">
                                <Building2 className="size-3.5 text-gold" />
                                Agence de rattachement
                                {action === 'assign' && (
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                )}
                            </span>
                            <select
                                className="control"
                                value={selectedAgencyUuid}
                                onChange={(event) =>
                                    onAgencyChange(event.target.value)
                                }
                            >
                                <option value="">Aucune agence</option>
                                {agencies.map((agency) => (
                                    <option
                                        key={agency.uuid}
                                        value={agency.uuid}
                                    >
                                        {agency.name}
                                    </option>
                                ))}
                            </select>
                            <small className="text-[0.65rem] font-semibold text-muted-foreground">
                                Propriétaire logistique de l’équipement dans le
                                parc.
                            </small>
                        </label>

                        <div className="field">
                            <span className="flex items-center gap-1.5">
                                <UserRound className="size-3.5 text-gold" />
                                Utilisateur autorisé
                                {action === 'assign' && (
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                )}
                            </span>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="control flex items-center gap-2 text-left"
                                    >
                                        {selectedUser ? (
                                            <>
                                                <span className="min-w-0 flex-1 truncate">
                                                    {selectedUser.name}
                                                </span>
                                                <UserBadges
                                                    user={selectedUser}
                                                />
                                            </>
                                        ) : (
                                            <span className="flex-1 text-muted-foreground">
                                                Aucun utilisateur
                                            </span>
                                        )}
                                        <ChevronDown className="size-3.5 shrink-0 text-muted-foreground" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    className="z-[110] max-h-64 w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto rounded-xl p-1.5"
                                >
                                    <DropdownMenuItem
                                        onSelect={() => setSelectedUserUuid('')}
                                        className="text-xs font-bold text-muted-foreground"
                                    >
                                        Aucun utilisateur
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    {visibleUsers.length === 0 && (
                                        <DropdownMenuItem
                                            disabled
                                            className="text-xs"
                                        >
                                            Aucun utilisateur dans cette agence
                                        </DropdownMenuItem>
                                    )}
                                    {visibleUsers.map((user) => (
                                        <DropdownMenuItem
                                            key={user.uuid}
                                            onSelect={() =>
                                                setSelectedUserUuid(user.uuid)
                                            }
                                            className="gap-2.5 py-2"
                                        >
                                            <span
                                                className={cn(
                                                    'grid size-4 shrink-0 place-items-center rounded-full border',
                                                    user.uuid ===
                                                        selectedUserUuid
                                                        ? 'border-gold bg-gold/15 text-gold'
                                                        : 'border-border text-transparent',
                                                )}
                                            >
                                                <Check className="size-2.5" />
                                            </span>
                                            <span className="min-w-0 flex-1 truncate text-xs font-bold">
                                                {user.name}
                                            </span>
                                            <UserBadges user={user} />
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <small className="text-[0.65rem] font-semibold text-muted-foreground">
                                Seul ce compte pourra ouvrir une session OPS sur
                                le terminal.
                            </small>
                            {actionErrors.assigned_user_id && (
                                <small className="field-error-text font-semibold text-destructive">
                                    {actionErrors.assigned_user_id}
                                </small>
                            )}
                        </div>

                        {selectedAgency && (
                            <div className="flex items-center justify-between gap-3 rounded-lg bg-muted/60 px-3 py-2 text-[0.6875rem] font-semibold text-muted-foreground">
                                <span>
                                    {showAllUsers
                                        ? `${visibleUsers.length} utilisateur(s) — tout le réseau`
                                        : `${agencyUsers.length} utilisateur(s) dans ${selectedAgency.name}`}
                                </span>
                                <button
                                    type="button"
                                    className="font-bold text-digital hover:underline"
                                    onClick={() =>
                                        setShowAllUsers(!showAllUsers)
                                    }
                                >
                                    {showAllUsers
                                        ? `Filtrer sur ${selectedAgency.name}`
                                        : 'Afficher tout le réseau'}
                                </button>
                            </div>
                        )}

                        <div className="rounded-r-xl border-l-[3px] border-gold bg-muted/60 px-4 py-3">
                            <span className="text-[0.625rem] font-black tracking-[0.13em] text-digital uppercase">
                                Deux rôles indépendants
                            </span>
                            <p className="mt-1 text-[0.6875rem] leading-5 text-foreground/80">
                                L’agence porte la logistique du parc ;
                                l’utilisateur porte l’accès OPS. Choisissez au
                                moins une destination.
                            </p>
                        </div>
                    </div>
                )}

                {needsReason && (
                    <label className="field mt-3">
                        <span>
                            Motif
                            {action === 'recover' && (
                                <b className="font-black text-destructive">
                                    {' '}
                                    *
                                </b>
                            )}
                        </span>
                        <textarea
                            className="control h-auto min-h-20 py-2.5"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            placeholder="Saisissez le motif de l’action…"
                            required={action === 'recover'}
                            maxLength={255}
                        />
                        {actionErrors.reason && (
                            <small className="field-error-text font-semibold text-destructive">
                                {actionErrors.reason}
                            </small>
                        )}
                    </label>
                )}

                {modalError && (
                    <div className="cash-form-error" role="alert">
                        {modalError}
                    </div>
                )}

                <div className="cash-modal-actions">
                    <button
                        type="button"
                        onClick={close}
                        className="btn btn-ghost"
                        disabled={actionProcessing}
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        onClick={() => void execute()}
                        className={cn(
                            'btn font-black',
                            actionDestructive
                                ? 'border-transparent bg-destructive text-white hover:bg-destructive/90'
                                : 'btn-primary',
                        )}
                        disabled={actionProcessing}
                    >
                        {actionProcessing ? 'Traitement…' : 'Confirmer'}
                    </button>
                </div>
            </div>
        </div>
    );
}

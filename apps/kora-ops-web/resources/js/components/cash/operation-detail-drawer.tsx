import { router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    Calendar,
    Check,
    CheckCheck,
    CircleAlert,
    Clock,
    Download,
    Expand,
    FileCheck,
    Hash,
    History,
    Hourglass,
    ImageIcon,
    Receipt,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
    User,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    formatDate,
    formatDateTime,
    formatOperationEvent,
    networkBrands,
    networkLabels,
    operationChannelLabel,
    StatusPill,
} from '@/components/cash/cash-shared';
import type {
    CashOperationRow,
    CashSessionLedger,
} from '@/components/cash/cash-shared';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type {
    SaleCashMedia,
    SaleCashSession,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { approveVariance, exportMethod as exportCash } from '@/routes/cash';
import { approve, reject } from '@/routes/cash/movements';
import { forceClose } from '@/routes/cash/sessions';

const lifecycleDescriptions: Record<string, string> = {
    session_opened:
        'L’agent a ouvert sa caisse et déclaré les soldes initiaux de chaque sous-journal.',
    session_submitted:
        'La clôture a été soumise avec les soldes constatés au comptage.',
    session_closed:
        'La session a été clôturée et ses soldes définitivement validés.',
    session_force_closed:
        'Un superviseur a clôturé cette session de force, sans comptage.',
    variance_approved:
        'L’écart constaté à la clôture a été approuvé par un responsable.',
};

export type CashSessionDetail = Omit<SaleCashSession, 'ledgers'> & {
    uuid: string;
    status: string;
    ledgers: CashSessionLedger[];
    openedAt?: string | null;
    submittedAt?: string | null;
    closedAt?: string | null;
    approvedAt?: string | null;
    approvedByName?: string | null;
    forceClosedAt?: string | null;
    forceClosedByName?: string | null;
    closingComment?: string | null;
    agentName?: string | null;
    agencyName?: string | null;
    cashRegister?: { uuid: string; code: string; label: string } | null;
    isStale?: boolean;
    isLateClosing?: boolean;
    isForced?: boolean;
    permissions?: {
        canApproveVariance: boolean;
        canForceClose: boolean;
        canApproveMovement: boolean;
    };
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    operation: CashOperationRow | null;
    sessionDetail: CashSessionDetail | null;
    isLoadingSession?: boolean;
    canApproveMovement?: boolean;
    filters?: Record<string, string | number>;
};

type DrawerAction = {
    key: string;
    label: string;
    icon: typeof Check;
    tone: 'success' | 'primary' | 'danger';
    onClick: () => void;
};

export function OperationDetailDrawer({
    open,
    onOpenChange,
    operation,
    sessionDetail,
    isLoadingSession = false,
    canApproveMovement = false,
    filters = {},
}: Props) {
    const [manualTab, setManualTab] = useState<{
        key: string;
        tab: 'operation' | 'session';
    } | null>(null);
    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [forceCloseModalOpen, setForceCloseModalOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const runAction = (url: string) => {
        if (busy) {
            return;
        }

        setBusy(true);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBusy(false),
            },
        );
    };

    if (!operation) {
        return null;
    }

    const activeTab =
        manualTab?.key === operation.key
            ? manualTab.tab
            : operation.kind === 'lifecycle'
              ? 'session'
              : 'operation';
    const isMovement = operation.kind === 'movement';
    const isPendingMovement = isMovement && operation.status === 'pending';
    const isApprovedMovement = isMovement && operation.status === 'approved';
    const isRejectedMovement = isMovement && operation.status === 'rejected';

    const session = sessionDetail;

    const actions: DrawerAction[] = [];

    if (isPendingMovement && canApproveMovement) {
        actions.push(
            {
                key: 'approve-movement',
                label: 'Approuver le mouvement',
                icon: Check,
                tone: 'success',
                onClick: () => runAction(approve.url(operation.uuid)),
            },
            {
                key: 'reject-movement',
                label: 'Rejeter',
                icon: X,
                tone: 'danger',
                onClick: () => setRejectModalOpen(true),
            },
        );
    }

    if (
        session?.status === 'pending_approval' &&
        session.permissions?.canApproveVariance
    ) {
        actions.push({
            key: 'approve-variance',
            label: 'Approuver l’écart',
            icon: CheckCheck,
            tone: 'primary',
            onClick: () => runAction(approveVariance.url(session.uuid)),
        });
    }

    if (session?.status === 'open' && session.permissions?.canForceClose) {
        actions.push({
            key: 'force-close',
            label: 'Clôturer de force',
            icon: ShieldAlert,
            tone: 'danger',
            onClick: () => setForceCloseModalOpen(true),
        });
    }

    const timeline = session
        ? [
              {
                  label: 'Ouverture',
                  at: session.openedAt ?? null,
                  detail: session.agentName ?? '',
                  done: session.openedAt != null,
              },
              {
                  label: 'Clôture soumise',
                  at: session.submittedAt ?? null,
                  detail: '',
                  done: session.submittedAt != null,
              },
              {
                  label: session.isForced ? 'Clôture forcée' : 'Clôture',
                  at: session.closedAt ?? session.forceClosedAt ?? null,
                  detail: session.isForced
                      ? `Par ${session.forceClosedByName ?? '—'}`
                      : session.isLateClosing
                        ? 'Clôture tardive'
                        : '',
                  done:
                      session.closedAt != null || session.forceClosedAt != null,
              },
              {
                  label: 'Écart approuvé',
                  at: session.approvedAt ?? null,
                  detail: session.approvedByName ?? '',
                  done: session.approvedAt != null,
              },
          ].filter(
              (step) =>
                  step.done ||
                  step.label === 'Ouverture' ||
                  session.status === 'open',
          )
        : [];

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent
                    side="right"
                    className="flex h-full w-full flex-col gap-0 border-l border-border bg-card p-0 shadow-[-20px_0_60px_rgba(0,29,78,0.18)] sm:max-w-[36rem]"
                >
                    <SheetTitle className="sr-only">
                        Détail de l’opération de caisse
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        Visualisez et gérez le détail de l’opération ainsi que
                        la session de caisse associée.
                    </SheetDescription>

                    {/* Header */}
                    <div className="border-b border-border bg-card px-6 py-5">
                        <div className="flex items-start justify-between gap-4">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <span className="section-kicker">
                                        Journal de caisse
                                    </span>
                                    <span className="text-muted-foreground/40">
                                        /
                                    </span>
                                    <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                        {isMovement
                                            ? 'Mouvement financier'
                                            : 'Événement de session'}
                                    </span>
                                </div>
                                <h3 className="mt-1 truncate text-lg font-black tracking-tight text-foreground">
                                    {formatOperationEvent(operation)}
                                </h3>
                                <div className="mt-1 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                    <span className="flex items-center gap-1">
                                        <Clock className="size-3 text-gold" />
                                        {formatDateTime(operation.occurredAt)}
                                    </span>
                                    {operation.agentName && (
                                        <span className="flex items-center gap-1">
                                            <User className="size-3 text-gold" />
                                            {operation.agentName}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {operation.status ? (
                                    <StatusPill status={operation.status} />
                                ) : operation.event ===
                                  'session_force_closed' ? (
                                    <KoraStatusBadge
                                        label="Forcée"
                                        tone="destructive"
                                    />
                                ) : null}
                            </div>
                        </div>

                        {/* Navigation Tabs */}
                        <div className="mt-4 flex gap-1 rounded-xl bg-muted/50 p-1">
                            <button
                                type="button"
                                onClick={() =>
                                    setManualTab({
                                        key: operation.key,
                                        tab: 'operation',
                                    })
                                }
                                className={cn(
                                    'flex flex-1 items-center justify-center gap-2 rounded-lg py-1.5 text-xs font-bold transition-all',
                                    activeTab === 'operation'
                                        ? 'bg-card text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Receipt className="size-3.5" /> Opération
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    setManualTab({
                                        key: operation.key,
                                        tab: 'session',
                                    })
                                }
                                className={cn(
                                    'flex flex-1 items-center justify-center gap-2 rounded-lg py-1.5 text-xs font-bold transition-all',
                                    activeTab === 'session'
                                        ? 'bg-card text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <History className="size-3.5" /> Session &
                                Journaux
                            </button>
                        </div>
                    </div>

                    {/* Body Content */}
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-6">
                        {activeTab === 'operation' ? (
                            <div className="space-y-6">
                                {/* Hero Operation Card */}
                                <div className="rounded-2xl border border-border bg-gradient-to-b from-muted/30 to-muted/10 p-5 shadow-xs">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <span
                                                className={cn(
                                                    'grid size-11 place-items-center rounded-2xl shadow-xs',
                                                    isMovement
                                                        ? operation.direction ===
                                                          'in'
                                                            ? 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20'
                                                            : 'bg-orange-500/15 text-orange-600 dark:bg-orange-500/20'
                                                        : 'bg-gold/15 text-gold',
                                                )}
                                            >
                                                {isMovement ? (
                                                    operation.direction ===
                                                    'in' ? (
                                                        <ArrowDownLeft
                                                            className="size-5"
                                                            strokeWidth={1.7}
                                                        />
                                                    ) : (
                                                        <ArrowUpRight
                                                            className="size-5"
                                                            strokeWidth={1.7}
                                                        />
                                                    )
                                                ) : (
                                                    <FileCheck
                                                        className="size-5"
                                                        strokeWidth={1.7}
                                                    />
                                                )}
                                            </span>
                                            <div>
                                                <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                                    {isMovement
                                                        ? operation.direction ===
                                                          'in'
                                                            ? 'Encaissement net'
                                                            : 'Sortie de fonds'
                                                        : 'Type d’événement'}
                                                </span>
                                                <p className="text-base font-black text-foreground">
                                                    {formatOperationEvent(
                                                        operation,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        {isMovement &&
                                            operation.amount !== null && (
                                                <div className="text-right">
                                                    <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                                        Montant
                                                    </span>
                                                    <p
                                                        className={cn(
                                                            'font-mono text-xl font-extrabold tracking-tight',
                                                            operation.direction ===
                                                                'in'
                                                                ? 'text-emerald-600'
                                                                : 'text-orange-600',
                                                        )}
                                                    >
                                                        {operation.direction ===
                                                        'in'
                                                            ? '+'
                                                            : '−'}
                                                        {formatMoney(
                                                            operation.amount,
                                                            'XOF',
                                                        )}
                                                    </p>
                                                </div>
                                            )}
                                    </div>
                                    {!isMovement && (
                                        <p className="mt-3 border-t border-black/[0.05] pt-3 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                            {lifecycleDescriptions[
                                                operation.event
                                            ] ??
                                                'Événement du cycle de vie de la session de caisse.'}
                                        </p>
                                    )}
                                </div>

                                {/* Contextual Alerts for Operations */}
                                {isPendingMovement && (
                                    <div className="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-foreground">
                                        <div className="flex items-start gap-3">
                                            <Hourglass className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                            <div className="flex-1">
                                                <strong className="block text-xs font-bold text-foreground">
                                                    Mouvement en attente de
                                                    validation
                                                </strong>
                                                <p className="mt-1 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                                    Ce reversement ou
                                                    décaissement est soumis à
                                                    contrôle. Il ne modifiera le
                                                    solde constaté qu’après
                                                    approbation d’un
                                                    responsable.
                                                    {canApproveMovement &&
                                                        ' Utilisez les actions en bas du panneau pour valider ou rejeter.'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {isApprovedMovement && (
                                    <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-foreground">
                                        <div className="flex items-start gap-3">
                                            <CheckCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                            <div>
                                                <strong className="block text-xs font-bold text-foreground">
                                                    Mouvement approuvé
                                                </strong>
                                                <p className="mt-0.5 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                                    Cette opération a été
                                                    validée et intégrée dans le
                                                    journal de caisse.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {isRejectedMovement && (
                                    <div className="rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-foreground">
                                        <div className="flex items-start gap-3">
                                            <ShieldAlert className="mt-0.5 size-4 shrink-0 text-destructive" />
                                            <div>
                                                <strong className="block text-xs font-bold text-destructive">
                                                    Mouvement rejeté
                                                </strong>
                                                <p className="mt-0.5 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                                    Cette opération a été
                                                    refusée lors du contrôle et
                                                    n’a pas impacté le solde de
                                                    caisse.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Bento Metadata Grid */}
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Calendar className="size-3.5 text-gold" />
                                            <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                                Date & Heure
                                            </span>
                                        </div>
                                        <p className="mt-2 text-sm font-extrabold text-foreground">
                                            {formatDateTime(
                                                operation.occurredAt,
                                            )}
                                        </p>
                                        <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                            Journée comptable :{' '}
                                            {formatDate(operation.businessDate)}
                                        </span>
                                    </div>

                                    <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            {operation.channel === 'cash' ? (
                                                <Banknote className="size-3.5 text-gold" />
                                            ) : (
                                                <Smartphone className="size-3.5 text-gold" />
                                            )}
                                            <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                                Canal de règlement
                                            </span>
                                        </div>
                                        <p className="mt-2 text-sm font-extrabold text-foreground">
                                            {operationChannelLabel(operation)}
                                        </p>
                                        <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                            {operation.channel === 'cash'
                                                ? 'Caisse physique'
                                                : (networkLabels[
                                                      operation.channel ?? ''
                                                  ] ?? 'Mobile Money')}
                                        </span>
                                    </div>

                                    <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Hash className="size-3.5 text-gold" />
                                            <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                                Référence
                                            </span>
                                        </div>
                                        <p className="mt-2 font-mono text-sm font-extrabold text-foreground">
                                            {operation.reference ?? '—'}
                                        </p>
                                        <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                            Identifiant interne
                                        </span>
                                    </div>

                                    <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <User className="size-3.5 text-gold" />
                                            <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                                                Agent opérateur
                                            </span>
                                        </div>
                                        <p className="mt-2 text-sm font-extrabold text-foreground">
                                            {operation.agentName ?? '—'}
                                        </p>
                                        <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                                            Responsable du mouvement
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            /* Tab Session */
                            <div className="space-y-6">
                                {isLoadingSession || !session ? (
                                    <div className="space-y-4">
                                        <Skeleton className="h-24 w-full rounded-2xl" />
                                        <div className="grid grid-cols-2 gap-3">
                                            <Skeleton className="h-20 rounded-xl" />
                                            <Skeleton className="h-20 rounded-xl" />
                                        </div>
                                        <Skeleton className="h-40 w-full rounded-2xl" />
                                    </div>
                                ) : (
                                    <>
                                        {/* Session Top Header Card */}
                                        <div className="rounded-2xl border border-border bg-gradient-to-b from-muted/30 to-muted/10 p-5 shadow-xs">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <span className="section-kicker">
                                                        Journée de caisse
                                                    </span>
                                                    <h4 className="mt-0.5 text-base font-black text-foreground">
                                                        Session du{' '}
                                                        {formatDate(
                                                            session.businessDate,
                                                        )}
                                                    </h4>
                                                    <p className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                        <span>
                                                            {session.agentName ??
                                                                '—'}
                                                        </span>
                                                        <span>·</span>
                                                        <span>
                                                            {session.agencyName ??
                                                                '—'}
                                                        </span>
                                                        {session.cashRegister && (
                                                            <>
                                                                <span>·</span>
                                                                <span>
                                                                    {
                                                                        session
                                                                            .cashRegister
                                                                            .code
                                                                    }{' '}
                                                                    {
                                                                        session
                                                                            .cashRegister
                                                                            .label
                                                                    }
                                                                </span>
                                                            </>
                                                        )}
                                                    </p>
                                                </div>
                                                <StatusPill
                                                    status={session.status}
                                                />
                                            </div>
                                        </div>

                                        {/* Session Contextual Alerts */}
                                        {(session.isStale ||
                                            session.isForced ||
                                            session.isLateClosing ||
                                            session.status ===
                                                'pending_approval') && (
                                            <div className="space-y-3">
                                                {session.isStale && (
                                                    <div className="flex items-start gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs">
                                                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                                        <div>
                                                            <strong className="block font-bold text-foreground">
                                                                Session non
                                                                clôturée
                                                            </strong>
                                                            <p className="mt-0.5 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                                                Cette session
                                                                est restée
                                                                ouverte après sa
                                                                journée. Elle
                                                                bloque la caisse
                                                                jusqu’à sa
                                                                clôture.
                                                                {session
                                                                    .permissions
                                                                    ?.canForceClose &&
                                                                    ' Vous pouvez la clôturer de force depuis la barre d’actions ci-dessous.'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}

                                                {session.isForced && (
                                                    <div className="flex items-start gap-3 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-xs">
                                                        <ShieldAlert className="mt-0.5 size-4 shrink-0 text-destructive" />
                                                        <div>
                                                            <strong className="block font-bold text-destructive">
                                                                Session clôturée
                                                                de force par{' '}
                                                                {session.forceClosedByName ??
                                                                    'un superviseur'}
                                                            </strong>
                                                            <p className="mt-0.5 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                                                Motif :{' '}
                                                                {session.closingComment ??
                                                                    'Aucun motif renseigné.'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}

                                                {!session.isForced &&
                                                    session.isLateClosing && (
                                                        <div className="flex items-start gap-3 rounded-2xl border border-blue-500/30 bg-blue-500/10 p-4 text-xs">
                                                            <Clock className="mt-0.5 size-4 shrink-0 text-blue-600" />
                                                            <div>
                                                                <strong className="block font-bold text-foreground">
                                                                    Clôture
                                                                    tardive
                                                                </strong>
                                                                <p className="mt-0.5 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                                                    La clôture a
                                                                    été
                                                                    enregistrée
                                                                    après la
                                                                    date
                                                                    initiale de
                                                                    la session.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}

                                                {session.status ===
                                                    'pending_approval' && (
                                                    <div className="flex items-start gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs">
                                                        <CircleAlert className="mt-0.5 size-4 shrink-0 text-gold" />
                                                        <div className="flex-1">
                                                            <strong className="block font-bold text-foreground">
                                                                Écart détecté à
                                                                la clôture
                                                            </strong>
                                                            <p className="mt-0.5 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                                                {session.closingComment
                                                                    ? `Commentaire : ${session.closingComment}`
                                                                    : 'En attente de validation par un responsable.'}
                                                                {session
                                                                    .permissions
                                                                    ?.canApproveVariance &&
                                                                    ' Vous pouvez approuver cet écart depuis la barre d’actions ci-dessous.'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Actions for Session */}
                                        <div className="flex flex-wrap items-center gap-2">
                                            <a
                                                href={exportCash.url({
                                                    query: {
                                                        ...filters,
                                                        session_uuid:
                                                            session.uuid,
                                                    },
                                                })}
                                                className="btn btn-ghost gap-1.5 border border-border text-xs font-bold text-foreground"
                                            >
                                                <Download className="size-3.5 text-gold" />
                                                Exporter le relevé (CSV)
                                            </a>
                                        </div>

                                        {/* Timeline */}
                                        <div className="rounded-2xl border border-border bg-muted/20 p-4">
                                            <span className="text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                                                Chronologie de la session
                                            </span>
                                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                                {timeline.map((step) => (
                                                    <div
                                                        key={step.label}
                                                        className={cn(
                                                            'flex items-center gap-2 rounded-xl border border-black/[0.04] px-3 py-2 text-xs',
                                                            step.done
                                                                ? 'bg-card text-foreground'
                                                                : 'bg-muted/40 text-muted-foreground opacity-60',
                                                        )}
                                                    >
                                                        <span
                                                            className={cn(
                                                                'grid size-4 place-items-center rounded-full',
                                                                step.done
                                                                    ? 'bg-emerald-500/15 text-emerald-600'
                                                                    : 'bg-muted text-muted-foreground',
                                                            )}
                                                        >
                                                            <Check className="size-2.5" />
                                                        </span>
                                                        <div>
                                                            <p className="text-[0.6875rem] font-bold">
                                                                {step.label}
                                                            </p>
                                                            <p className="text-[0.625rem] text-muted-foreground">
                                                                {step.done
                                                                    ? `${formatDateTime(step.at)}${step.detail ? ` · ${step.detail}` : ''}`
                                                                    : 'En attente'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        {/* Ledgers Breakdown Cards */}
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <span className="text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                                                    Sous-journaux & Comptes (
                                                    {session.ledgers.length})
                                                </span>
                                            </div>
                                            <div className="space-y-3">
                                                {session.ledgers.map(
                                                    (ledger) => (
                                                        <DrawerLedgerCard
                                                            key={ledger.uuid}
                                                            ledger={ledger}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Sticky Validation Actions */}
                    {actions.length > 0 && (
                        <div className="shrink-0 border-t border-border bg-muted/30 px-6 py-4">
                            <div className="mb-2.5 flex items-center gap-1.5">
                                <ShieldCheck className="size-3.5 text-gold" />
                                <span className="text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                    Actions de validation
                                </span>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {actions.map((action) => (
                                    <button
                                        key={action.key}
                                        type="button"
                                        disabled={busy}
                                        onClick={action.onClick}
                                        className={cn(
                                            'btn gap-1.5 text-xs font-black disabled:cursor-not-allowed disabled:opacity-55',
                                            action.tone === 'success' &&
                                                'btn-success',
                                            action.tone === 'primary' &&
                                                'btn-primary',
                                            action.tone === 'danger' &&
                                                'btn-danger',
                                        )}
                                    >
                                        <action.icon className="size-3.5" />
                                        {busy ? 'Traitement…' : action.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </SheetContent>
            </Sheet>

            {/* Modal de Rejet de Mouvement */}
            {rejectModalOpen && operation && (
                <RejectMovementModal
                    operationUuid={operation.uuid}
                    onClose={() => setRejectModalOpen(false)}
                />
            )}

            {/* Modal de Clôture Forcée */}
            {forceCloseModalOpen && session && (
                <ForceCloseModal
                    sessionUuid={session.uuid}
                    onClose={() => setForceCloseModalOpen(false)}
                />
            )}
        </>
    );
}

function DrawerLedgerCard({ ledger }: { ledger: CashSessionLedger }) {
    const isCash = ledger.kind === 'cash';
    const brand = isCash
        ? { key: 'cash', mark: '₣' }
        : (networkBrands[ledger.account?.network ?? ''] ?? {
              key: 'default',
              mark: 'MM',
          });

    return (
        <div className="rounded-2xl border border-border bg-card p-4 shadow-2xs">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    <span className="grid size-8 place-items-center rounded-xl bg-gold/15 text-xs font-black text-gold">
                        {brand.mark}
                    </span>
                    <div>
                        <p className="text-xs font-black text-foreground">
                            {isCash
                                ? 'Espèces'
                                : (networkLabels[
                                      ledger.account?.network ?? ''
                                  ] ?? 'Mobile Money')}
                        </p>
                        <small className="text-[0.625rem] text-muted-foreground">
                            {isCash
                                ? 'Caisse physique'
                                : (ledger.account?.accountIdentifier ??
                                  ledger.account?.label)}
                        </small>
                    </div>
                </div>
                <StatusPill status={ledger.status} />
            </div>

            <div className="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-muted/30 p-2.5 text-center text-xs">
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground uppercase">
                        Ouverture
                    </span>
                    <strong className="font-mono text-xs font-bold text-foreground">
                        {formatMoney(ledger.openingBalance, 'XOF')}
                    </strong>
                </div>
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground uppercase">
                        Entrées
                    </span>
                    <strong className="font-mono text-xs font-bold text-emerald-600">
                        +{formatMoney(ledger.inflows, 'XOF')}
                    </strong>
                </div>
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground uppercase">
                        Sorties
                    </span>
                    <strong className="font-mono text-xs font-bold text-orange-600">
                        −{formatMoney(ledger.outflows, 'XOF')}
                    </strong>
                </div>
            </div>

            <div className="mt-2.5 grid grid-cols-3 gap-2 border-t border-black/[0.04] pt-2.5 text-center text-xs">
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground uppercase">
                        Attendu
                    </span>
                    <strong className="font-mono text-xs font-bold text-foreground">
                        {formatMoney(ledger.expectedBalance, 'XOF')}
                    </strong>
                </div>
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground uppercase">
                        Constaté
                    </span>
                    <strong className="font-mono text-xs font-bold text-foreground">
                        {ledger.actualBalance !== null
                            ? formatMoney(ledger.actualBalance, 'XOF')
                            : 'Non contrôlé'}
                    </strong>
                </div>
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground uppercase">
                        Écart
                    </span>
                    <strong
                        className={cn(
                            'font-mono text-xs font-bold',
                            ledger.variance !== null && ledger.variance !== 0
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {ledger.variance !== null
                            ? formatMoney(ledger.variance, 'XOF')
                            : '—'}
                    </strong>
                </div>
            </div>

            {/* Media Evidences */}
            <div className="mt-3 space-y-2 border-t border-black/[0.04] pt-2">
                <EvidenceGallery
                    title="Preuve d’ouverture"
                    media={ledger.openingMedia ?? []}
                />
                <EvidenceGallery
                    title="Preuve de clôture"
                    media={ledger.closingMedia ?? []}
                />
            </div>
        </div>
    );
}

function EvidenceGallery({
    title,
    media,
}: {
    title: string;
    media: SaleCashMedia[];
}) {
    const [lightbox, setLightbox] = useState<SaleCashMedia | null>(null);

    if (media.length === 0) {
        return null;
    }

    return (
        <div>
            <span className="text-[0.625rem] font-bold tracking-wider text-muted-foreground uppercase">
                {title}
            </span>
            <div className="mt-1.5 flex flex-wrap gap-2">
                {media.map((item) =>
                    item.thumbUrl ? (
                        <button
                            key={item.uuid}
                            type="button"
                            onClick={() => setLightbox(item)}
                            className="group relative size-16 shrink-0 overflow-hidden rounded-xl border border-border bg-muted/40 transition hover:border-gold/50 hover:ring-2 hover:ring-gold/30"
                            aria-label={`Agrandir ${item.name}`}
                        >
                            <img
                                src={item.thumbUrl}
                                alt={item.name}
                                loading="lazy"
                                className="size-full object-cover"
                            />
                            <span className="absolute inset-0 grid place-items-center bg-black/0 opacity-0 transition group-hover:bg-black/35 group-hover:opacity-100">
                                <Expand className="size-4 text-white" />
                            </span>
                        </button>
                    ) : (
                        <a
                            key={item.uuid}
                            href={item.url}
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-1.5 rounded-lg border border-border bg-muted/40 px-2.5 py-1.5 text-[0.6875rem] font-bold text-foreground transition-colors hover:bg-muted"
                        >
                            <ImageIcon className="size-3.5 text-gold" />
                            <span className="max-w-[140px] truncate">
                                {item.name}
                            </span>
                        </a>
                    ),
                )}
            </div>
            {lightbox && (
                <EvidenceLightbox
                    item={lightbox}
                    onClose={() => setLightbox(null)}
                />
            )}
        </div>
    );
}

function EvidenceLightbox({
    item,
    onClose,
}: {
    item: SaleCashMedia;
    onClose: () => void;
}) {
    return (
        <div
            className="fixed inset-0 z-[110] grid place-items-center bg-black/75 p-6 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={onClose}
        >
            <button
                type="button"
                onClick={onClose}
                className="absolute top-4 right-4 grid size-9 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/25"
                aria-label="Fermer la visionneuse"
            >
                <X className="size-4" />
            </button>
            <figure
                className="max-w-full"
                onClick={(event) => event.stopPropagation()}
            >
                <img
                    src={item.previewUrl ?? item.url}
                    alt={item.name}
                    className="max-h-[80vh] max-w-[85vw] rounded-2xl object-contain shadow-2xl"
                />
                <figcaption className="mt-3 flex items-center justify-between gap-4 text-[0.6875rem] font-semibold text-white/80">
                    <span className="truncate">{item.name}</span>
                    <a
                        href={item.url}
                        target="_blank"
                        rel="noreferrer"
                        className="flex shrink-0 items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1.5 text-white transition hover:bg-white/25"
                    >
                        <Download className="size-3.5" /> Original
                    </a>
                </figcaption>
            </figure>
        </div>
    );
}

function RejectMovementModal({
    operationUuid,
    onClose,
}: {
    operationUuid: string;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });
    const valid = form.data.reason.trim().length > 0;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!valid || form.processing) {
            return;
        }

        form.post(reject.url(operationUuid), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal"
                role="dialog"
                aria-modal="true"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Validation</span>
                        <h3>Rejeter le mouvement</h3>
                        <p>
                            Indiquez le motif du rejet. L’opération passera au
                            statut rejeté et ne modifiera pas le solde de la
                            caisse.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <label className="field mt-4 block">
                    <span>
                        Motif du rejet <b>*</b>
                    </span>
                    <textarea
                        className="control min-h-20"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        placeholder="Ex. : justificatif illisible, montant erroné…"
                    />
                    {form.errors.reason && (
                        <small className="mt-1 block text-destructive">
                            {form.errors.reason}
                        </small>
                    )}
                </label>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onClose}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-danger"
                        disabled={!valid || form.processing}
                    >
                        {form.processing ? 'Rejet…' : 'Confirmer le rejet'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function ForceCloseModal({
    sessionUuid,
    onClose,
}: {
    sessionUuid: string;
    onClose: () => void;
}) {
    const form = useForm<{ reason: string }>({ reason: '' });
    const valid = form.data.reason.trim().length >= 5;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!valid || form.processing) {
            return;
        }

        form.post(forceClose.url(sessionUuid), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal"
                role="dialog"
                aria-modal="true"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Supervision</span>
                        <h3>Clôturer la session de force</h3>
                        <p>
                            La session sera fermée immédiatement sans comptage.
                            Les mouvements en attente seront rejetés. Cette
                            action est tracée et auditée.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <label className="field mt-4 block">
                    <span>
                        Motif de la clôture forcée <b>*</b>
                    </span>
                    <textarea
                        className="control min-h-20"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        placeholder="Ex. : agent absent, caisse restée ouverte la veille…"
                    />
                    {form.errors.reason && (
                        <small className="mt-1 block text-destructive">
                            {form.errors.reason}
                        </small>
                    )}
                </label>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onClose}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-danger"
                        disabled={!valid || form.processing}
                    >
                        {form.processing
                            ? 'Clôture…'
                            : 'Confirmer la clôture forcée'}
                    </button>
                </div>
            </form>
        </div>
    );
}

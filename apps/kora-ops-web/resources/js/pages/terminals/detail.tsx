import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    History,
    Laptop,
    Package,
    Power,
    PowerOff,
    RotateCcw,
    ShieldCheck,
    ShieldOff,
    SlidersHorizontal,
    UserMinus,
    UserRound,
    Wifi,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import {
    ActionRow,
    connectivityLabels,
    connectivityTones,
    EmptyLine,
    formatDate,
    formatRelative,
    InfoBentoCard,
    statusLabels,
    statusTones,
} from '@/components/terminals/shared';
import type {
    AgencySummary,
    AssignmentRecord,
    HistoryEvent,
    HistoryTypeOption,
    Pagination,
    TerminalSession,
    TerminalSummary,
    UserCandidate,
} from '@/components/terminals/shared';
import { TerminalActionModal } from '@/components/terminals/terminal-action-modal';
import { cn } from '@/lib/utils';
import { terminals as terminalsIndexRoute } from '@/routes';
import * as terminalsRoutes from '@/routes/terminals';
import type { BreadcrumbItem } from '@/types';

type DetailTab = 'identity' | 'assignment' | 'security' | 'history';

type DetailPageProps = {
    terminal: TerminalSummary;
    assignmentHistory: AssignmentRecord[];
    sessions: TerminalSession[];
    history: Pagination<HistoryEvent>;
    historyTypes: HistoryTypeOption[];
    historyFilters: { event_type: string; from: string; to: string };
    statuses: Record<string, string>;
    agencies: AgencySummary[];
    users: UserCandidate[];
    permissions: { canManage: boolean };
};

const tabs: Array<{ key: DetailTab; label: string; icon: typeof Laptop }> = [
    { key: 'identity', label: 'Identité', icon: Laptop },
    { key: 'assignment', label: 'Affectation', icon: UserRound },
    { key: 'security', label: 'Sécurité', icon: ShieldCheck },
    { key: 'history', label: 'Historique', icon: History },
];

function Panel({
    title,
    sub,
    children,
    className,
}: {
    title: string;
    sub?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]',
                className,
            )}
        >
            <header className="flex items-start gap-3 border-b border-border/70 px-5 py-4">
                <span className="mt-[5px] h-4 w-1 shrink-0 rounded-full bg-gold" />
                <div>
                    <h3 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                        {title}
                    </h3>
                    {sub && (
                        <p className="mt-1 text-[11px] leading-5 text-muted-foreground">
                            {sub}
                        </p>
                    )}
                </div>
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

function Timeline({ events }: { events: HistoryEvent[] }) {
    if (events.length === 0) {
        return <EmptyLine text="Aucun événement enregistré." />;
    }

    return (
        <div>
            {events.map((event, index) => (
                <div
                    key={`${event.type}-${event.occurred_at}-${index}`}
                    className="relative pb-5 pl-7 last:pb-0"
                >
                    {index < events.length - 1 && (
                        <span className="absolute top-4 bottom-0 left-[9px] w-px bg-border" />
                    )}
                    <span className="absolute top-1 left-0 grid size-[19px] place-items-center rounded-full border-[3px] border-card bg-digital shadow-[0_0_0_1px_var(--digital)]" />
                    <strong className="block text-xs text-foreground">
                        {event.summary}
                    </strong>
                    <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                        {formatDate(event.occurred_at)}
                        {event.actor ? ` · ${event.actor.name}` : ' · Système'}
                    </small>
                </div>
            ))}
        </div>
    );
}

function IdentityTab({ terminal }: { terminal: TerminalSummary }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <InfoBentoCard
                icon={Laptop}
                label="Numéro de série"
                value={terminal.serial_number}
                hint="P501 physique"
            />
            <InfoBentoCard
                icon={Package}
                label="Modèle matériel"
                value={terminal.hardware_model ?? 'Non remonté'}
                hint={terminal.os_version ?? 'Version Android non remontée'}
            />
            <InfoBentoCard
                icon={Activity}
                label="Version application"
                value={terminal.app_version ?? 'Non remontée'}
                hint={
                    terminal.version_status.status === 'not_tracked'
                        ? 'Build OPS embarqué'
                        : `${terminal.version_status.label}${
                              terminal.version_status.latest_version
                                  ? ` · dernière publiée ${terminal.version_status.latest_version}`
                                  : ''
                          }`
                }
            />
            <InfoBentoCard
                icon={Wifi}
                label="Dernière présence"
                value={formatRelative(terminal.last_seen_at)}
                hint={formatDate(terminal.last_seen_at)}
            />
            <InfoBentoCard
                icon={Activity}
                label="Dernière synchro"
                value={formatRelative(terminal.last_sync_at)}
                hint={formatDate(terminal.last_sync_at)}
            />
            <InfoBentoCard
                icon={CheckCircle2}
                label="Enregistré le"
                value={formatDate(terminal.created_at)}
                hint="Date d’inscription au parc"
            />
        </div>
    );
}

function AssignmentTab({
    terminal,
    assignmentHistory,
    canManage,
    onAction,
}: {
    terminal: TerminalSummary;
    assignmentHistory: AssignmentRecord[];
    canManage: boolean;
    onAction: (action: string) => void;
}) {
    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2">
                <InfoBentoCard
                    icon={UserRound}
                    label="Utilisateur courant"
                    value={terminal.assigned_user?.name ?? 'Non affecté'}
                    hint={
                        terminal.assigned_user
                            ? 'Compte OPS en session'
                            : 'Aucun compte rattaché'
                    }
                />
                <InfoBentoCard
                    icon={Building2}
                    label="Agence courante"
                    value={terminal.agency?.name ?? 'Non affectée'}
                    hint={
                        terminal.agency
                            ? `Code site : ${terminal.agency.code ?? '—'}`
                            : 'Stock / sans agence'
                    }
                />
            </div>

            {canManage && (
                <div className="space-y-3">
                    <h4 className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                        Actions d’affectation
                    </h4>
                    <div className="grid gap-2">
                        <ActionRow
                            icon={UserRound}
                            label="Affecter / réaffecter"
                            hint="Choisir l’utilisateur et/ou l’agence destinataire"
                            cta="Affecter"
                            tone="gold"
                            onClick={() => onAction('assign')}
                        />
                        {(terminal.assigned_user || terminal.agency) && (
                            <ActionRow
                                icon={UserMinus}
                                label="Désaffecter"
                                hint="Clôturer l’affectation courante sans perdre l’historique"
                                cta="Désaffecter"
                                onClick={() => onAction('unassign')}
                            />
                        )}
                        {terminal.status !== 'lost' && (
                            <ActionRow
                                icon={Package}
                                label="Retour au stock"
                                hint="Désactiver le terminal et retirer toute affectation"
                                cta="Retourner"
                                tone="destructive"
                                onClick={() => onAction('stock')}
                            />
                        )}
                    </div>
                </div>
            )}

            <div>
                <h4 className="flex items-center gap-2 text-xs font-black tracking-wider text-muted-foreground uppercase">
                    <UserRound className="size-3.5 text-gold" />
                    Historique des affectations
                </h4>
                <div className="mt-3 space-y-2">
                    {assignmentHistory.length === 0 ? (
                        <EmptyLine text="Aucune affectation enregistrée." />
                    ) : (
                        assignmentHistory.map((assignment) => (
                            <div
                                key={assignment.uuid}
                                className="rounded-xl border border-border bg-muted/20 p-3.5 text-xs"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <strong className="text-foreground">
                                        {assignment.assigned_user?.name ??
                                            assignment.agency?.name ??
                                            'Affectation vide'}
                                    </strong>
                                    <span className="text-muted-foreground">
                                        {formatDate(assignment.assigned_at)}
                                    </span>
                                </div>
                                <p className="mt-1 text-muted-foreground">
                                    {assignment.unassigned_at
                                        ? `Clôturée le ${formatDate(assignment.unassigned_at)}`
                                        : 'Affectation active'}
                                </p>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}

function SecurityTab({
    terminal,
    sessions,
    canManage,
    onAction,
}: {
    terminal: TerminalSummary;
    sessions: TerminalSession[];
    canManage: boolean;
    onAction: (action: string) => void;
}) {
    const controls: Array<[string, string, string]> = [
        [
            'Numéro de série',
            terminal.serial_number,
            statusLabels[terminal.status],
        ],
        [
            'Sessions actives',
            String(sessions.length),
            sessions.length > 0 ? 'Active' : 'Aucune',
        ],
        ['Révocation distante', 'Disponible', 'Prête'],
        ['Dernier handshake', formatDate(terminal.last_handshake_at), 'Tracé'],
        ['Dernière synchro', formatDate(terminal.last_sync_at), 'Tracée'],
    ];

    return (
        <div className="space-y-6">
            <div className="overflow-hidden rounded-xl border border-border">
                <table className="w-full text-left text-xs">
                    <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-2.5">Contrôle</th>
                            <th className="px-4 py-2.5">Valeur</th>
                            <th className="px-4 py-2.5 text-right">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        {controls.map(([control, value, state]) => (
                            <tr
                                key={control}
                                className="border-t border-black/[0.045]"
                            >
                                <td className="px-4 py-2.5 font-bold text-foreground">
                                    {control}
                                </td>
                                <td className="px-4 py-2.5 font-mono text-muted-foreground">
                                    {value}
                                </td>
                                <td className="px-4 py-2.5 text-right">
                                    <span className="font-bold text-digital">
                                        {state}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {sessions.length > 0 && (
                <div>
                    <h4 className="flex items-center gap-2 text-xs font-black tracking-wider text-muted-foreground uppercase">
                        <ShieldCheck className="size-3.5 text-gold" />
                        Sessions OPS actives
                    </h4>
                    <div className="mt-3 space-y-2">
                        {sessions.map((session) => (
                            <div
                                key={session.uuid}
                                className="rounded-xl border border-border bg-muted/20 p-3.5 text-xs"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <strong className="text-foreground">
                                        {session.user?.name ?? 'Session OPS'}
                                    </strong>
                                    <span className="font-bold text-digital">
                                        Active
                                    </span>
                                </div>
                                <p className="mt-1 text-muted-foreground">
                                    Ouverte le {formatDate(session.started_at)}{' '}
                                    · vue le {formatDate(session.last_seen_at)}{' '}
                                    · expire le {formatDate(session.expires_at)}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {canManage && (
                <div className="space-y-3">
                    <h4 className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                        Actions d’administration
                    </h4>
                    <div className="grid gap-2">
                        {(terminal.status === 'inactive' ||
                            terminal.status === 'maintenance') && (
                            <ActionRow
                                icon={Power}
                                label={
                                    terminal.status === 'maintenance'
                                        ? 'Sortir de maintenance'
                                        : 'Activer le terminal'
                                }
                                hint="Autoriser à nouveau l’ouverture de sessions OPS"
                                cta="Activer"
                                tone="emerald"
                                onClick={() => onAction('activate')}
                            />
                        )}
                        {terminal.status === 'active' && (
                            <ActionRow
                                icon={PowerOff}
                                label="Désactiver"
                                hint="Révoque immédiatement les sessions actives"
                                cta="Désactiver"
                                onClick={() => onAction('deactivate')}
                            />
                        )}
                        {terminal.status !== 'maintenance' &&
                            terminal.status !== 'lost' && (
                                <ActionRow
                                    icon={Wrench}
                                    label="Mettre en maintenance"
                                    hint="Bloque les opérations terrain sans bloquer le terminal"
                                    cta="Maintenance"
                                    tone="gold"
                                    onClick={() => onAction('maintenance')}
                                />
                            )}
                        {sessions.length > 0 && (
                            <ActionRow
                                icon={ShieldOff}
                                label="Révoquer les sessions"
                                hint="Ferme toutes les sessions OPS actives du terminal"
                                cta="Révoquer"
                                tone="blue"
                                onClick={() => onAction('revoke')}
                            />
                        )}
                        {terminal.status !== 'lost' ? (
                            <ActionRow
                                icon={AlertTriangle}
                                label="Déclarer perdu"
                                hint="Bloque le terminal et révoque ses sessions"
                                cta="Bloquer"
                                tone="destructive"
                                onClick={() => onAction('lost')}
                            />
                        ) : (
                            <ActionRow
                                icon={RotateCcw}
                                label="Récupérer le terminal"
                                hint="Inscrit une récupération explicite dans l’audit"
                                cta="Récupérer"
                                tone="emerald"
                                onClick={() => onAction('recover')}
                            />
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function HistoryTab({
    history,
    historyTypes,
    filters,
    onVisit,
}: {
    history: Pagination<HistoryEvent>;
    historyTypes: HistoryTypeOption[];
    filters: { event_type: string; from: string; to: string };
    onVisit: (params: Record<string, string>) => void;
}) {
    const [eventType, setEventType] = useState(filters.event_type);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const buildParams = (page?: number): Record<string, string> => {
        const params: Record<string, string> = {};

        if (eventType) {
            params.event_type = eventType;
        }

        if (from) {
            params.from = from;
        }

        if (to) {
            params.to = to;
        }

        if (page && page > 1) {
            params.history_page = String(page);
        }

        return params;
    };

    const filterControlClass =
        'h-10 w-full rounded-[0.625rem] border-0 bg-card px-3 text-xs text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none focus:shadow-[inset_0_0_0_1px_var(--gold)]';

    return (
        <div className="space-y-4">
            <form
                className="grid gap-2 rounded-xl bg-muted/60 p-3.5 shadow-[inset_0_0_0_1px_var(--border)] sm:grid-cols-[1.4fr_1fr_1fr_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    onVisit(buildParams());
                }}
            >
                <select
                    value={eventType}
                    onChange={(event) => setEventType(event.target.value)}
                    className={filterControlClass}
                    aria-label="Type d’événement"
                >
                    <option value="">Tous les événements</option>
                    {historyTypes.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </select>
                <input
                    type="date"
                    value={from}
                    onChange={(event) => setFrom(event.target.value)}
                    className={filterControlClass}
                    aria-label="Historique à partir du"
                />
                <input
                    type="date"
                    value={to}
                    onChange={(event) => setTo(event.target.value)}
                    className={filterControlClass}
                    aria-label="Historique jusqu’au"
                />
                <button type="submit" className="btn btn-navy">
                    <SlidersHorizontal className="size-4" /> Filtrer
                </button>
            </form>

            {history.data.length === 0 ? (
                <EmptyLine text="Aucun événement enregistré." />
            ) : (
                <Timeline events={history.data} />
            )}

            {history.last_page > 1 && (
                <div className="flex items-center justify-between border-t border-black/[0.045] pt-3 text-xs text-muted-foreground">
                    <span>
                        {history.from ?? 1}–{history.to ?? history.data.length}{' '}
                        sur {history.total} événements
                    </span>
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                            onClick={() =>
                                onVisit({
                                    ...buildParams(),
                                    history_page: String(
                                        history.current_page - 1,
                                    ),
                                })
                            }
                            disabled={history.current_page <= 1}
                            aria-label="Page précédente"
                        >
                            <ChevronLeft className="size-3.5" />
                        </button>
                        <span className="px-2 font-bold text-foreground">
                            Page {history.current_page} / {history.last_page}
                        </span>
                        <button
                            type="button"
                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                            onClick={() =>
                                onVisit({
                                    ...buildParams(),
                                    history_page: String(
                                        history.current_page + 1,
                                    ),
                                })
                            }
                            disabled={history.current_page >= history.last_page}
                            aria-label="Page suivante"
                        >
                            <ChevronRight className="size-3.5" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function TerminalDetailPage({
    terminal,
    assignmentHistory,
    sessions,
    history,
    historyTypes,
    historyFilters,
    agencies,
    users,
    permissions,
}: DetailPageProps) {
    const [activeTab, setActiveTab] = useState<DetailTab>('identity');
    const [pendingAction, setPendingAction] = useState<string | null>(null);

    const showUrl = terminalsRoutes.show.url({ terminal: terminal.uuid });

    const visitHistory = (params: Record<string, string>) => {
        router.get(showUrl, params, {
            only: ['history', 'historyFilters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const onActionSuccess = (message: string) => {
        setPendingAction(null);
        toast.success(message);
        router.reload({
            only: ['terminal', 'assignmentHistory', 'sessions', 'history'],
        });
    };

    const tabTitles: Record<DetailTab, string> = {
        identity: 'Identité du terminal',
        assignment: 'Affectation',
        security: 'Contrôles de sécurité',
        history: 'Journal d’activité',
    };

    return (
        <>
            <Head title={`Terminal ${terminal.serial_number}`} />

            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Administration / Terminaux P501 /{' '}
                            {terminal.serial_number}
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Détail terminal
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Fiche complète, affectation et journal de{' '}
                            {terminal.serial_number}.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={() =>
                                router.get(terminalsIndexRoute.url())
                            }
                        >
                            <ArrowLeft className="size-4" /> Retour
                        </button>
                        {permissions.canManage && (
                            <>
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    disabled={sessions.length === 0}
                                    onClick={() => setPendingAction('revoke')}
                                >
                                    <ShieldOff className="size-4" /> Révoquer
                                    les sessions
                                </button>
                                {terminal.status === 'lost' ? (
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        onClick={() =>
                                            setPendingAction('recover')
                                        }
                                    >
                                        <RotateCcw className="size-4" />{' '}
                                        Récupérer le terminal
                                    </button>
                                ) : terminal.status === 'active' ? (
                                    <button
                                        type="button"
                                        className="btn border-transparent bg-destructive text-white hover:bg-destructive/90"
                                        onClick={() => setPendingAction('lost')}
                                    >
                                        <AlertTriangle className="size-4" />{' '}
                                        Bloquer le terminal
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        onClick={() =>
                                            setPendingAction('activate')
                                        }
                                    >
                                        <Power className="size-4" /> Activer le
                                        terminal
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                </div>

                <nav
                    className="cash-reveal cash-reveal-delay-1 mb-4 flex gap-1 overflow-x-auto border-b border-border"
                    aria-label="Sections du terminal"
                >
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const isActive = activeTab === tab.key;

                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
                                aria-current={isActive ? 'page' : undefined}
                                className={cn(
                                    'flex items-center gap-2 border-b-2 px-4 pt-3 pb-3 text-[0.6875rem] font-extrabold tracking-[0.08em] whitespace-nowrap uppercase transition-colors',
                                    isActive
                                        ? 'border-gold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Icon
                                    className={cn(
                                        'size-3.5',
                                        isActive
                                            ? 'text-gold'
                                            : 'text-muted-foreground',
                                    )}
                                />
                                {tab.label}
                            </button>
                        );
                    })}
                </nav>

                <section className="cash-reveal cash-reveal-delay-2 relative mb-5 flex items-center justify-between gap-6 overflow-hidden rounded-[1.5rem] bg-kora bg-[radial-gradient(720px_180px_at_88%_118%,rgba(48,120,255,0.34),transparent_68%)] px-7 py-6 text-white ring-1 ring-black/10 dark:ring-white/10">
                    <div className="relative z-10 max-w-3xl">
                        <span className="section-kicker text-gold">
                            Terminal autorisé
                        </span>
                        <h2 className="mt-1.5 font-mono text-[clamp(1.4rem,2.4vw,1.9rem)] font-black tracking-tight">
                            {terminal.serial_number}
                        </h2>
                        <p className="mt-1.5 text-[0.75rem] leading-6 text-white/70">
                            {terminal.assigned_user?.name ?? 'Non affecté'}
                            {terminal.agency
                                ? ` · ${terminal.agency.name}`
                                : ' · Stock / sans agence'}
                            {terminal.hardware_model
                                ? ` · ${terminal.hardware_model}`
                                : ''}
                            {terminal.last_seen_at
                                ? ` · Vu ${formatRelative(terminal.last_seen_at)}`
                                : ' · Jamais connecté'}
                        </p>
                    </div>
                    <div className="relative z-10 flex shrink-0 flex-col items-end gap-2">
                        <KoraStatusBadge
                            label={statusLabels[terminal.status]}
                            tone={statusTones[terminal.status]}
                        />
                        <KoraStatusBadge
                            label={
                                connectivityLabels[terminal.connectivity_status]
                            }
                            tone={
                                connectivityTones[terminal.connectivity_status]
                            }
                        />
                    </div>
                </section>

                <div className="cash-reveal cash-reveal-delay-3 grid items-start gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(270px,0.55fr)]">
                    <Panel
                        title={tabTitles[activeTab]}
                        sub="Informations propres à ce dossier"
                    >
                        {activeTab === 'identity' && (
                            <IdentityTab terminal={terminal} />
                        )}
                        {activeTab === 'assignment' && (
                            <AssignmentTab
                                terminal={terminal}
                                assignmentHistory={assignmentHistory}
                                canManage={permissions.canManage}
                                onAction={setPendingAction}
                            />
                        )}
                        {activeTab === 'security' && (
                            <SecurityTab
                                terminal={terminal}
                                sessions={sessions}
                                canManage={permissions.canManage}
                                onAction={setPendingAction}
                            />
                        )}
                        {activeTab === 'history' && (
                            <HistoryTab
                                history={history}
                                historyTypes={historyTypes}
                                filters={historyFilters}
                                onVisit={visitHistory}
                            />
                        )}

                        <div className="mt-6 rounded-r-xl border-l-[3px] border-gold bg-muted/60 px-4 py-3.5">
                            <span className="text-[0.625rem] font-black tracking-[0.13em] text-digital uppercase">
                                Règle métier
                            </span>
                            <p className="mt-1.5 text-[0.6875rem] leading-5 text-foreground/80">
                                Un terminal non enregistré ou bloqué ne peut pas
                                se connecter aux services KORA. Seul le compte
                                de l’utilisateur affecté peut ouvrir une session
                                OPS sur ce numéro de série.
                            </p>
                        </div>
                    </Panel>

                    <Panel
                        title="Sécurité terminal"
                        sub="Derniers événements enregistrés"
                    >
                        <Timeline events={history.data.slice(0, 4)} />

                        {!permissions.canManage && (
                            <div className="mt-5 rounded-xl border border-digital/10 bg-muted/60 p-3.5">
                                <strong className="block text-[0.6875rem] text-foreground">
                                    Consultation uniquement
                                </strong>
                                <small className="mt-1 block text-[0.625rem] leading-4 text-muted-foreground">
                                    Aucune modification autorisée pour votre
                                    profil.
                                </small>
                            </div>
                        )}
                    </Panel>
                </div>
            </div>

            {pendingAction && (
                <TerminalActionModal
                    key={pendingAction}
                    action={pendingAction}
                    terminal={terminal}
                    users={users}
                    agencies={agencies}
                    onClose={() => setPendingAction(null)}
                    onSuccess={onActionSuccess}
                />
            )}
        </>
    );
}

TerminalDetailPage.layout = {
    breadcrumbs: [
        { title: 'Administration', href: '/users' } satisfies BreadcrumbItem,
        {
            title: 'Terminaux P501',
            href: '/terminals',
        } satisfies BreadcrumbItem,
    ],
};

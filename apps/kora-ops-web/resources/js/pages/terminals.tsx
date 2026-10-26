import { Head, router, useHttp, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    ChevronLeft,
    ChevronRight,
    Laptop,
    Plus,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Wifi,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { versionStatusTones } from '@/components/app-releases/shared';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import {
    connectivityLabels,
    connectivityTones,
    errorMessage,
    formatDate,
    formatRelative,
    statusLabels,
    statusTones,
} from '@/components/terminals/shared';
import type {
    AgencySummary,
    Pagination,
    TerminalStatus,
    TerminalSummary,
    UserCandidate,
} from '@/components/terminals/shared';
import { cn } from '@/lib/utils';
import { terminals as terminalsIndexRoute } from '@/routes';
import * as terminalsRoutes from '@/routes/terminals';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    terminals: Pagination<TerminalSummary>;
    statistics: {
        total: number;
        active: number;
        online: number;
        attention: number;
    };
    filters: {
        q: string;
        status: string;
        health: string;
        agency: string;
        assignment: string;
    };
    statuses: Record<string, string>;
    agencies: AgencySummary[];
    users: UserCandidate[];
    permissions: { canCreate: boolean; canManage: boolean };
};

function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone = 'navy',
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green' | 'rose';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
        rose: 'bg-destructive/10 text-destructive',
    };

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', toneStyles[tone])}>
                        <Icon className="size-4" strokeWidth={1.7} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Total</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

export default function TerminalsPage({
    terminals,
    statistics,
    filters,
    statuses,
    agencies,
    permissions,
}: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [modalError, setModalError] = useState<string | null>(null);

    const [query, setQuery] = useState(filters.q ?? '');
    const [selectedStatus, setSelectedStatus] = useState(
        filters.status || 'all',
    );
    const [selectedHealth, setSelectedHealth] = useState(
        filters.health || 'all',
    );
    const [selectedAgency, setSelectedAgency] = useState(
        filters.agency || 'all',
    );
    const [selectedAssignment, setSelectedAssignment] = useState(
        filters.assignment || 'all',
    );

    const {
        submit: submitCreate,
        data: createData,
        setData: setCreateData,
        processing: createProcessing,
        errors: createErrors,
    } = useHttp<{ serial_number: string }, unknown>({ serial_number: '' });
    const { start, stop } = usePoll(
        60000,
        { only: ['terminals', 'statistics'] },
        { autoStart: false },
    );

    useEffect(() => {
        const updatePolling = () => (document.hidden ? stop() : start());
        updatePolling();
        document.addEventListener('visibilitychange', updatePolling);

        return () => {
            stop();
            document.removeEventListener('visibilitychange', updatePolling);
        };
    }, [start, stop]);

    const buildParams = (
        overrides: {
            q?: string;
            status?: string;
            health?: string;
            agency?: string;
            assignment?: string;
            page?: number;
        } = {},
    ): Record<string, string> => {
        const nextQuery = overrides.q ?? query;
        const nextStatus = overrides.status ?? selectedStatus;
        const nextHealth = overrides.health ?? selectedHealth;
        const nextAgency = overrides.agency ?? selectedAgency;
        const nextAssignment = overrides.assignment ?? selectedAssignment;

        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextStatus !== 'all') {
            params.status = nextStatus;
        }

        if (nextHealth !== 'all') {
            params.health = nextHealth;
        }

        if (nextAgency !== 'all') {
            params.agency = nextAgency;
        }

        if (nextAssignment !== 'all') {
            params.assignment = nextAssignment;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        return params;
    };

    const visit = (overrides: Parameters<typeof buildParams>[0] = {}) => {
        router.get(terminalsIndexRoute.url(), buildParams(overrides), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const applyFilters = () => visit({ page: 1 });

    const resetFilters = () => {
        setQuery('');
        setSelectedStatus('all');
        setSelectedHealth('all');
        setSelectedAgency('all');
        setSelectedAssignment('all');
        router.get(
            terminalsIndexRoute.url(),
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const createTerminal = async (event: React.FormEvent) => {
        event.preventDefault();
        setModalError(null);
        setCreateData({
            serial_number: createData.serial_number.trim().toUpperCase(),
        });

        try {
            await submitCreate(terminalsRoutes.store());
            setCreateOpen(false);
            setCreateData({ serial_number: '' });
            toast.success('Terminal P501 enregistré.');
            await router.reload({ only: ['terminals', 'statistics'] });
        } catch (error) {
            setModalError(errorMessage(error));
        }
    };

    const filterSelect = (
        label: string,
        value: string,
        onChange: (value: string) => void,
        options: [string, string][],
    ) => (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <ChevronRight
                className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                strokeWidth={1.7}
            />
        </label>
    );

    return (
        <>
            <Head title="Terminaux P501" />

            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Administration / Terminaux P501
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Terminaux P501
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Administrez les terminaux autorisés et supervisez
                            leur présence terrain.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {permissions.canCreate && (
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() => {
                                    setModalError(null);
                                    setCreateOpen(true);
                                }}
                            >
                                <Plus className="size-4" /> Nouveau terminal
                            </button>
                        )}
                    </div>
                </div>

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        icon={Laptop}
                        label="P501 enregistrés"
                        hint="au référentiel du parc"
                        value={statistics.total}
                        tone="navy"
                    />
                    <KpiCard
                        icon={ShieldCheck}
                        label="Autorisés"
                        hint="sessions OPS permises"
                        value={statistics.active}
                        tone="green"
                    />
                    <KpiCard
                        icon={Wifi}
                        label="En ligne"
                        hint="présence terrain récente"
                        value={statistics.online}
                        tone="blue"
                    />
                    <KpiCard
                        icon={AlertTriangle}
                        label="À traiter"
                        hint="perdus, maintenance ou hors ligne"
                        value={statistics.attention}
                        tone={statistics.attention > 0 ? 'rose' : 'muted'}
                    />
                </section>

                <div className="cash-filterbar cash-reveal cash-reveal-delay-2">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters();
                            }
                        }}
                        aria-label="Rechercher par numéro de série"
                        placeholder="Rechercher par numéro de série…"
                        className="cash-filter-search"
                    />

                    {filterSelect(
                        'Statut',
                        selectedStatus,
                        (value) => {
                            setSelectedStatus(value);
                            visit({ status: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les statuts'],
                            ...Object.entries(statuses).map(
                                ([value, label]) =>
                                    [
                                        value,
                                        statusLabels[value as TerminalStatus] ??
                                            label,
                                    ] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Connexion',
                        selectedHealth,
                        (value) => {
                            setSelectedHealth(value);
                            visit({ health: value, page: 1 });
                        },
                        [
                            ['all', 'Toutes les connexions'],
                            ...Object.entries(connectivityLabels).map(
                                ([value, label]) =>
                                    [value, label] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Agence',
                        selectedAgency,
                        (value) => {
                            setSelectedAgency(value);
                            visit({ agency: value, page: 1 });
                        },
                        [
                            ['all', 'Toutes les agences'],
                            ...agencies.map(
                                (agency) =>
                                    [
                                        agency.uuid,
                                        agency.code
                                            ? `${agency.code} · ${agency.name}`
                                            : agency.name,
                                    ] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Affectation',
                        selectedAssignment,
                        (value) => {
                            setSelectedAssignment(value);
                            visit({ assignment: value, page: 1 });
                        },
                        [
                            ['all', 'Toutes les affectations'],
                            ['assigned', 'Affectés'],
                            ['unassigned', 'Non affectés'],
                        ],
                    )}

                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={applyFilters}
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={resetFilters}
                    >
                        Réinitialiser
                    </button>
                </div>

                <section className="cash-table-card cash-reveal cash-reveal-delay-3">
                    {terminals.data.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-muted-foreground">
                            <Laptop className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="font-semibold text-foreground">
                                Aucun terminal P501 trouvé
                            </p>
                            <p className="mt-1 text-xs">
                                Ajustez vos critères de recherche ou enregistrez
                                un nouveau terminal.
                            </p>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="btn btn-ghost mt-4 text-xs"
                            >
                                Réinitialiser les filtres
                            </button>
                        </div>
                    ) : (
                        <>
                            <div className="cash-table-scroll overflow-x-auto">
                                <table className="w-full min-w-[980px] text-left">
                                    <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Terminal
                                            </th>
                                            <th className="px-4 py-3">
                                                Modèle
                                            </th>
                                            <th className="px-4 py-3">
                                                Affectation
                                            </th>
                                            <th className="px-4 py-3">
                                                Dernière connexion
                                            </th>
                                            <th className="px-4 py-3">
                                                Version
                                            </th>
                                            <th className="px-4 py-3">
                                                Statut
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {terminals.data.map((terminal) => (
                                            <tr
                                                key={terminal.uuid}
                                                className="group cursor-pointer border-b border-black/[0.045] transition-colors hover:bg-muted/30"
                                                onClick={() =>
                                                    router.get(
                                                        terminalsRoutes.show.url(
                                                            {
                                                                terminal:
                                                                    terminal.uuid,
                                                            },
                                                        ),
                                                    )
                                                }
                                            >
                                                <td className="px-4 py-3.5">
                                                    <div className="flex items-center gap-3">
                                                        <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/15 text-gold">
                                                            <Laptop className="size-4" />
                                                        </span>
                                                        <div>
                                                            <p className="font-mono text-xs font-black text-foreground">
                                                                {
                                                                    terminal.serial_number
                                                                }
                                                            </p>
                                                            <span className="text-[0.6875rem] text-muted-foreground">
                                                                P501 physique
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 text-xs text-foreground">
                                                    {terminal.hardware_model ??
                                                        'P501 Android'}
                                                    {terminal.os_version
                                                        ? ` · ${terminal.os_version}`
                                                        : ''}
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="text-xs">
                                                        <strong className="block text-foreground">
                                                            {terminal
                                                                .assigned_user
                                                                ?.name ??
                                                                'Non affecté'}
                                                        </strong>
                                                        <span className="text-muted-foreground">
                                                            {terminal.agency
                                                                ?.name ??
                                                                'Stock / sans agence'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="text-xs">
                                                        <span className="block text-foreground">
                                                            {formatRelative(
                                                                terminal.last_seen_at,
                                                            )}
                                                        </span>
                                                        <span className="text-muted-foreground">
                                                            {formatDate(
                                                                terminal.last_seen_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="flex flex-col items-start gap-1.5">
                                                        <span className="font-mono text-xs text-foreground">
                                                            {terminal.app_version ??
                                                                '—'}
                                                        </span>
                                                        {terminal.version_status
                                                            .status !==
                                                            'not_tracked' && (
                                                            <KoraStatusBadge
                                                                label={
                                                                    terminal
                                                                        .version_status
                                                                        .label
                                                                }
                                                                tone={
                                                                    versionStatusTones[
                                                                        terminal
                                                                            .version_status
                                                                            .status
                                                                    ]
                                                                }
                                                                className="whitespace-nowrap"
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="flex flex-col items-start gap-1.5">
                                                        <KoraStatusBadge
                                                            label={
                                                                statusLabels[
                                                                    terminal
                                                                        .status
                                                                ]
                                                            }
                                                            tone={
                                                                statusTones[
                                                                    terminal
                                                                        .status
                                                                ]
                                                            }
                                                        />
                                                        <KoraStatusBadge
                                                            label={
                                                                connectivityLabels[
                                                                    terminal
                                                                        .connectivity_status
                                                                ]
                                                            }
                                                            tone={
                                                                connectivityTones[
                                                                    terminal
                                                                        .connectivity_status
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                                <td
                                                    className="px-4 py-3.5 text-right"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost px-2.5 py-1 text-xs font-bold text-digital"
                                                        onClick={() =>
                                                            router.get(
                                                                terminalsRoutes.show.url(
                                                                    {
                                                                        terminal:
                                                                            terminal.uuid,
                                                                    },
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Détail →
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {terminals.total > 0 && (
                                <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
                                    <span>
                                        {terminals.from ?? 1}–
                                        {terminals.to ?? terminals.data.length}{' '}
                                        sur {terminals.total} terminaux
                                    </span>
                                    <div className="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                            onClick={() =>
                                                visit({
                                                    page:
                                                        terminals.current_page -
                                                        1,
                                                })
                                            }
                                            disabled={
                                                terminals.current_page <= 1
                                            }
                                            aria-label="Page précédente"
                                        >
                                            <ChevronLeft className="size-3.5" />
                                        </button>
                                        <span className="px-2 font-bold text-foreground">
                                            Page {terminals.current_page} /{' '}
                                            {terminals.last_page}
                                        </span>
                                        <button
                                            type="button"
                                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                            onClick={() =>
                                                visit({
                                                    page:
                                                        terminals.current_page +
                                                        1,
                                                })
                                            }
                                            disabled={
                                                terminals.current_page >=
                                                terminals.last_page
                                            }
                                            aria-label="Page suivante"
                                        >
                                            <ChevronRight className="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </section>
            </div>

            {createOpen && (
                <div
                    className="cash-modal-backdrop"
                    role="presentation"
                    onMouseDown={(event) =>
                        event.target === event.currentTarget &&
                        !createProcessing &&
                        setCreateOpen(false)
                    }
                >
                    <form
                        className="cash-modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="terminal-create-title"
                        onSubmit={createTerminal}
                    >
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Laptop className="size-5" />
                                </span>
                                <div>
                                    <span className="section-kicker">
                                        Administration · Parc terrain
                                    </span>
                                    <h3 id="terminal-create-title">
                                        Nouveau terminal P501
                                    </h3>
                                    <p>
                                        Le numéro de série devient l’identité
                                        publique du terminal.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCreateOpen(false)}
                                className="cash-modal-close"
                                aria-label="Fermer"
                                disabled={createProcessing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <label className="field">
                            <span>
                                Numéro de série{' '}
                                <b className="font-black text-destructive">*</b>
                            </span>
                            <input
                                id="terminal-serial"
                                name="serial_number"
                                className="control font-mono uppercase"
                                placeholder="P501-BJ-000184"
                                required
                                maxLength={64}
                                autoFocus
                                value={createData.serial_number}
                                aria-invalid={
                                    createErrors.serial_number
                                        ? 'true'
                                        : undefined
                                }
                                onChange={(event) =>
                                    setCreateData({
                                        serial_number:
                                            event.target.value.toUpperCase(),
                                    })
                                }
                            />
                            {createErrors.serial_number && (
                                <small className="field-error-text font-semibold text-destructive">
                                    {createErrors.serial_number}
                                </small>
                            )}
                        </label>

                        {modalError && (
                            <div className="cash-form-error" role="alert">
                                {modalError}
                            </div>
                        )}

                        <div className="cash-modal-actions">
                            <button
                                type="button"
                                onClick={() => setCreateOpen(false)}
                                className="btn btn-ghost"
                                disabled={createProcessing}
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary gap-2 font-black"
                                disabled={createProcessing}
                            >
                                <Check className="size-4" />
                                {createProcessing
                                    ? 'Enregistrement…'
                                    : 'Enregistrer'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}

TerminalsPage.layout = {
    breadcrumbs: [
        { title: 'Administration', href: '/users' } satisfies BreadcrumbItem,
        {
            title: 'Terminaux P501',
            href: '/terminals',
        } satisfies BreadcrumbItem,
    ],
};

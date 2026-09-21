import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    Clock3,
    Eye,
    HandHelping,
    Inbox,
    MessageCircle,
    Phone,
    Search,
    SlidersHorizontal,
    TimerOff,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { QueueLeg } from '@/components/assisted-bookings/booking-drawer';
import {
    AssistedBookingDrawer,
    assistedStatusLabels,
    assistedStatusTones,
} from '@/components/assisted-bookings/booking-drawer';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import { index } from '@/routes/assisted-bookings';
import type { BreadcrumbItem } from '@/types';

type QueueItem = {
    uuid: string;
    status: string;
    channel: string;
    reference: string;
    contactName: string;
    contactPhone: string;
    handlingAgency: { uuid: string; name: string } | null;
    assignee: { uuid: string; name: string } | null;
    legs: QueueLeg[];
    originalAmount: number;
    currentAmount: number | null;
    currency: string;
    expiresAt: string;
};

type Props = {
    assistedBookings: {
        data: QueueItem[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    counters: {
        pending: number;
        inProgress: number;
        expired: number;
        finalized: number;
    };
    filters: { q?: string; status?: string; agency?: string; date?: string };
    statuses: Record<string, string>;
    agencies: { uuid: string; name: string; city: string }[];
};

const channelLabels: Record<string, string> = {
    whatsapp: 'WhatsApp',
    call: 'Téléphone',
};

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: [string, string][];
}) {
    return (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
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
}

function FilterDate({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <input
                type="date"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            />
        </label>
    );
}

function KpiCard({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green' | 'destructive';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
        destructive: 'bg-destructive/10 text-destructive',
    };
    const iconBg = toneStyles[tone] ?? toneStyles.muted;

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', iconBg)}>
                        <Icon className="size-4" strokeWidth={1.7} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>Assistance</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Réservations</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

function Pagination({
    currentPage,
    totalPages,
    total,
    from,
    to,
    onPageChange,
}: {
    currentPage: number;
    totalPages: number;
    total: number;
    from: number | null;
    to: number | null;
    onPageChange: (page: number) => void;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
            <span>
                {from}–{to} sur {total}
            </span>
            <div className="flex items-center gap-1.5">
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                    aria-label="Page précédente"
                >
                    <ChevronRight
                        className="size-3.5 rotate-180"
                        strokeWidth={1.7}
                    />
                </button>
                <span className="px-2 font-bold text-foreground">
                    {currentPage} / {totalPages}
                </span>
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= totalPages}
                    aria-label="Page suivante"
                >
                    <ChevronRight className="size-3.5" strokeWidth={1.7} />
                </button>
            </div>
        </div>
    );
}

export default function AssistedBookingsIndex({
    assistedBookings,
    counters,
    filters,
    statuses,
    agencies,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [agency, setAgency] = useState(filters.agency ?? 'all');
    const [date, setDate] = useState(filters.date ?? '');
    const [openUuid, setOpenUuid] = useState<string | null>(() =>
        new URLSearchParams(window.location.search).get('open'),
    );

    function closeDrawer() {
        setOpenUuid(null);
        const url = new URL(window.location.href);
        url.searchParams.delete('open');
        window.history.replaceState(null, '', url.toString());
    }

    const statusOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les états'],
            ...(Object.keys(statuses).map((value) => [
                value,
                assistedStatusLabels[value] ?? statuses[value],
            ]) as [string, string][]),
        ],
        [statuses],
    );

    const agencyOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Toutes les agences'],
            ...agencies.map(
                (item) =>
                    [item.uuid, `${item.name} — ${item.city}`] as [
                        string,
                        string,
                    ],
            ),
        ],
        [agencies],
    );

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (query.trim() !== '') {
            params.q = query.trim();
        }

        if (status !== 'all') {
            params.status = status;
        }

        if (agency !== 'all') {
            params.agency = agency;
        }

        if (date !== '') {
            params.date = date;
        }

        return params;
    };

    const applyFilters = () => {
        router.get(index.url(), filterParams(), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const resetFilters = () => {
        setQuery('');
        setStatus('all');
        setAgency('all');
        setDate('');
        router.get(
            index.url(),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            index.url(),
            { ...filterParams(), page: String(page) },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Réservations en attente" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Vente / Réservations en attente
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Réservations en attente
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Reprenez les réservations mobiles confiées aux
                            agences. Une réservation n’occupe aucun siège tant
                            qu’elle reste dans cette file.
                        </p>
                    </div>
                </div>

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1">
                    <KpiCard
                        icon={Inbox}
                        label="À prendre en charge"
                        value={counters.pending}
                        tone="gold"
                    />
                    <KpiCard
                        icon={HandHelping}
                        label="En cours"
                        value={counters.inProgress}
                        tone="blue"
                    />
                    <KpiCard
                        icon={TimerOff}
                        label="Expirés"
                        value={counters.expired}
                        tone="destructive"
                    />
                    <KpiCard
                        icon={CheckCircle2}
                        label="Finalisés"
                        value={counters.finalized}
                        tone="navy"
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">
                            File d’assistance
                        </span>
                        <h2>File des réservations</h2>
                    </div>
                </div>

                <form
                    className="cash-filterbar cash-reveal cash-reveal-delay-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters();
                    }}
                >
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters();
                            }
                        }}
                        placeholder="Référence, nom ou téléphone du contact…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="État"
                        value={status}
                        onChange={setStatus}
                        options={statusOptions}
                    />
                    <FilterSelect
                        label="Agence"
                        value={agency}
                        onChange={setAgency}
                        options={agencyOptions}
                    />
                    <FilterDate label="Date" value={date} onChange={setDate} />
                    <button
                        type="submit"
                        className="btn btn-navy cash-filter-submit"
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
                </form>

                <section className="cash-table-card cash-reveal cash-reveal-delay-3">
                    {assistedBookings.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <HandHelping className="mx-auto size-8 text-muted-foreground/50" />
                            <h2 className="mt-3 font-black">
                                Aucune réservation dans cette file
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Ajustez vos filtres ou attendez le prochain
                                transfert depuis l’application mobile.
                            </p>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Réservation
                                        </th>
                                        <th className="px-4 py-3">Trajet</th>
                                        <th className="px-4 py-3">Contact</th>
                                        <th className="px-4 py-3">Agence traitante</th>
                                        <th className="px-4 py-3">État</th>
                                        <th className="px-4 py-3 text-right">
                                            Montant
                                        </th>
                                        <th className="px-4 py-3">Validité</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {assistedBookings.data.map((item) => (
                                        <tr
                                            key={item.uuid}
                                            className="group cursor-pointer border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                            onClick={() =>
                                                setOpenUuid(item.uuid)
                                            }
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/12 text-[var(--kora)]">
                                                        {item.channel ===
                                                        'whatsapp' ? (
                                                            <MessageCircle
                                                                className="size-4"
                                                                strokeWidth={
                                                                    1.6
                                                                }
                                                            />
                                                        ) : (
                                                            <Phone
                                                                className="size-4"
                                                                strokeWidth={
                                                                    1.6
                                                                }
                                                            />
                                                        )}
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-black text-foreground">
                                                            {item.reference}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {item.assignee
                                                                ?.name ??
                                                                'Non attribuée'}{' '}
                                                            ·{' '}
                                                            {channelLabels[
                                                                item.channel
                                                            ] ?? item.channel}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {item.legs.length > 0 ? (
                                                    <>
                                                        <p className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                                            {item.legs[0].label}
                                                            {item.legs.length >
                                                                1 && (
                                                                <span className="rounded-md bg-gold/15 px-1.5 py-0.5 text-[0.5625rem] font-black text-amber-700 dark:text-amber-300">
                                                                    +
                                                                    {item.legs
                                                                        .length -
                                                                        1}
                                                                </span>
                                                            )}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {item.legs[0].line}{' '}
                                                            ·{' '}
                                                            {formatDate(
                                                                item.legs[0]
                                                                    .departureAt,
                                                            )}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <p className="text-xs font-bold text-foreground">
                                                    {item.contactName}
                                                </p>
                                                <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                    {item.contactPhone}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs font-medium text-foreground">
                                                {item.handlingAgency?.name ?? 'File réseau'}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        assistedStatusLabels[
                                                            item.status
                                                        ] ?? item.status
                                                    }
                                                    tone={
                                                        assistedStatusTones[
                                                            item.status
                                                        ] ?? 'muted'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5 text-right text-xs font-black text-foreground">
                                                {money(
                                                    item.currentAmount ??
                                                        item.originalAmount,
                                                    item.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <Clock3 className="size-3.5" />
                                                    {formatDate(item.expiresAt)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div
                                                    className="flex justify-end gap-1.5"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="grid size-8 place-items-center rounded-full bg-muted text-foreground transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-gold/15"
                                                        onClick={() =>
                                                            setOpenUuid(
                                                                item.uuid,
                                                            )
                                                        }
                                                        aria-label="Voir la réservation"
                                                    >
                                                        <Eye
                                                            className="size-3.5"
                                                            strokeWidth={1.7}
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination
                        currentPage={assistedBookings.current_page}
                        totalPages={assistedBookings.last_page}
                        total={assistedBookings.total}
                        from={assistedBookings.from}
                        to={assistedBookings.to}
                        onPageChange={goToPage}
                    />
                </section>
            </div>
            {openUuid && (
                <AssistedBookingDrawer uuid={openUuid} onClose={closeDrawer} />
            )}
        </>
    );
}

AssistedBookingsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Réservations en attente',
            href: '/assisted-bookings',
        } satisfies BreadcrumbItem,
    ],
};

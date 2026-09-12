import { Head, router } from '@inertiajs/react';
import {
    ChevronRight,
    Download,
    Eye,
    MessageCircle,
    Search,
    SlidersHorizontal,
    Ticket as TicketIcon,
    TrendingUp,
    CheckCircle2,
    X,
    Send,
    Banknote,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type TicketRecord = {
    uuid: string;
    shortCode: string;
    passengerName: string;
    passengerPhone: string | null;
    channel: string | null;
    channelLabel: string;
    originCity: string;
    destinationCity: string;
    amount: number;
    currency: string;
    amountLabel: string;
    status: string;
    statusLabel: string;
    issuedAt: string | null;
    issuedAtLabel: string;
    mecefUid: string | null;
    mecefCode: string | null;
    mecefNim: string | null;
    fiscalStatus: string | null;
};

type Kpis = {
    total: number;
    issued: number;
    used: number;
    cancelled: number;
    refunded: number;
    totalRevenue: number;
    totalRevenueLabel: string;
};

type PageProps = {
    tickets: {
        data: TicketRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    channels: Record<string, string>;
    routes: string[];
    kpis: Kpis;
    flash?: {
        whatsapp_status?: string;
    };
};

const statusLabels: Record<string, string> = {
    issued: 'Émis',
    used: 'Utilisé',
    cancelled: 'Annulé',
    refunded: 'Remboursé',
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    issued: 'navy',
    used: 'blue',
    cancelled: 'muted',
    refunded: 'gold',
};

const channelTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    counter: 'gold',
    online: 'blue',
    mobile: 'navy',
    ops: 'muted',
};

const channelLabels: Record<string, string> = {
    counter: 'Guichet',
    online: 'En ligne',
    mobile: 'Mobile',
    ops: 'OPS',
};

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

function KpiCard({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
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
                        <small>Billets</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Valeur</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

function WhatsAppDialog({
    ticket,
    onClose,
}: {
    ticket: TicketRecord;
    onClose: () => void;
}) {
    const [phone, setPhone] = useState(ticket.passengerPhone ?? '');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        setError(null);

        const trimmed = phone.trim();

        if (trimmed === '') {
            setError('Le numéro de téléphone est requis.');

            return;
        }

        if (!trimmed.startsWith('+')) {
            setError(
                'Le numéro doit être au format international (ex: +2290197421860).',
            );

            return;
        }

        setSubmitting(true);
        router.post(
            `/tickets/${ticket.uuid}/whatsapp`,
            { phone: trimmed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSubmitting(false);
                    onClose();
                },
                onError: (errors) => {
                    setSubmitting(false);
                    setError(
                        errors.phone ??
                        "Une erreur est survenue lors de l'envoi.",
                    );
                },
            },
        );
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <div
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="whatsapp-dialog-title"
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Envoi WhatsApp</span>
                        <h3 id="whatsapp-dialog-title">
                            Envoyer le billet {ticket.shortCode}
                        </h3>
                        <p>
                            Renseignez le numéro de téléphone du destinataire au
                            format international. Par défaut, le numéro du
                            passager est pré-rempli.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="cash-opening-list">
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <MessageCircle className="size-4" />
                            </span>
                            <div>
                                <p className="text-xs font-black text-foreground">
                                    Numéro destinataire
                                </p>
                                <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                    Format international (+229…)
                                </p>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>Téléphone WhatsApp</span>
                                <input
                                    type="tel"
                                    value={phone}
                                    onChange={(event) =>
                                        setPhone(event.target.value)
                                    }
                                    placeholder="+2290197421860"
                                    className="control"
                                    autoFocus
                                />
                            </label>
                        </div>
                    </div>

                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <TicketIcon className="size-4" />
                            </span>
                            <div>
                                <p className="text-xs font-black text-foreground">
                                    Billet à envoyer
                                </p>
                                <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                    {ticket.passengerName} — {ticket.originCity}{' '}
                                    → {ticket.destinationCity}
                                </p>
                            </div>
                        </div>
                    </div>

                    {error && (
                        <div className="cash-form-error" role="alert">
                            {error}
                        </div>
                    )}

                    <div className="cash-modal-actions">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={onClose}
                            disabled={submitting}
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            className={cn(
                                'btn btn-navy',
                                submitting && 'opacity-60',
                            )}
                            disabled={submitting}
                        >
                            <Send className="size-4" /> Envoyer le billet
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

export default function TicketsPage({
    tickets,
    statuses,
    channels,
    routes,
    kpis,
    flash,
}: PageProps) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('all');
    const [channel, setChannel] = useState('all');
    const [route, setRoute] = useState('all');
    const [whatsappTarget, setWhatsappTarget] = useState<TicketRecord | null>(
        null,
    );

    const statusOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les statuts'],
            ...(Object.entries(statuses).map(([value, label]) => [
                value,
                statusLabels[value] ?? label,
            ]) as [string, string][]),
        ],
        [statuses],
    );

    const channelOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les canaux'],
            ...(Object.entries(channels).map(([value, label]) => [
                value,
                label,
            ]) as [string, string][]),
        ],
        [channels],
    );

    const routeOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les trajets'],
            ...routes.map((r) => [r, r] as [string, string]),
        ],
        [routes],
    );

    const applyFilters = () => {
        const params: Record<string, string> = {};

        if (query.trim() !== '') {
            params.q = query.trim();
        }

        if (status !== 'all') {
            params.status = status;
        }

        if (channel !== 'all') {
            params.channel = channel;
        }

        if (route !== 'all') {
            params.route = route;
        }

        router.get('/tickets', params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const resetFilters = () => {
        setQuery('');
        setStatus('all');
        setChannel('all');
        setRoute('all');
        router.get(
            '/tickets',
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const goToPage = (page: number) => {
        const params: Record<string, string> = { page: String(page) };

        if (query.trim() !== '') {
            params.q = query.trim();
        }

        if (status !== 'all') {
            params.status = status;
        }

        if (channel !== 'all') {
            params.channel = channel;
        }

        if (route !== 'all') {
            params.route = route;
        }

        router.get('/tickets', params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <>
            <Head title="Billets" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Billets
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Billets
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Recherchez, contrôlez et traitez chaque billet selon
                            son canal de vente.
                        </p>
                    </div>
                </div>

                {flash?.whatsapp_status && (
                    <div
                        className={cn(
                            'cash-reveal cash-reveal-delay-1 mb-4 rounded-xl px-4 py-3 text-sm font-bold',
                            flash.whatsapp_status === 'dispatched'
                                ? 'bg-emerald-500/10 text-emerald-600'
                                : 'bg-gold/15 text-gold',
                        )}
                    >
                        {flash.whatsapp_status === 'dispatched'
                            ? 'Envoi WhatsApp programmé. Le billet sera livré dans quelques instants.'
                            : 'Ce billet a déjà été envoyé via WhatsApp.'}
                    </div>
                )}

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1">
                    <KpiCard
                        icon={TicketIcon}
                        label="Total billets"
                        value={kpis.total}
                        tone="navy"
                    />
                    <KpiCard
                        icon={Banknote}
                        label="Chiffre d'affaires"
                        value={kpis.totalRevenueLabel}
                        tone="gold"
                    />
                    <KpiCard
                        icon={CheckCircle2}
                        label="Émis"
                        value={kpis.issued}
                        tone="blue"
                    />
                    <KpiCard
                        icon={TrendingUp}
                        label="Utilisés"
                        value={kpis.used}
                        tone="green"
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">
                            Journal des billets
                        </span>
                        <h2>Liste des billets</h2>
                    </div>
                </div>

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
                        placeholder="Rechercher par code court, passager…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={setStatus}
                        options={statusOptions}
                    />
                    <FilterSelect
                        label="Canal"
                        value={channel}
                        onChange={setChannel}
                        options={channelOptions}
                    />
                    <FilterSelect
                        label="Trajet"
                        value={route}
                        onChange={setRoute}
                        options={routeOptions}
                    />
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
                    {tickets.data.length === 0 ? (
                        <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                            Aucun billet ne correspond à ces filtres.
                        </p>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Code court
                                        </th>
                                        <th className="px-4 py-3">Passager</th>
                                        <th className="px-4 py-3">Canal</th>
                                        <th className="px-4 py-3">Trajet</th>
                                        <th className="px-4 py-3 text-right">
                                            Montant
                                        </th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3">Émis le</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tickets.data.map((ticket) => (
                                        <tr
                                            key={ticket.uuid}
                                            className="group cursor-pointer border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                            onClick={() =>
                                                router.get(
                                                    `/tickets/${ticket.uuid}`,
                                                )
                                            }
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/12 text-[var(--kora)]">
                                                        <TicketIcon
                                                            className="size-4"
                                                            strokeWidth={1.6}
                                                        />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-black text-foreground">
                                                            {ticket.shortCode}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {ticket.mecefCode
                                                                ? `${ticket.mecefCode} • ${ticket.mecefNim ?? ''}`.trim()
                                                                : 'Non fiscalisé'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                                                {ticket.passengerName}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        ticket.channel
                                                            ? (channelLabels[
                                                                ticket.channel
                                                            ] ??
                                                                ticket.channelLabel)
                                                            : '—'
                                                    }
                                                    tone={
                                                        ticket.channel
                                                            ? (channelTones[
                                                                ticket.channel
                                                            ] ?? 'muted')
                                                            : 'muted'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5 text-xs font-medium text-foreground">
                                                {ticket.originCity} →{' '}
                                                {ticket.destinationCity}
                                            </td>
                                            <td className="px-4 py-3.5 text-right text-xs font-black text-foreground">
                                                {ticket.amountLabel}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        statusLabels[
                                                        ticket.status
                                                        ] ?? ticket.statusLabel
                                                    }
                                                    tone={
                                                        statusTones[
                                                        ticket.status
                                                        ] ?? 'gold'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {ticket.issuedAtLabel}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div
                                                    className="flex justify-end gap-1.5"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <button
                                                        className="grid size-8 place-items-center rounded-full bg-muted text-foreground transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-gold/15"
                                                        onClick={() =>
                                                            router.get(
                                                                `/tickets/${ticket.uuid}`,
                                                            )
                                                        }
                                                        aria-label="Voir le billet"
                                                    >
                                                        <Eye
                                                            className="size-3.5"
                                                            strokeWidth={1.7}
                                                        />
                                                    </button>
                                                    <button
                                                        className="grid size-8 place-items-center rounded-full bg-muted text-foreground transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-gold/15"
                                                        onClick={() =>
                                                            window.open(
                                                                `/tickets/${ticket.uuid}/pdf`,
                                                                '_blank',
                                                            )
                                                        }
                                                        aria-label="Télécharger PDF"
                                                    >
                                                        <Download
                                                            className="size-3.5"
                                                            strokeWidth={1.7}
                                                        />
                                                    </button>
                                                    <button
                                                        className="grid size-8 place-items-center rounded-full bg-emerald-500/10 text-emerald-600 transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-emerald-500/20"
                                                        onClick={() =>
                                                            setWhatsappTarget(
                                                                ticket,
                                                            )
                                                        }
                                                        aria-label="Envoyer WhatsApp"
                                                    >
                                                        <MessageCircle
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
                        currentPage={tickets.current_page}
                        totalPages={tickets.last_page}
                        total={tickets.total}
                        from={tickets.from}
                        to={tickets.to}
                        onPageChange={goToPage}
                    />
                </section>
            </div>

            {whatsappTarget && (
                <WhatsAppDialog
                    ticket={whatsappTarget}
                    onClose={() => setWhatsappTarget(null)}
                />
            )}
        </>
    );
}

TicketsPage.layout = {
    breadcrumbs: [
        { title: 'Billets', href: '/tickets' } satisfies BreadcrumbItem,
    ],
};

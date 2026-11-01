import { router } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    Clock,
    Coins,
    HandCoins,
    HelpCircle,
    PlusCircle,
    Printer,
    ReceiptText,
    RotateCcw,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Smartphone,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { KoraTooltip } from '@/components/kora-tooltip';
import { cn } from '@/lib/utils';
import type {
    CashBrouillardFilters,
    DailyCashBrouillard,
    FinancialHealth,
    KoraPointProfile,
    PointCashSession,
} from '@/types/kora-points';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    brouillard?: DailyCashBrouillard;
    filters?: CashBrouillardFilters;
    cashSession?: PointCashSession;
    onOpenSession?: (amount: number) => void;
    onCloseSession?: (actualCash: number, note?: string) => void;
    onNavigateToSale?: () => void;
    onNavigateToSettlements?: () => void;
};

const NETWORK_FLAGS: Record<string, { label: string; flag: string }> = {
    mtn_momo: { label: 'MTN MoMo', flag: '🇧🇯' },
    moov_flooz: { label: 'Moov Flooz', flag: '🇧🇯' },
    celtiis_cash: { label: 'Celtiis Cash', flag: '🇧🇯' },
    tmoney_tg: { label: 'T-Money', flag: '🇹🇬' },
    moov_flooz_tg: { label: 'Moov Togo', flag: '🇹🇬' },
    wave_ci: { label: 'Wave CI', flag: '🇨🇮' },
    orange_money_ci: { label: 'Orange Money', flag: '🇨🇮' },
    mtn_momo_ci: { label: 'MTN CI', flag: '🇨🇮' },
    moov_flooz_ci: { label: 'Moov CI', flag: '🇨🇮' },
};

const kpiVariants = {
    blue: 'cash-ledger-card-kpi-digital',
    gold: 'cash-ledger-card-kpi-gold',
    emerald: 'cash-ledger-card-kpi-emerald',
    navy: 'cash-ledger-card-kpi-navy',
};

const getTodayStr = () => new Date().toISOString().split('T')[0];

const getYesterdayStr = () => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    return d.toISOString().split('T')[0];
};

const get7DaysAgoStr = () => {
    const d = new Date();
    d.setDate(d.getDate() - 6);
    return d.toISOString().split('T')[0];
};

const getMonthStartStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
};

export function KoraPointCashView({
    point,
    health,
    brouillard,
    filters,
    cashSession,
    onNavigateToSale,
    onNavigateToSettlements,
}: Props) {
    const todayStr = useMemo(() => getTodayStr(), []);
    const [query, setQuery] = useState(filters?.q ?? '');
    const [fromDate, setFromDate] = useState(filters?.from ?? todayStr);
    const [toDate, setToDate] = useState(filters?.to ?? todayStr);
    const [channel, setChannel] = useState(filters?.channel ?? 'all');

    useEffect(() => {
        if (filters) {
            setQuery(filters.q ?? '');
            setFromDate(filters.from ?? todayStr);
            setToDate(filters.to ?? todayStr);
            setChannel(filters.channel ?? 'all');
        }
    }, [filters, todayStr]);

    const applyFilters = (f = fromDate, t = toDate, c = channel, qVal = query) => {
        router.get(
            '/kora-point/cash',
            {
                from: f,
                to: t,
                channel: c,
                q: qVal.trim(),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const handlePreset = (preset: 'today' | 'yesterday' | '7d' | 'month') => {
        let newFrom = todayStr;
        let newTo = todayStr;

        if (preset === 'today') {
            newFrom = todayStr;
            newTo = todayStr;
        } else if (preset === 'yesterday') {
            newFrom = getYesterdayStr();
            newTo = newFrom;
        } else if (preset === '7d') {
            newFrom = get7DaysAgoStr();
            newTo = todayStr;
        } else if (preset === 'month') {
            newFrom = getMonthStartStr();
            newTo = todayStr;
        }

        setFromDate(newFrom);
        setToDate(newTo);
        applyFilters(newFrom, newTo, channel, query);
    };

    const handleReset = () => {
        setQuery('');
        setFromDate(todayStr);
        setToDate(todayStr);
        setChannel('all');
        router.get(
            '/kora-point/cash',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const data: DailyCashBrouillard = useMemo(() => {
        if (brouillard) {
            return brouillard;
        }

        const fallbackCash = cashSession?.cash_collected ?? 0;
        const fallbackMoMo = cashSession?.mobile_money_collected ?? 0;
        const total = fallbackCash + fallbackMoMo;

        return {
            date_label: new Date().toLocaleDateString('fr-FR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }),
            gross_total: total,
            commission_total: Math.round(total * 0.05),
            net_total: Math.round(total * 0.95),
            cash_total: fallbackCash,
            mobile_money_total: fallbackMoMo,
            tickets_count:
                cashSession?.movements.filter((m) => m.type !== 'OPENING')
                    .length ?? 0,
            movements: (cashSession?.movements ?? [])
                .filter((m) => m.type !== 'OPENING')
                .map((m) => ({
                    id: String(m.id),
                    date_label: new Date().toLocaleDateString('fr-FR'),
                    time_label: m.time_label,
                    ticket_reference: 'BILLET',
                    passenger_name: 'Client',
                    passenger_phone: '—',
                    method: m.method === 'CASH' ? 'cash' : 'mobile_money',
                    network: null,
                    network_label: m.method === 'CASH' ? null : 'Mobile Money',
                    reference: '—',
                    gross_amount: m.amount,
                    commission_amount: Math.round(m.amount * 0.05),
                    net_amount: Math.round(m.amount * 0.95),
                })),
        };
    }, [brouillard, cashSession]);

    const isAutonomous = !point.organization;

    const kpis = [
        {
            id: 'gross',
            label: 'Total encaissé',
            hint: 'Recettes brutes globales',
            value: `${data.gross_total.toLocaleString('fr-FR')} FCFA`,
            trend: `${data.tickets_count} billet(s) émis`,
            tone: 'blue' as const,
            icon: Coins,
            formula:
                'Somme totale brute encaissée (espèces et mobile money) sur la période sélectionnée.',
        },
        {
            id: 'cash',
            label: 'Espèces en caisse',
            hint: 'Liquidités physiques',
            value: `${data.cash_total.toLocaleString('fr-FR')} FCFA`,
            trend: 'Perçu au guichet',
            tone: 'emerald' as const,
            icon: HandCoins,
            formula:
                'Montant cumulé perçu en liquide physique au guichet sur la période sélectionnée.',
        },
        {
            id: 'mobile_money',
            label: 'Mobile Money',
            hint: 'Bénin · Togo · Côte d’Ivoire',
            value: `${data.mobile_money_total.toLocaleString('fr-FR')} FCFA`,
            trend: 'Règlements mobiles',
            tone: 'gold' as const,
            icon: Smartphone,
            formula:
                'Montant total encaissé via transferts électroniques (MTN, Moov, Celtiis, Wave, Orange, T-Money).',
        },
        {
            id: 'net',
            label: 'Net dû KORA',
            hint: `Dont +${data.commission_total.toLocaleString('fr-FR')} FCFA commission`,
            value: `${data.net_total.toLocaleString('fr-FR')} FCFA`,
            trend: 'À reverser à KORA',
            tone: 'navy' as const,
            icon: ReceiptText,
            formula:
                'Net à reverser à KORA = Ventes brutes − Commissions acquises déduites. Votre commission reste acquise.',
        },
    ];

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="kora-page-head flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between print:mb-4">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground print:hidden">
                        KORA POINT / Espace Agent / Brouillard d'encaissement
                    </div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-[clamp(1.75rem,2.8vw,2.5rem)] font-extrabold tracking-[-0.04em] text-foreground">
                            Brouillard d’encaissement
                        </h1>
                        <span className="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary capitalize">
                            {data.date_label}
                        </span>
                    </div>
                    <p className="mt-1 text-[0.8125rem] leading-6 text-muted-foreground">
                        Journal des flux perçus (Espèces et Mobile Money) au guichet {point.name} ({point.code}). Encaissement autonome sans clôture de caisse imposée.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5 print:hidden">
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="btn btn-outline"
                    >
                        <Printer className="size-4" />
                        <span>Imprimer le brouillard</span>
                    </button>
                    {onNavigateToSale && (
                        <button
                            type="button"
                            onClick={onNavigateToSale}
                            className="btn btn-primary"
                        >
                            <PlusCircle className="size-4" />
                            <span>Nouvelle vente</span>
                        </button>
                    )}
                </div>
            </div>

            {/* Exactly 4 KPIs with Colored Backgrounds (Dashboard Style) */}
            <section aria-label="Indicateurs de caisse">
                <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                    {kpis.map((kpi) => {
                        const Icon = kpi.icon;

                        return (
                            <article
                                key={kpi.id}
                                className={cn(
                                    'cash-ledger-card transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-lg',
                                    kpiVariants[kpi.tone],
                                )}
                            >
                                <div className="cash-ledger-head">
                                    <div className="cash-ledger-identity">
                                        <span className="cash-ledger-brand">
                                            <Icon className="size-4" strokeWidth={1.8} />
                                        </span>
                                        <div>
                                            <div className="flex items-center gap-1.5">
                                                <span className="cash-ledger-title">{kpi.label}</span>
                                                <KoraTooltip content={kpi.formula} side="top">
                                                    <button
                                                        type="button"
                                                        aria-label={`Explication du calcul : ${kpi.label}`}
                                                        className="opacity-70 transition-opacity hover:opacity-100 focus:outline-none"
                                                    >
                                                        <HelpCircle className="size-3.5" />
                                                    </button>
                                                </KoraTooltip>
                                            </div>
                                            <small>{kpi.hint}</small>
                                        </div>
                                    </div>
                                </div>

                                <div className="cash-ledger-balance mb-0">
                                    <span>{kpi.trend}</span>
                                    <strong>{kpi.value}</strong>
                                </div>
                            </article>
                        );
                    })}
                </div>
            </section>

            {/* Financial Health & Credit Exposure Reminder Banner */}
            <div className="rounded-2xl border border-border/80 bg-gradient-to-r from-muted/50 via-card to-card p-4 sm:p-5 shadow-sm print:hidden">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-primary" />
                            <strong className="text-sm font-bold text-foreground">
                                Situation d'encours KORA
                            </strong>
                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[0.6875rem] font-bold text-primary">
                                {health.usage_percentage} % du plafond
                            </span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Encours actuel :{' '}
                            <strong className="font-mono text-foreground">
                                {health.current_exposure.toLocaleString('fr-FR')} FCFA
                            </strong>{' '}
                            sur un plafond autorisé de{' '}
                            <span className="font-mono">
                                {health.credit_limit.toLocaleString('fr-FR')} FCFA
                            </span>
                            . Disponible :{' '}
                            <strong className="font-mono text-emerald-600 dark:text-emerald-400">
                                {health.available_balance.toLocaleString('fr-FR')} FCFA
                            </strong>
                            .
                        </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Clock className="size-3.5 text-gold" />
                            <span>
                                Échéance : <strong className="text-foreground">{health.next_due_label}</strong>
                            </span>
                        </div>
                        {isAutonomous && onNavigateToSettlements && (
                            <button
                                type="button"
                                onClick={onNavigateToSettlements}
                                className="btn btn-outline btn-sm text-xs"
                            >
                                <span>Déclarer versement</span>
                                <ArrowRight className="size-3" />
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* Filter Bar with Search, Period & Channel (Cash style) */}
            <div className="space-y-2.5 print:hidden">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-1.5 text-xs">
                        <span className="text-[0.6875rem] font-black tracking-wider uppercase text-muted-foreground mr-1">
                            Période :
                        </span>
                        <button
                            type="button"
                            onClick={() => handlePreset('today')}
                            className={cn(
                                'rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors',
                                fromDate === todayStr && toDate === todayStr
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            Aujourd’hui
                        </button>
                        <button
                            type="button"
                            onClick={() => handlePreset('yesterday')}
                            className={cn(
                                'rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors',
                                fromDate === getYesterdayStr() && toDate === getYesterdayStr()
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            Hier
                        </button>
                        <button
                            type="button"
                            onClick={() => handlePreset('7d')}
                            className={cn(
                                'rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors',
                                fromDate === get7DaysAgoStr() && toDate === todayStr
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            7 derniers jours
                        </button>
                        <button
                            type="button"
                            onClick={() => handlePreset('month')}
                            className={cn(
                                'rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors',
                                fromDate === getMonthStartStr() && toDate === todayStr
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            Ce mois
                        </button>
                    </div>

                    {(query || channel !== 'all' || fromDate !== todayStr || toDate !== todayStr) && (
                        <button
                            type="button"
                            onClick={handleReset}
                            className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <RotateCcw className="size-3" />
                            <span>Réinitialiser les filtres</span>
                        </button>
                    )}
                </div>

                <div className="cash-filterbar flex-wrap">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                applyFilters();
                            }
                        }}
                        placeholder="Rechercher voyageur, téléphone, réf. billet…"
                        aria-label="Rechercher une opération"
                        className="cash-filter-search"
                    />

                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground shrink-0">
                        <span className="font-bold text-[0.6875rem] uppercase">Du</span>
                        <input
                            type="date"
                            value={fromDate}
                            onChange={(e) => setFromDate(e.target.value)}
                            aria-label="Date de début"
                            className="cash-filter-select h-10 rounded-xl px-2.5 text-xs font-medium"
                        />
                    </div>

                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground shrink-0">
                        <span className="font-bold text-[0.6875rem] uppercase">Au</span>
                        <input
                            type="date"
                            value={toDate}
                            onChange={(e) => setToDate(e.target.value)}
                            aria-label="Date de fin"
                            className="cash-filter-select h-10 rounded-xl px-2.5 text-xs font-medium"
                        />
                    </div>

                    <select
                        value={channel}
                        onChange={(e) => {
                            const nextChannel = e.target.value;
                            setChannel(nextChannel);
                            applyFilters(fromDate, toDate, nextChannel, query);
                        }}
                        aria-label="Canal de paiement"
                        className="cash-filter-select h-10 rounded-xl px-2.5 text-xs font-medium"
                    >
                        <option value="all">Tous les canaux</option>
                        <option value="cash">💵 Espèces</option>
                        <option value="mobile_money">📱 Tout Mobile Money</option>
                        <optgroup label="Bénin 🇧🇯">
                            <option value="mtn_momo">MTN MoMo (Bénin)</option>
                            <option value="moov_flooz">Moov Flooz (Bénin)</option>
                            <option value="celtiis_cash">Celtiis Cash (Bénin)</option>
                        </optgroup>
                        <optgroup label="Togo 🇹🇬">
                            <option value="tmoney_tg">T-Money (Togo)</option>
                            <option value="moov_flooz_tg">Moov Togo (Togo)</option>
                        </optgroup>
                        <optgroup label="Côte d’Ivoire 🇨🇮">
                            <option value="wave_ci">Wave CI (Côte d’Ivoire)</option>
                            <option value="orange_money_ci">Orange Money (Côte d’Ivoire)</option>
                            <option value="mtn_momo_ci">MTN CI (Côte d’Ivoire)</option>
                            <option value="moov_flooz_ci">Moov CI (Côte d’Ivoire)</option>
                        </optgroup>
                    </select>

                    <button
                        type="button"
                        onClick={() => applyFilters()}
                        className="btn btn-navy flex items-center gap-1.5 shrink-0"
                    >
                        <SlidersHorizontal className="size-3.5" />
                        <span>Filtrer</span>
                    </button>
                </div>
            </div>

            {/* Table: Chronological Movements (Cash System Style) */}
            <div className="cash-table-card print:border-none print:shadow-none">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-border/60 px-5 py-4">
                    <div>
                        <h2 className="text-sm font-extrabold text-foreground">
                            Journal chronologique des encaissements
                        </h2>
                        <span className="text-xs text-muted-foreground">
                            {data.date_label} · {data.movements.length} opération(s) enregistrée(s)
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="rounded-full bg-muted/70 px-2.5 py-1 text-xs font-bold text-foreground">
                            {data.movements.length} ligne(s)
                        </span>
                    </div>
                </div>

                {data.movements.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-12 text-center">
                        <div className="grid size-12 place-items-center rounded-2xl bg-muted text-muted-foreground mb-3">
                            <Banknote className="size-6" />
                        </div>
                        <h3 className="text-sm font-bold text-foreground">
                            Aucun encaissement pour cette sélection
                        </h3>
                        <p className="mt-1 text-xs text-muted-foreground max-w-sm">
                            Aucun mouvement ne correspond aux filtres appliqués (période, canal ou recherche).
                        </p>
                        {(query || channel !== 'all' || fromDate !== todayStr || toDate !== todayStr) && (
                            <button
                                type="button"
                                onClick={handleReset}
                                className="btn btn-outline btn-sm mt-4"
                            >
                                Réinitialiser les filtres
                            </button>
                        )}
                        {onNavigateToSale && (
                            <button
                                type="button"
                                onClick={onNavigateToSale}
                                className="btn btn-primary btn-sm mt-4 ml-2"
                            >
                                <PlusCircle className="size-4" />
                                <span>Nouvelle vente</span>
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="cash-table-scroll overflow-x-auto">
                        <table className="w-full min-w-[850px] text-left">
                            <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase border-b border-border/60">
                                <tr>
                                    <th className="px-4 py-3">Date & heure</th>
                                    <th className="px-4 py-3">Billet / Réf</th>
                                    <th className="px-4 py-3">Voyageur</th>
                                    <th className="px-4 py-3">Canal de paiement</th>
                                    <th className="px-4 py-3 text-right">Brut encaissé</th>
                                    <th className="px-4 py-3 text-right">Commission</th>
                                    <th className="px-4 py-3 text-right">Net dû KORA</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/40 text-xs">
                                {data.movements.map((m) => {
                                    const netInfo = m.network
                                        ? NETWORK_FLAGS[m.network]
                                        : null;
                                    const isCash = m.method === 'cash';

                                    return (
                                        <tr
                                            key={m.id}
                                            className="hover:bg-muted/30 transition-colors"
                                        >
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                <p className="font-mono text-xs font-black text-foreground">
                                                    {m.time_label}
                                                </p>
                                                <p className="mt-0.5 text-[0.6875rem] text-muted-foreground">
                                                    {m.date_label ?? ''}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                <span className="font-mono font-bold text-foreground bg-muted/60 px-2 py-0.5 rounded text-xs border border-border/50">
                                                    {m.ticket_reference}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="font-bold text-foreground">
                                                    {m.passenger_name}
                                                </div>
                                                <div className="text-[0.6875rem] text-muted-foreground font-mono">
                                                    {m.passenger_phone}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {isCash ? (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                                        <HandCoins className="size-3" />
                                                        <span>Espèces</span>
                                                    </span>
                                                ) : (
                                                    <div className="inline-flex flex-col items-start gap-0.5">
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:text-blue-400">
                                                            <Smartphone className="size-3" />
                                                            <span>{netInfo?.flag ?? '📱'}</span>
                                                            <span>
                                                                {netInfo?.label ??
                                                                    m.network_label ??
                                                                    'Mobile Money'}
                                                            </span>
                                                        </span>
                                                        {m.reference &&
                                                            m.reference !== '—' && (
                                                                <span className="font-mono text-[0.625rem] text-muted-foreground">
                                                                    Réf: {m.reference}
                                                                </span>
                                                            )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-right font-mono text-xs font-black text-foreground whitespace-nowrap">
                                                {m.gross_amount.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </td>
                                            <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-gold whitespace-nowrap">
                                                +
                                                {m.commission_amount.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </td>
                                            <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-foreground whitespace-nowrap">
                                                {m.net_amount.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot className="border-t-2 border-border/80 bg-muted/40 font-bold">
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-3 text-right text-xs font-black text-foreground"
                                    >
                                        Totaux consolidés ({data.movements.length} billet(s)) :
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs font-black text-foreground whitespace-nowrap">
                                        {data.gross_total.toLocaleString(
                                            'fr-FR',
                                        )}{' '}
                                        FCFA
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs font-black text-gold whitespace-nowrap">
                                        +
                                        {data.commission_total.toLocaleString(
                                            'fr-FR',
                                        )}{' '}
                                        FCFA
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs font-black text-foreground whitespace-nowrap">
                                        {data.net_total.toLocaleString(
                                            'fr-FR',
                                        )}{' '}
                                        FCFA
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>

            {/* Print Footer Notice */}
            <div className="hidden print:block text-center text-xs text-slate-500 pt-6 border-t border-slate-200">
                Document généré pour {point.name} ({point.code}) · KORA EXPRESS Bus Platform · {data.date_label}
            </div>
        </div>
    );
}

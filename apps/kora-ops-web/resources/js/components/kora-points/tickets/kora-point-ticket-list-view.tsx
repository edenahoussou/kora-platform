import {
    ChevronRight,
    Eye,
    ReceiptText,
    Search,
    Ticket,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type { PointSaleRecord, SettlementStatus } from '../mock/kora-point-mock-data';
import { KoraPointTicketDetailDrawer } from './kora-point-ticket-detail-drawer';

type Props = {
    sales: PointSaleRecord[];
    onStartNewSale: () => void;
    onRequestRefund?: (saleUuid: string) => void;
};

export function KoraPointTicketListView({
    sales,
    onStartNewSale,
    onRequestRefund,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | SettlementStatus>('all');
    const [selectedSale, setSelectedSale] = useState<PointSaleRecord | null>(null);

    // Filter logic
    const filteredSales = useMemo(() => {
        return sales.filter((sale) => {
            const matchesStatus =
                statusFilter === 'all' || sale.settlement_status === statusFilter;

            const q = searchQuery.toLowerCase().trim();
            const matchesSearch =
                !q ||
                sale.ticket_number.toLowerCase().includes(q) ||
                sale.short_code.toLowerCase().includes(q) ||
                sale.passenger_name.toLowerCase().includes(q) ||
                sale.passenger_phone.toLowerCase().includes(q) ||
                sale.route_label.toLowerCase().includes(q);

            return matchesStatus && matchesSearch;
        });
    }, [sales, statusFilter, searchQuery]);

    // Financial summaries based on current filtered view
    const totalAmount = filteredSales.reduce((acc, s) => acc + s.amount_paid, 0);
    const totalCommission = filteredSales.reduce((acc, s) => acc + s.commission_amount, 0);

    return (
        <div className="space-y-6">
            {/* Drawer for details */}
            <KoraPointTicketDetailDrawer
                sale={selectedSale}
                onClose={() => setSelectedSale(null)}
                onRequestRefund={onRequestRefund}
            />

            {/* Header */}
            <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA POINT / Espace Agent / Billets
                    </div>
                    <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                        Billets & Activité
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        Historique des titres de transport émis, statuts d'apurement et détail passagers.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onClick={onStartNewSale}
                        className="btn btn-primary"
                    >
                        <Ticket className="size-4" />
                        <span>Nouvelle vente</span>
                    </button>
                </div>
            </div>

            {/* Summary Cards */}
            <div className="cash-ledger-grid cash-reveal cash-reveal-delay-1 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-digital/10 text-digital">
                                <Ticket className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">Billets émis</span>
                                <small>Volume filtré</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>{filteredSales.length}</strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-kora text-white dark:bg-gold/20 dark:text-gold">
                                <Wallet className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">Recettes totales</span>
                                <small>Montant brut</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>{totalAmount.toLocaleString('fr-FR')} FCFA</strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-emerald-500/10 text-emerald-600">
                                <TrendingUp className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">Commissions</span>
                                <small>Acquises en direct</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>+{totalCommission.toLocaleString('fr-FR')} FCFA</strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-gold/15 text-gold">
                                <ReceiptText className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">À reverser</span>
                                <small>Dette nette KORA</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>{(totalAmount - totalCommission).toLocaleString('fr-FR')} FCFA</strong>
                    </div>
                </article>
            </div>

            {/* Filter Bar */}
            <div className="cash-filterbar cash-reveal cash-reveal-delay-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {/* Search */}
                <div className="relative flex-1 max-w-md">
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="text"
                        placeholder="Rechercher par billet N°, code court, voyageur..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="h-10 w-full rounded-xl border border-border bg-muted/40 pl-10 pr-4 text-xs font-semibold text-foreground placeholder:text-muted-foreground focus:bg-card focus:outline-none focus:ring-1 focus:ring-gold"
                    />
                </div>

                {/* Status Tabs */}
                <div className="flex flex-wrap items-center gap-1.5">
                    {[
                        { id: 'all', label: 'Tous' },
                        { id: 'open', label: 'À reverser' },
                        { id: 'in_settlement', label: 'Dans bordereau' },
                        { id: 'settled', label: 'Réglé' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setStatusFilter(tab.id as any)}
                            className={cn(
                                'btn btn-sm',
                                statusFilter === tab.id ? 'btn-navy' : 'btn-ghost',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Desktop Table View */}
            <div className="hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block overflow-hidden dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-[0.625rem] font-black uppercase tracking-wider text-slate-400 dark:border-slate-800 dark:bg-slate-950">
                            <th className="py-3.5 pl-5 pr-3">Billet & Code</th>
                            <th className="py-3.5 px-3">Date & Heure</th>
                            <th className="py-3.5 px-3">Voyageur</th>
                            <th className="py-3.5 px-3">Trajet / Départ</th>
                            <th className="py-3.5 px-3 text-right">Montant</th>
                            <th className="py-3.5 px-3 text-right">Commission</th>
                            <th className="py-3.5 px-3">Statut reversement</th>
                            <th className="py-3.5 pl-3 pr-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {filteredSales.map((sale) => (
                            <tr
                                key={sale.uuid}
                                onClick={() => setSelectedSale(sale)}
                                className="group cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td className="py-3 pl-5 pr-3">
                                    <div className="font-mono font-bold text-slate-900 dark:text-white">
                                        {sale.ticket_number}
                                    </div>
                                    <span className="inline-block rounded bg-gold/20 px-1.5 py-0.2 font-mono text-[0.625rem] font-black text-kora dark:text-gold">
                                        Code {sale.short_code}
                                    </span>
                                </td>
                                <td className="py-3 px-3 text-slate-500 whitespace-nowrap">
                                    {sale.date_label}
                                </td>
                                <td className="py-3 px-3">
                                    <div className="font-bold text-slate-800 dark:text-slate-200">
                                        {sale.passenger_name}
                                    </div>
                                    <div className="text-[0.6875rem] font-mono text-slate-400">
                                        {sale.passenger_phone}
                                    </div>
                                </td>
                                <td className="py-3 px-3">
                                    <div className="font-semibold text-slate-900 dark:text-white">
                                        {sale.route_label}
                                    </div>
                                    <div className="text-[0.6875rem] text-slate-400">
                                        {sale.departure_ref} · Siège {sale.seat_number}
                                    </div>
                                </td>
                                <td className="py-3 px-3 text-right font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    {sale.amount_paid.toLocaleString('fr-FR')} FCFA
                                </td>
                                <td className="py-3 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                    +{sale.commission_amount.toLocaleString('fr-FR')} FCFA
                                </td>
                                <td className="py-3 px-3 whitespace-nowrap">
                                    {sale.settlement_status === 'open' && (
                                        <KoraStatusBadge
                                            label="À reverser"
                                            tone="amber"
                                            variant="solid"
                                        />
                                    )}
                                    {sale.settlement_status === 'in_settlement' && (
                                        <KoraStatusBadge
                                            label="Dans bordereau"
                                            tone="blue"
                                            variant="solid"
                                        />
                                    )}
                                    {sale.settlement_status === 'settled' && (
                                        <KoraStatusBadge
                                            label="Réglé"
                                            tone="emerald"
                                            variant="solid"
                                        />
                                    )}
                                </td>
                                <td className="py-3 pl-3 pr-5 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setSelectedSale(sale);
                                        }}
                                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[0.6875rem] font-bold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        <Eye className="size-3" />
                                        <span>Détail</span>
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile Cards View (Adaptive on Phone) */}
            <div className="space-y-3 md:hidden">
                {filteredSales.map((sale) => (
                    <article
                        key={sale.uuid}
                        onClick={() => setSelectedSale(sale)}
                        className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition active:scale-[0.99] dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div className="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                            <div>
                                <span className="font-mono text-xs font-black text-slate-900 dark:text-white">
                                    {sale.ticket_number}
                                </span>
                                <span className="ml-2 rounded bg-gold/20 px-1.5 py-0.2 font-mono text-[0.625rem] font-black text-kora dark:text-gold">
                                    Code {sale.short_code}
                                </span>
                            </div>
                            {sale.settlement_status === 'open' && (
                                <KoraStatusBadge label="À reverser" tone="amber" variant="solid" />
                            )}
                            {sale.settlement_status === 'in_settlement' && (
                                <KoraStatusBadge label="Dans bordereau" tone="blue" variant="solid" />
                            )}
                            {sale.settlement_status === 'settled' && (
                                <KoraStatusBadge label="Réglé" tone="emerald" variant="solid" />
                            )}
                        </div>

                        <div className="my-3 space-y-1.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-400">Voyageur :</span>
                                <strong className="text-slate-900 dark:text-white">
                                    {sale.passenger_name}
                                </strong>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-slate-400">Trajet :</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">
                                    {sale.route_label}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-slate-400">Départ / Siège :</span>
                                <span className="text-slate-600 dark:text-slate-400">
                                    {sale.departure_ref} · Siège {sale.seat_number}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-t border-slate-100 pt-2 dark:border-slate-800">
                                <span className="font-bold text-slate-700 dark:text-slate-300">
                                    Montant payé :
                                </span>
                                <strong className="font-mono text-sm text-slate-900 dark:text-white">
                                    {sale.amount_paid.toLocaleString('fr-FR')} FCFA
                                </strong>
                            </div>
                            <div className="flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                                <span>Commission Point :</span>
                                <span>+{sale.commission_amount.toLocaleString('fr-FR')} FCFA</span>
                            </div>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-2 text-xs dark:border-slate-800">
                            <span className="text-[0.6875rem] text-slate-400">{sale.date_label}</span>
                            <span className="flex items-center gap-1 font-bold text-blue-600 dark:text-blue-400">
                                Consulter le reçu <ChevronRight className="size-3.5" />
                            </span>
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}

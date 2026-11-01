import { Search, Ticket, Eye, ChevronRight } from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    PointSaleRecord,
    SettlementStatus,
} from '../mock/kora-point-mock-data';
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
    const [statusFilter, setStatusFilter] = useState<'all' | SettlementStatus>(
        'all',
    );
    const [selectedSale, setSelectedSale] = useState<PointSaleRecord | null>(
        null,
    );

    // Filter logic
    const filteredSales = useMemo(() => {
        return sales.filter((sale) => {
            const matchesStatus =
                statusFilter === 'all' ||
                sale.settlement_status === statusFilter;

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
    const totalAmount = filteredSales.reduce(
        (acc, s) => acc + s.amount_paid,
        0,
    );
    const totalCommission = filteredSales.reduce(
        (acc, s) => acc + s.commission_amount,
        0,
    );

    return (
        <div className="space-y-6">
            {/* Drawer for details */}
            <KoraPointTicketDetailDrawer
                sale={selectedSale}
                onClose={() => setSelectedSale(null)}
                onRequestRefund={onRequestRefund}
            />

            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span className="text-[0.625rem] font-black tracking-wider text-slate-500 uppercase">
                        Journal des ventes du Point
                    </span>
                    <h1 className="text-xl font-black text-slate-900 sm:text-2xl dark:text-white">
                        Billets & Activité
                    </h1>
                </div>

                <button
                    type="button"
                    onClick={onStartNewSale}
                    className="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl bg-gold px-4 text-xs font-black tracking-wider text-kora uppercase shadow-md transition hover:bg-gold/90"
                >
                    <Ticket className="size-4" />
                    <span>Nouvelle vente</span>
                </button>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-[0.625rem] font-bold text-slate-400 uppercase">
                        Billets affichés
                    </span>
                    <strong className="mt-1 block font-mono text-xl font-black text-slate-900 dark:text-white">
                        {filteredSales.length}
                    </strong>
                </div>

                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-[0.625rem] font-bold text-slate-400 uppercase">
                        Recettes totales
                    </span>
                    <strong className="mt-1 block font-mono text-xl font-black text-slate-900 dark:text-white">
                        {totalAmount.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>

                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-[0.625rem] font-bold text-slate-400 uppercase">
                        Commissions acquises
                    </span>
                    <strong className="mt-1 block font-mono text-xl font-black text-emerald-600 dark:text-emerald-400">
                        +{totalCommission.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>

                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-[0.625rem] font-bold text-slate-400 uppercase">
                        À reverser net
                    </span>
                    <strong className="mt-1 block font-mono text-xl font-black text-gold">
                        {(totalAmount - totalCommission).toLocaleString(
                            'fr-FR',
                        )}{' '}
                        FCFA
                    </strong>
                </div>
            </div>

            {/* Filter Bar */}
            <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900">
                {/* Search */}
                <div className="relative max-w-md flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Rechercher par billet N°, code court, voyageur..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pr-4 pl-10 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:bg-white focus:ring-1 focus:ring-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
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
                                'rounded-xl px-3 py-1.5 text-xs font-bold transition',
                                statusFilter === tab.id
                                    ? 'bg-[var(--kora)] text-white shadow-sm dark:bg-digital dark:text-kora'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Desktop Table View */}
            <div className="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-[0.625rem] font-black tracking-wider text-slate-400 uppercase dark:border-slate-800 dark:bg-slate-950">
                            <th className="py-3.5 pr-3 pl-5">Billet & Code</th>
                            <th className="px-3 py-3.5">Date & Heure</th>
                            <th className="px-3 py-3.5">Voyageur</th>
                            <th className="px-3 py-3.5">Trajet / Départ</th>
                            <th className="px-3 py-3.5 text-right">Montant</th>
                            <th className="px-3 py-3.5 text-right">
                                Commission
                            </th>
                            <th className="px-3 py-3.5">Statut reversement</th>
                            <th className="py-3.5 pr-5 pl-3 text-right">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {filteredSales.map((sale) => (
                            <tr
                                key={sale.uuid}
                                onClick={() => setSelectedSale(sale)}
                                className="group cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td className="py-3 pr-3 pl-5">
                                    <div className="font-mono font-bold text-slate-900 dark:text-white">
                                        {sale.ticket_number}
                                    </div>
                                    <span className="py-0.2 inline-block rounded bg-gold/20 px-1.5 font-mono text-[0.625rem] font-black text-kora dark:text-gold">
                                        Code {sale.short_code}
                                    </span>
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-slate-500">
                                    {sale.date_label}
                                </td>
                                <td className="px-3 py-3">
                                    <div className="font-bold text-slate-800 dark:text-slate-200">
                                        {sale.passenger_name}
                                    </div>
                                    <div className="font-mono text-[0.6875rem] text-slate-400">
                                        {sale.passenger_phone}
                                    </div>
                                </td>
                                <td className="px-3 py-3">
                                    <div className="font-semibold text-slate-900 dark:text-white">
                                        {sale.route_label}
                                    </div>
                                    <div className="text-[0.6875rem] text-slate-400">
                                        {sale.departure_ref} · Siège{' '}
                                        {sale.seat_number}
                                    </div>
                                </td>
                                <td className="px-3 py-3 text-right font-mono font-bold whitespace-nowrap text-slate-900 dark:text-white">
                                    {sale.amount_paid.toLocaleString('fr-FR')}{' '}
                                    FCFA
                                </td>
                                <td className="px-3 py-3 text-right font-mono font-bold whitespace-nowrap text-emerald-600 dark:text-emerald-400">
                                    +
                                    {sale.commission_amount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap">
                                    {sale.settlement_status === 'open' && (
                                        <KoraStatusBadge
                                            label="À reverser"
                                            tone="amber"
                                            variant="solid"
                                        />
                                    )}
                                    {sale.settlement_status ===
                                        'in_settlement' && (
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
                                <td className="py-3 pr-5 pl-3 text-right whitespace-nowrap">
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
                                <span className="py-0.2 ml-2 rounded bg-gold/20 px-1.5 font-mono text-[0.625rem] font-black text-kora dark:text-gold">
                                    Code {sale.short_code}
                                </span>
                            </div>
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
                        </div>

                        <div className="my-3 space-y-1.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-400">
                                    Voyageur :
                                </span>
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
                                <span className="text-slate-400">
                                    Départ / Siège :
                                </span>
                                <span className="text-slate-600 dark:text-slate-400">
                                    {sale.departure_ref} · Siège{' '}
                                    {sale.seat_number}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-t border-slate-100 pt-2 dark:border-slate-800">
                                <span className="font-bold text-slate-700 dark:text-slate-300">
                                    Montant payé :
                                </span>
                                <strong className="font-mono text-sm text-slate-900 dark:text-white">
                                    {sale.amount_paid.toLocaleString('fr-FR')}{' '}
                                    FCFA
                                </strong>
                            </div>
                            <div className="flex items-center justify-between font-bold text-emerald-600 dark:text-emerald-400">
                                <span>Commission Point :</span>
                                <span>
                                    +
                                    {sale.commission_amount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-2 text-xs dark:border-slate-800">
                            <span className="text-[0.6875rem] text-slate-400">
                                {sale.date_label}
                            </span>
                            <span className="flex items-center gap-1 font-bold text-blue-600 dark:text-blue-400">
                                Consulter le reçu{' '}
                                <ChevronRight className="size-3.5" />
                            </span>
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}

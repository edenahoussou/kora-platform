import {
    Building2,
    Store,
    TrendingUp,
    Ticket,
    Coins,
    Clock,
    CheckCircle2,
    AlertTriangle,
    Eye,
    Search,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    OrganizationSubPoint,
    PointSaleRecord,
    PointRefundRecord,
} from '../mock/kora-point-mock-data';
import { KoraOrgSubPointDrawer } from './kora-org-subpoint-drawer';

type Props = {
    organizationName: string;
    subpoints: OrganizationSubPoint[];
    recentSales: PointSaleRecord[];
    recentRefunds: PointRefundRecord[];
};

export function KoraOrgSupervisionView({
    organizationName,
    subpoints,
    recentSales,
}: Props) {
    const [selectedSubpointId, setSelectedSubpointId] = useState<string>('ALL');
    const [searchQuery, setSearchQuery] = useState('');
    const [activeDrawerSubpoint, setActiveDrawerSubpoint] = useState<OrganizationSubPoint | null>(null);

    // Filtered subpoints
    const filteredSubpoints = useMemo(() => {
        return subpoints.filter((sp) => {
            const matchesScope = selectedSubpointId === 'ALL' || sp.uuid === selectedSubpointId;
            const matchesSearch =
                sp.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                sp.code.toLowerCase().includes(searchQuery.toLowerCase()) ||
                sp.city.toLowerCase().includes(searchQuery.toLowerCase()) ||
                sp.manager_name.toLowerCase().includes(searchQuery.toLowerCase());

            return matchesScope && matchesSearch;
        });
    }, [subpoints, selectedSubpointId, searchQuery]);

    // Consolidated metrics
    const networkMetrics = useMemo(() => {
        let totalMonthlySales = 0;
        let totalDailySales = 0;
        let totalDailyTickets = 0;
        let totalCommission = 0;
        let totalExposure = 0;
        let totalCreditLimit = 0;
        let alertPointsCount = 0;

        subpoints.forEach((sp) => {
            totalMonthlySales += sp.monthly_sales_amount;
            totalDailySales += sp.daily_sales_amount;
            totalDailyTickets += sp.daily_tickets_count;
            totalCommission += sp.monthly_commission_earned;
            totalExposure += sp.current_exposure;
            totalCreditLimit += sp.credit_limit;

            if (sp.status === 'WARNING_THRESHOLD' || sp.status === 'LIMIT_REACHED') {
                alertPointsCount += 1;
            }
        });

        const overallUsagePercentage =
            totalCreditLimit > 0 ? Math.round((totalExposure / totalCreditLimit) * 100) : 0;

        return {
            totalMonthlySales,
            totalDailySales,
            totalDailyTickets,
            totalCommission,
            totalExposure,
            totalCreditLimit,
            overallUsagePercentage,
            alertPointsCount,
        };
    }, [subpoints]);

    const getStatusBadge = (status: OrganizationSubPoint['status']) => {
        switch (status) {
            case 'ACTIVE':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="emerald"
                        icon={<CheckCircle2 className="h-3 w-3" />}
                    >
                        Opérationnel
                    </KoraStatusBadge>
                );
            case 'WARNING_THRESHOLD':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="amber"
                        icon={<Clock className="h-3 w-3" />}
                    >
                        Seuil 80%
                    </KoraStatusBadge>
                );
            case 'LIMIT_REACHED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="destructive"
                        icon={<AlertTriangle className="h-3 w-3" />}
                    >
                        Plafond 100%
                    </KoraStatusBadge>
                );
            default:
                return (
                    <KoraStatusBadge variant="solid" colorScheme="navy">
                        Inactif
                    </KoraStatusBadge>
                );
        }
    };

    return (
        <div className="space-y-6">
            {/* Drawer de consultation détaillée d'un bureau */}
            <KoraOrgSubPointDrawer
                subpoint={activeDrawerSubpoint}
                isOpen={activeDrawerSubpoint !== null}
                onClose={() => setActiveDrawerSubpoint(null)}
            />

            {/* Grand bandeau d'accueil de supervision */}
            <div className="relative overflow-hidden rounded-3xl bg-navy p-6 sm:p-8 text-white shadow-xl">
                <div className="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gold">
                            <Building2 className="h-4 w-4" />
                            Portail de supervision partenaire · KORA BUS
                        </div>
                        <h1 className="mt-2 text-2xl sm:text-3xl font-black text-white">
                            {organizationName}
                        </h1>
                        <p className="mt-1 text-xs sm:text-sm text-slate-300 max-w-xl">
                            Supervision consolidée du réseau de distribution billetterie ({subpoints.length} bureaux de poste partenaires connectés).
                        </p>
                    </div>

                    {/* Scope Switcher in Header */}
                    <div className="rounded-2xl bg-white/10 p-4 backdrop-blur-md border border-white/10 min-w-[280px]">
                        <span className="text-[11px] font-bold text-slate-300 uppercase tracking-wider block mb-1">
                            Périmètre de consultation
                        </span>
                        <select
                            value={selectedSubpointId}
                            onChange={(e) => setSelectedSubpointId(e.target.value)}
                            className="w-full rounded-xl bg-navy-light px-3 py-2 text-xs font-bold text-white border border-white/20 focus:outline-none focus:border-gold"
                        >
                            <option value="ALL">Réseau consolidé (Tous les 5 bureaux)</option>
                            {subpoints.map((sp) => (
                                <option key={sp.uuid} value={sp.uuid}>
                                    {sp.name} ({sp.city})
                                </option>
                            ))}
                        </select>
                        <span className="text-[10px] text-slate-400 mt-1 block">
                            Rôle : Responsable Organisation (Lecture seule)
                        </span>
                    </div>
                </div>
            </div>

            {/* Alert Banner if Points are near limit */}
            {networkMetrics.alertPointsCount > 0 && (
                <div className="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs dark:border-amber-900/60 dark:bg-amber-950/40">
                    <div className="flex items-start gap-3">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-amber-600 mt-0.5" />
                        <div>
                            <p className="font-bold text-amber-900 dark:text-amber-200">
                                {networkMetrics.alertPointsCount} bureau(x) nécessitent l'attention du Trésorier
                            </p>
                            <p className="text-amber-800 dark:text-amber-300 mt-0.5">
                                Des bureaux ont dépassé 80 % ou atteint 100 % de leur plafond de crédit. Un reversement groupé doit être préparé par la trésorerie pour réactiver les émissions de billets.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* 4 KPIs Consolidés Réseau */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* Volume Ventes Réseau */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Volume Ventes Réseau (Mois)
                        </span>
                        <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                            <TrendingUp className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {networkMetrics.totalMonthlySales.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Aujourd'hui : {networkMetrics.totalDailySales.toLocaleString()} FCFA
                        </p>
                    </div>
                </div>

                {/* Billets émis */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Billets émis (Aujourd'hui)
                        </span>
                        <div className="rounded-lg bg-navy/10 p-2 text-navy dark:bg-gold/15 dark:text-gold">
                            <Ticket className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {networkMetrics.totalDailyTickets} billets
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Sur l'ensemble des 5 agences postales
                        </p>
                    </div>
                </div>

                {/* Commissions acquises */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Commissions acquises (3 %)
                        </span>
                        <div className="rounded-lg bg-gold/15 p-2 text-gold-dark dark:text-gold">
                            <Coins className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-gold-dark dark:text-gold">
                            {networkMetrics.totalCommission.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Rémunération conservée à la source
                        </p>
                    </div>
                </div>

                {/* Encours consolidé */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Encours consolidé réseau
                        </span>
                        <div className="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                            <Clock className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {networkMetrics.totalExposure.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Plafond global : {networkMetrics.totalCreditLimit.toLocaleString()} FCFA ({networkMetrics.overallUsagePercentage} %)
                        </p>
                    </div>
                </div>
            </div>

            {/* Network Subpoints Section */}
            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <Store className="h-5 w-5 text-gold-dark dark:text-gold" />
                            Réseau des bureaux rattachés
                        </h2>
                        <p className="text-xs text-slate-500">
                            Suivi opérationnel, taux d'utilisation des plafonds et responsables locaux
                        </p>
                    </div>

                    <div className="relative w-full sm:w-72">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Filtrer bureau, ville, responsable..."
                            className="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                </div>

                {/* Subpoints Table */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table className="w-full text-left text-xs">
                        <thead className="border-b border-slate-100 bg-slate-50/75 text-slate-500 dark:border-slate-800 dark:bg-slate-800/40">
                            <tr>
                                <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                    Bureau & Code
                                </th>
                                <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                    Localisation & Contact
                                </th>
                                <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                    Statut
                                </th>
                                <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                    Ventes du mois
                                </th>
                                <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                    Encours / Plafond
                                </th>
                                <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {filteredSubpoints.map((sp) => (
                                <tr
                                    key={sp.uuid}
                                    onClick={() => setActiveDrawerSubpoint(sp)}
                                    className="cursor-pointer transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/30"
                                >
                                    <td className="px-5 py-4">
                                        <span className="font-bold text-slate-900 dark:text-white block">
                                            {sp.name}
                                        </span>
                                        <span className="font-mono text-[11px] text-slate-400">
                                            {sp.code}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className="font-semibold text-slate-800 dark:text-slate-200 block">
                                            {sp.city}
                                        </span>
                                        <span className="text-[11px] text-slate-500">
                                            {sp.manager_name} ({sp.phone})
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">{getStatusBadge(sp.status)}</td>
                                    <td className="px-5 py-4 text-right">
                                        <span className="font-black text-slate-900 dark:text-white block">
                                            {sp.monthly_sales_amount.toLocaleString()} FCFA
                                        </span>
                                        <span className="text-[11px] text-emerald-600 dark:text-emerald-400">
                                            Com : {sp.monthly_commission_earned.toLocaleString()} FCFA
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        <div className="space-y-1">
                                            <span className="font-black text-slate-900 dark:text-white block">
                                                {sp.current_exposure.toLocaleString()} FCFA
                                            </span>
                                            <div className="h-1.5 w-24 ml-auto rounded-full bg-slate-100 overflow-hidden dark:bg-slate-700">
                                                <div
                                                    className={cn(
                                                        'h-full rounded-full',
                                                        sp.usage_percentage >= 100
                                                            ? 'bg-rose-600'
                                                            : sp.usage_percentage >= 80
                                                            ? 'bg-amber-500'
                                                            : 'bg-emerald-500',
                                                    )}
                                                    style={{
                                                        width: `${Math.min(100, sp.usage_percentage)}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-[10px] text-slate-400 block">
                                                {sp.usage_percentage}% de {sp.credit_limit.toLocaleString()} FCFA
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setActiveDrawerSubpoint(sp);
                                            }}
                                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        >
                                            <Eye className="h-3.5 w-3.5 text-slate-500" />
                                            Consulter
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Consolidated Network Activity Stream */}
            <div className="space-y-3">
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Flux d'activité consolidé en direct (Ventes & Remboursements du réseau)
                </h3>
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-3">
                    {recentSales.slice(0, 5).map((sale) => (
                        <div
                            key={sale.uuid}
                            className="flex items-center justify-between py-2 border-b border-slate-100 last:border-0 dark:border-slate-800 text-xs"
                        >
                            <div className="flex items-center gap-3">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-navy/10 text-navy dark:bg-gold/15 dark:text-gold">
                                    <Ticket className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="font-bold text-slate-900 dark:text-white">
                                        {sale.ticket_number} · {sale.passenger_name}
                                    </p>
                                    <span className="text-[11px] text-slate-500">
                                        {sale.route_label} · Bureau Akpakpa ({sale.date_label})
                                    </span>
                                </div>
                            </div>
                            <div className="text-right">
                                <span className="font-black text-slate-900 dark:text-white block">
                                    {sale.amount_paid.toLocaleString()} FCFA
                                </span>
                                <span className="text-[11px] text-emerald-600 dark:text-emerald-400">
                                    Com Point : +{sale.commission_amount} FCFA
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

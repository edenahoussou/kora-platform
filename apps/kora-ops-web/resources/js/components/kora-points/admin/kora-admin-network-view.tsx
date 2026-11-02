import {
    Store,
    Building2,
    Search,
    Plus,
    Clock,
    CheckCircle2,
    AlertTriangle,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    AdminNetworkPoint,
    AdminOrganization,
} from '../mock/kora-point-mock-data';
import { KoraAdminPointWizardDialog } from './kora-admin-point-wizard-dialog';

type Props = {
    points: AdminNetworkPoint[];
    organizations: AdminOrganization[];
    onCreatePoint: (data: any) => void;
};

export function KoraAdminNetworkView({
    points,
    organizations,
    onCreatePoint,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [typeFilter, setTypeFilter] = useState<
        'ALL' | 'AUTONOMOUS' | 'ORGANIZATION_ATTACHED'
    >('ALL');
    const [statusFilter, setStatusFilter] = useState<string>('ALL');
    const [departmentFilter, setDepartmentFilter] = useState<string>('ALL');
    const [isWizardOpen, setIsWizardOpen] = useState(false);

    // Filtered points
    const filteredPoints = useMemo(() => {
        return points.filter((pt) => {
            const matchesSearch =
                pt.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                pt.code.toLowerCase().includes(searchQuery.toLowerCase()) ||
                pt.city.toLowerCase().includes(searchQuery.toLowerCase()) ||
                pt.manager_name
                    .toLowerCase()
                    .includes(searchQuery.toLowerCase());

            const matchesType = typeFilter === 'ALL' || pt.type === typeFilter;
            const matchesStatus =
                statusFilter === 'ALL' || pt.status === statusFilter;
            const matchesDept =
                departmentFilter === 'ALL' ||
                pt.department === departmentFilter;

            return matchesSearch && matchesType && matchesStatus && matchesDept;
        });
    }, [points, searchQuery, typeFilter, statusFilter, departmentFilter]);

    // Network metrics
    const stats = useMemo(() => {
        let totalMonthlySales = 0;
        let totalExposure = 0;
        let alertCount = 0;
        let activeCount = 0;

        points.forEach((pt) => {
            totalMonthlySales += pt.monthly_sales_amount;
            totalExposure += pt.current_exposure;

            if (pt.status === 'ACTIVE') {
                activeCount += 1;
            }

            if (
                pt.status === 'WARNING_THRESHOLD' ||
                pt.status === 'LIMIT_REACHED' ||
                pt.status === 'SUSPENDED'
            ) {
                alertCount += 1;
            }
        });

        return { totalMonthlySales, totalExposure, alertCount, activeCount };
    }, [points]);

    const getStatusBadge = (status: AdminNetworkPoint['status']) => {
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
            case 'SUSPENDED':
                return (
                    <KoraStatusBadge variant="solid" colorScheme="destructive">
                        Suspendu
                    </KoraStatusBadge>
                );
        }
    };

    return (
        <div className="space-y-6">
            {/* Modal de création d'un Point */}
            <KoraAdminPointWizardDialog
                isOpen={isWizardOpen}
                organizations={organizations}
                onClose={() => setIsWizardOpen(false)}
                onSubmit={(data) => {
                    const organization = organizations.find(
                        (item) => item.uuid === data.organizationUuid,
                    );
                    const frequency =
                        data.settlementFrequency ??
                        organization?.default_terms.settlement_frequency_days;

                    onCreatePoint({
                        name: data.name,
                        type: data.type,
                        organizationName: organization?.name,
                        city: data.city,
                        department: data.department,
                        managerName: data.managerName,
                        phone: data.phone ?? '',
                        creditLimit: data.creditLimit,
                        commissionRateBps:
                            data.commissionRateBps ??
                            organization?.default_terms.commission_rate_bps ??
                            0,
                        settlementFrequencyDays:
                            frequency === 'monthly' || frequency === 30
                                ? 30
                                : frequency === 'daily' || frequency === 1
                                  ? 1
                                  : 7,
                        gracePeriodDays:
                            organization?.default_terms.grace_period_days ?? 3,
                    });
                }}
            />

            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-black text-slate-900 dark:text-white">
                        <Store className="text-gold-dark h-6 w-6 dark:text-gold" />
                        Réseau Commercial KORA POINT
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Administration globale des Points de vente Bus
                        partenaires (autonomes et réseaux rattachés)
                    </p>
                </div>

                <button
                    type="button"
                    onClick={() => setIsWizardOpen(true)}
                    className="hover:bg-gold-light inline-flex items-center justify-center gap-2 rounded-xl bg-gold px-5 py-2.5 text-xs font-black tracking-wider text-kora uppercase shadow-sm focus:ring-2 focus:ring-gold focus:outline-none"
                >
                    <Plus className="h-4 w-4" />
                    Créer un nouveau Point +
                </button>
            </div>

            {/* KPI Banner */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                        Points du réseau
                    </span>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {points.length} Points
                        </span>
                        <p className="mt-1 text-xs font-bold text-emerald-600">
                            {stats.activeCount} opérationnels
                        </p>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                        Volume mensuel réseau
                    </span>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {stats.totalMonthlySales.toLocaleString()} FCFA
                        </span>
                        <p className="mt-1 text-xs text-slate-500">
                            Recettes brutes billetterie
                        </p>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                        Encours global à recouvrer
                    </span>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-amber-600 dark:text-amber-400">
                            {stats.totalExposure.toLocaleString()} FCFA
                        </span>
                        <p className="mt-1 text-xs text-slate-500">
                            Dettes nettes en circulation
                        </p>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                        Points sous alerte
                    </span>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-rose-600 dark:text-rose-400">
                            {stats.alertCount} Point(s)
                        </span>
                        <p className="mt-1 text-xs text-slate-500">
                            Plafond ou seuil dépassé
                        </p>
                    </div>
                </div>
            </div>

            {/* Filter Bar */}
            <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between dark:border-slate-800 dark:bg-slate-900">
                <div className="relative flex-1">
                    <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Rechercher par nom, code, ville, gérant..."
                        className="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pr-4 pl-9 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {/* Type Filter */}
                    <select
                        value={typeFilter}
                        onChange={(e) => setTypeFilter(e.target.value as any)}
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <option value="ALL">Tous types</option>
                        <option value="AUTONOMOUS">Points Autonomes</option>
                        <option value="ORGANIZATION_ATTACHED">
                            Rattachés Organisation
                        </option>
                    </select>

                    {/* Statut Filter */}
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <option value="ALL">Tous statuts</option>
                        <option value="ACTIVE">Opérationnels</option>
                        <option value="WARNING_THRESHOLD">
                            Alerte seuil 80%
                        </option>
                        <option value="LIMIT_REACHED">Plafond 100%</option>
                        <option value="SUSPENDED">Suspendus</option>
                    </select>

                    {/* Département Filter */}
                    <select
                        value={departmentFilter}
                        onChange={(e) => setDepartmentFilter(e.target.value)}
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <option value="ALL">Tous départements</option>
                        <option value="Littoral">Littoral (Cotonou)</option>
                        <option value="Borgou">Borgou (Parakou)</option>
                        <option value="Ouémé">Ouémé (Porto-Novo)</option>
                        <option value="Zou">Zou (Bohicon)</option>
                        <option value="Atlantique">Atlantique (Calavi)</option>
                    </select>
                </div>
            </div>

            {/* Content List: Table & Mobile Cards */}
            {filteredPoints.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <Store className="mx-auto h-8 w-8 text-slate-400" />
                    <h3 className="mt-2 text-sm font-bold text-slate-900 dark:text-white">
                        Aucun Point trouvé
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Modifiez vos filtres de recherche ou créez un nouveau
                        Point.
                    </p>
                </div>
            ) : (
                <>
                    {/* Desktop Table View */}
                    <div className="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block dark:border-slate-800 dark:bg-slate-900">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-100 bg-slate-50/75 text-slate-500 dark:border-slate-800 dark:bg-slate-800/40">
                                <tr>
                                    <th className="px-5 py-3.5 font-bold tracking-wider uppercase">
                                        Point & Code
                                    </th>
                                    <th className="px-5 py-3.5 font-bold tracking-wider uppercase">
                                        Type & Organisation
                                    </th>
                                    <th className="px-5 py-3.5 font-bold tracking-wider uppercase">
                                        Localisation & Gérant
                                    </th>
                                    <th className="px-5 py-3.5 font-bold tracking-wider uppercase">
                                        Statut
                                    </th>
                                    <th className="px-5 py-3.5 text-right font-bold tracking-wider uppercase">
                                        Encours / Plafond
                                    </th>
                                    <th className="px-5 py-3.5 text-right font-bold tracking-wider uppercase">
                                        Commission
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {filteredPoints.map((pt) => (
                                    <tr
                                        key={pt.uuid}
                                        className="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/30"
                                    >
                                        <td className="px-5 py-4">
                                            <span className="block font-bold text-slate-900 dark:text-white">
                                                {pt.name}
                                            </span>
                                            <span className="font-mono text-[11px] text-slate-400">
                                                {pt.code} · Créé le{' '}
                                                {pt.created_at}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {pt.type === 'AUTONOMOUS' ? (
                                                <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                    <Store className="h-3 w-3" />{' '}
                                                    Autonome
                                                </span>
                                            ) : (
                                                <div className="space-y-0.5">
                                                    <span className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                                        <Building2 className="h-3 w-3" />{' '}
                                                        Rattaché
                                                    </span>
                                                    <span className="block max-w-xs truncate text-[11px] text-slate-500">
                                                        {pt.organization_name}
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="block font-semibold text-slate-800 dark:text-slate-200">
                                                {pt.city} ({pt.department})
                                            </span>
                                            <span className="text-[11px] text-slate-500">
                                                {pt.manager_name} · {pt.phone}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {getStatusBadge(pt.status)}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <div className="space-y-1">
                                                <span className="block font-black text-slate-900 dark:text-white">
                                                    {pt.current_exposure.toLocaleString()}{' '}
                                                    FCFA
                                                </span>
                                                <div className="ml-auto h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            pt.usage_percentage >=
                                                                100
                                                                ? 'bg-rose-600'
                                                                : pt.usage_percentage >=
                                                                    80
                                                                  ? 'bg-amber-500'
                                                                  : 'bg-emerald-500',
                                                        )}
                                                        style={{
                                                            width: `${Math.min(100, pt.usage_percentage)}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="block text-[10px] text-slate-400">
                                                    {pt.usage_percentage}% de{' '}
                                                    {pt.credit_limit.toLocaleString()}{' '}
                                                    FCFA
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <span className="text-gold-dark block font-bold dark:text-gold">
                                                {(
                                                    pt.commission_rate_bps / 100
                                                ).toFixed(2)}{' '}
                                                %
                                            </span>
                                            <span className="text-[10px] text-slate-400">
                                                {pt.settlement_frequency_days}j
                                                reversement
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card Stack */}
                    <div className="space-y-3 lg:hidden">
                        {filteredPoints.map((pt) => (
                            <div
                                key={pt.uuid}
                                className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="block text-sm font-bold text-slate-900 dark:text-white">
                                            {pt.name}
                                        </span>
                                        <span className="font-mono text-[11px] text-slate-400">
                                            {pt.code} · {pt.city}
                                        </span>
                                    </div>
                                    {getStatusBadge(pt.status)}
                                </div>

                                <div className="grid grid-cols-2 gap-2 border-t border-slate-100 pt-1 text-xs dark:border-slate-800">
                                    <div>
                                        <span className="block text-[11px] text-slate-400">
                                            Encours
                                        </span>
                                        <span className="font-black text-slate-900 dark:text-white">
                                            {pt.current_exposure.toLocaleString()}{' '}
                                            FCFA
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-[11px] text-slate-400">
                                            Commission
                                        </span>
                                        <span className="text-gold-dark font-bold dark:text-gold">
                                            {(
                                                pt.commission_rate_bps / 100
                                            ).toFixed(2)}{' '}
                                            %
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

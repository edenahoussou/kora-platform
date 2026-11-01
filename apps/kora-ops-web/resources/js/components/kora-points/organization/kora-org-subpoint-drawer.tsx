import {
    X,
    Building2,
    MapPin,
    Phone,
    User,
    Clock,
    CheckCircle2,
    AlertTriangle,
    Info,
} from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type { OrganizationSubPoint } from '../mock/kora-point-mock-data';

type Props = {
    subpoint: OrganizationSubPoint | null;
    isOpen: boolean;
    onClose: () => void;
};

export function KoraOrgSubPointDrawer({ subpoint, isOpen, onClose }: Props) {
    if (!isOpen || !subpoint) {
        return null;
    }

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
                        Seuil 80 % dépassé
                    </KoraStatusBadge>
                );
            case 'LIMIT_REACHED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="destructive"
                        icon={<AlertTriangle className="h-3 w-3" />}
                    >
                        Plafond 100 % (Ventes bloquées)
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
        <div
            className="fixed inset-0 z-50 animate-in overflow-hidden bg-slate-950/60 backdrop-blur-sm duration-200 fade-in"
            role="dialog"
            aria-modal="true"
        >
            <div className="absolute inset-y-0 right-0 flex max-w-full pl-10">
                <div className="flex h-full w-screen max-w-xl flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                    {/* Header */}
                    <div className="flex shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                        <div className="flex items-center gap-3">
                            <div className="bg-navy flex h-10 w-10 items-center justify-center rounded-xl text-gold">
                                <Building2 className="h-5 w-5" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="text-base font-black text-slate-900 dark:text-white">
                                        {subpoint.name}
                                    </h2>
                                    {getStatusBadge(subpoint.status)}
                                </div>
                                <p className="font-mono text-xs text-slate-500">
                                    Code bureau : {subpoint.code} ·{' '}
                                    {subpoint.city}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Body */}
                    <div className="flex-1 space-y-6 overflow-y-auto p-6">
                        {/* Notice Consultation Seule */}
                        <div className="flex items-start gap-2.5 rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-xs text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
                            <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                            <div>
                                <p className="font-bold">
                                    Consultation restreinte au rôle Supervision
                                </p>
                                <p className="mt-0.5 text-[11px] text-blue-800 dark:text-blue-300">
                                    Les ventes au guichet sont assurées par
                                    l'agent local. Le paramétrage contractuel et
                                    le plafond sont définis par KORA. Les
                                    règlements groupés sont assurés par le
                                    Trésorier.
                                </p>
                            </div>
                        </div>

                        {/* Credit Health & Exposure Gauge */}
                        <div className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-800/30">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                                    Encours & Plafond de crédit
                                </span>
                                <span className="text-xs font-black text-slate-900 dark:text-white">
                                    {subpoint.usage_percentage} % utilisé
                                </span>
                            </div>

                            {/* Progress bar */}
                            <div className="h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    className={cn(
                                        'h-full rounded-full transition-all',
                                        subpoint.usage_percentage >= 100
                                            ? 'bg-rose-600'
                                            : subpoint.usage_percentage >= 80
                                              ? 'bg-amber-500'
                                              : 'bg-emerald-500',
                                    )}
                                    style={{
                                        width: `${Math.min(100, subpoint.usage_percentage)}%`,
                                    }}
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4 pt-1 text-xs">
                                <div>
                                    <span className="block text-[11px] text-slate-400">
                                        Dette courante à reverser
                                    </span>
                                    <span className="text-base font-black text-slate-900 dark:text-white">
                                        {subpoint.current_exposure.toLocaleString()}{' '}
                                        FCFA
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-[11px] text-slate-400">
                                        Plafond alloué KORA
                                    </span>
                                    <span className="text-base font-black text-slate-900 dark:text-white">
                                        {subpoint.credit_limit.toLocaleString()}{' '}
                                        FCFA
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Performance & Commercial Stats */}
                        <div className="space-y-3">
                            <h3 className="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Performances commerciales
                            </h3>
                            <div className="grid grid-cols-2 gap-3 text-xs">
                                <div className="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                    <span className="block text-[11px] text-slate-400">
                                        Ventes du mois (Assiette)
                                    </span>
                                    <span className="mt-1 block text-base font-black text-slate-900 dark:text-white">
                                        {subpoint.monthly_sales_amount.toLocaleString()}{' '}
                                        FCFA
                                    </span>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                    <span className="block text-[11px] text-slate-400">
                                        Commissions générées (3 %)
                                    </span>
                                    <span className="mt-1 block text-base font-black text-emerald-600 dark:text-emerald-400">
                                        {subpoint.monthly_commission_earned.toLocaleString()}{' '}
                                        FCFA
                                    </span>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                    <span className="block text-[11px] text-slate-400">
                                        Activité du jour
                                    </span>
                                    <span className="mt-1 block text-base font-black text-slate-900 dark:text-white">
                                        {subpoint.daily_sales_amount.toLocaleString()}{' '}
                                        FCFA
                                    </span>
                                    <span className="text-[11px] text-slate-500">
                                        {subpoint.daily_tickets_count} billets
                                        émis aujourd'hui
                                    </span>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                    <span className="block text-[11px] text-slate-400">
                                        Dernier versement
                                    </span>
                                    <span className="mt-1 block text-sm font-black text-slate-900 dark:text-white">
                                        {subpoint.last_settlement_date}
                                    </span>
                                    <span className="text-[11px] text-emerald-600 dark:text-emerald-400">
                                        Rapproché KORA
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Local Agency Details */}
                        <div className="space-y-3">
                            <h3 className="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Responsable local & Coordonnées
                            </h3>
                            <div className="divide-y divide-slate-100 rounded-xl border border-slate-200 text-xs dark:divide-slate-800 dark:border-slate-800">
                                <div className="flex justify-between p-3">
                                    <span className="flex items-center gap-1.5 text-slate-500">
                                        <User className="h-3.5 w-3.5" /> Chef de
                                        bureau
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-white">
                                        {subpoint.manager_name}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="flex items-center gap-1.5 text-slate-500">
                                        <Phone className="h-3.5 w-3.5" />{' '}
                                        Téléphone
                                    </span>
                                    <span className="font-mono font-semibold text-slate-900 dark:text-white">
                                        {subpoint.phone}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="flex items-center gap-1.5 text-slate-500">
                                        <MapPin className="h-3.5 w-3.5" />{' '}
                                        Localisation
                                    </span>
                                    <span className="font-semibold text-slate-900 dark:text-white">
                                        {subpoint.city}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex shrink-0 justify-end border-t border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

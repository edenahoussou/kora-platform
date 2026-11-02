import { Building2, Store, Users, FileText, Coins, Edit3 } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import type {
    AdminOrganization,
    OrganizationSubPoint,
} from '../mock/kora-point-mock-data';

type Props = {
    organizations: AdminOrganization[];
    subpoints: OrganizationSubPoint[];
    onToggleUserStatus: (userUuid: string) => void;
    onUpdateTermsOverride?: (pointUuid: string, terms: any) => void;
};

export function KoraAdminOrganizationsView({
    organizations,
    subpoints,
    onToggleUserStatus,
    onUpdateTermsOverride,
}: Props) {
    const [selectedOrgUuid, setSelectedOrgUuid] = useState<string>(
        organizations[0]?.uuid || '',
    );
    const [activeTab, setActiveTab] = useState<
        'points' | 'terms' | 'documents' | 'users'
    >('points');

    const activeOrg =
        organizations.find((o) => o.uuid === selectedOrgUuid) ||
        organizations[0];
    const orgSubpoints = subpoints.filter(
        (sp) =>
            !sp.organization_uuid ||
            !activeOrg?.uuid ||
            sp.organization_uuid === activeOrg.uuid,
    );

    // Simulated overrides for demonstration of contract inheritance
    const [overrides, setOverrides] = useState<{
        [subpointUuid: string]: {
            commission_rate_bps?: number;
            credit_limit?: number;
        };
    }>({
        'sub-01': { credit_limit: 1500000 },
    });

    const handleToggleOverride = (subpointUuid: string) => {
        setOverrides((prev) => {
            if (prev[subpointUuid]) {
                const copy = { ...prev };
                delete copy[subpointUuid];
                onUpdateTermsOverride?.(subpointUuid, {
                    commission_basis_points: null,
                });

                return copy;
            } else {
                onUpdateTermsOverride?.(subpointUuid, {
                    commission_basis_points: 350,
                });

                return {
                    ...prev,
                    [subpointUuid]: { commission_rate_bps: 350 },
                };
            }
        });
    };

    return (
        <div className="space-y-6">
            {/* Header with Organization Switcher */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-black text-slate-900 dark:text-white">
                        <Building2 className="text-gold-dark h-6 w-6 dark:text-gold" />
                        Organisations Partenaires & Conditions Cadres
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Gestion contractuelle, matrice d'héritage, pièces
                        légales et habilitations
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-slate-500">
                        Organisation :
                    </span>
                    <select
                        value={selectedOrgUuid}
                        onChange={(e) => setSelectedOrgUuid(e.target.value)}
                        className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        {organizations.map((org) => (
                            <option key={org.uuid} value={org.uuid}>
                                {org.name} ({org.points_count} bureaux)
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Organization Overview Banner */}
            {activeOrg && (
                <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="text-lg font-black text-slate-900 dark:text-white">
                                    {activeOrg.name}
                                </h2>
                                <span className="bg-navy/10 text-navy rounded-md px-2 py-0.5 font-mono text-[11px] font-bold dark:bg-gold/15 dark:text-gold">
                                    {activeOrg.code}
                                </span>
                            </div>
                            <p className="mt-0.5 text-xs text-slate-500">
                                {activeOrg.legal_name} · RCCM : {activeOrg.rccm}{' '}
                                · IFU : {activeOrg.ifu}
                            </p>
                        </div>

                        <div className="flex gap-4 text-xs">
                            <div>
                                <span className="block text-[11px] text-slate-400">
                                    Bureaux déployés
                                </span>
                                <span className="text-base font-black text-slate-900 dark:text-white">
                                    {activeOrg.active_points_count} /{' '}
                                    {activeOrg.points_count} actifs
                                </span>
                            </div>
                            <div>
                                <span className="block text-[11px] text-slate-400">
                                    Encours consolidé
                                </span>
                                <span className="text-base font-black text-amber-600 dark:text-amber-400">
                                    {activeOrg.total_exposure.toLocaleString()}{' '}
                                    FCFA
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Navigation Tabs */}
                    <div className="flex border-b border-slate-200 text-xs font-bold dark:border-slate-800">
                        <button
                            type="button"
                            onClick={() => setActiveTab('points')}
                            className={cn(
                                'flex items-center gap-2 border-b-2 px-4 pb-3',
                                activeTab === 'points'
                                    ? 'border-gold text-slate-900 dark:text-white'
                                    : 'border-transparent text-slate-400 hover:text-slate-600',
                            )}
                        >
                            <Store className="h-4 w-4" />
                            Bureaux rattachés ({activeOrg.points_count})
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('terms')}
                            className={cn(
                                'flex items-center gap-2 border-b-2 px-4 pb-3',
                                activeTab === 'terms'
                                    ? 'border-gold text-slate-900 dark:text-white'
                                    : 'border-transparent text-slate-400 hover:text-slate-600',
                            )}
                        >
                            <Coins className="h-4 w-4" />
                            Conditions & Matrice d'héritage
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('documents')}
                            className={cn(
                                'flex items-center gap-2 border-b-2 px-4 pb-3',
                                activeTab === 'documents'
                                    ? 'border-gold text-slate-900 dark:text-white'
                                    : 'border-transparent text-slate-400 hover:text-slate-600',
                            )}
                        >
                            <FileText className="h-4 w-4" />
                            Conventions & Documents (
                            {activeOrg.documents.length})
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('users')}
                            className={cn(
                                'flex items-center gap-2 border-b-2 px-4 pb-3',
                                activeTab === 'users'
                                    ? 'border-gold text-slate-900 dark:text-white'
                                    : 'border-transparent text-slate-400 hover:text-slate-600',
                            )}
                        >
                            <Users className="h-4 w-4" />
                            Utilisateurs habilités ({activeOrg.users.length})
                        </button>
                    </div>

                    {/* Tab 1: Points rattachés */}
                    {activeTab === 'points' && (
                        <div className="space-y-3 pt-2">
                            <div className="overflow-hidden rounded-xl border border-slate-200 text-xs dark:border-slate-800">
                                <table className="w-full text-left">
                                    <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-800">
                                        <tr>
                                            <th className="px-4 py-3 font-bold uppercase">
                                                Bureau
                                            </th>
                                            <th className="px-4 py-3 font-bold uppercase">
                                                Chef de bureau
                                            </th>
                                            <th className="px-4 py-3 text-right font-bold uppercase">
                                                Encours courant
                                            </th>
                                            <th className="px-4 py-3 text-right font-bold uppercase">
                                                Plafond alloué
                                            </th>
                                            <th className="px-4 py-3 text-center font-bold uppercase">
                                                Statut
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {orgSubpoints.map((sp) => (
                                            <tr
                                                key={sp.uuid}
                                                className="hover:bg-slate-50/50"
                                            >
                                                <td className="px-4 py-3">
                                                    <span className="block font-bold text-slate-900 dark:text-white">
                                                        {sp.name}
                                                    </span>
                                                    <span className="text-[11px] text-slate-400">
                                                        {sp.code} · {sp.city}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 dark:text-slate-300">
                                                    {sp.manager_name} (
                                                    {sp.phone})
                                                </td>
                                                <td className="px-4 py-3 text-right font-black text-slate-900 dark:text-white">
                                                    {sp.current_exposure.toLocaleString()}{' '}
                                                    FCFA
                                                </td>
                                                <td className="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-400">
                                                    {sp.credit_limit.toLocaleString()}{' '}
                                                    FCFA
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="inline-block rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                        {sp.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Tab 2: Matrice d'héritage contractuel */}
                    {activeTab === 'terms' && (
                        <div className="space-y-4 pt-2 text-xs">
                            {/* Accord Cadre Organisation */}
                            <div className="space-y-2 rounded-xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900/60 dark:bg-blue-950/30">
                                <h3 className="flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-blue-900 uppercase dark:text-blue-200">
                                    <Building2 className="h-4 w-4 text-blue-600" />
                                    Accord-Cadre Organisation ({activeOrg.name})
                                </h3>
                                <div className="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                    <div>
                                        <span className="block text-[11px] text-slate-500">
                                            Commission standard
                                        </span>
                                        <span className="font-black text-slate-900 dark:text-white">
                                            {(
                                                activeOrg.default_terms
                                                    .commission_rate_bps / 100
                                            ).toFixed(2)}{' '}
                                            %
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-[11px] text-slate-500">
                                            Fréquence reversement
                                        </span>
                                        <span className="font-bold text-slate-900 dark:text-white">
                                            {
                                                activeOrg.default_terms
                                                    .settlement_frequency_days
                                            }{' '}
                                            jours (Mensuel)
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-[11px] text-slate-500">
                                            Délai de grâce
                                        </span>
                                        <span className="font-bold text-slate-900 dark:text-white">
                                            {
                                                activeOrg.default_terms
                                                    .grace_period_days
                                            }{' '}
                                            jours ouvrés
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-[11px] text-slate-500">
                                            Plafond global réseau
                                        </span>
                                        <span className="font-black text-slate-900 dark:text-white">
                                            {activeOrg.default_terms.credit_limit.toLocaleString()}{' '}
                                            FCFA
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Subpoints Inheritance Table */}
                            <div className="space-y-2">
                                <h3 className="font-bold text-slate-900 dark:text-white">
                                    Déclinaison par bureau & Détection des
                                    surcharges
                                </h3>
                                <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                                    <table className="w-full text-left">
                                        <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-800">
                                            <tr>
                                                <th className="px-4 py-2.5 font-bold uppercase">
                                                    Bureau
                                                </th>
                                                <th className="px-4 py-2.5 font-bold uppercase">
                                                    Taux Commission
                                                </th>
                                                <th className="px-4 py-2.5 font-bold uppercase">
                                                    Origine de la règle
                                                </th>
                                                <th className="px-4 py-2.5 text-right font-bold uppercase">
                                                    Plafond alloué
                                                </th>
                                                <th className="px-4 py-2.5 text-right font-bold uppercase">
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                            {orgSubpoints.map((sp) => {
                                                const hasCustom = Boolean(
                                                    overrides[sp.uuid],
                                                );
                                                const rate =
                                                    overrides[sp.uuid]
                                                        ?.commission_rate_bps ||
                                                    300;
                                                const limit =
                                                    overrides[sp.uuid]
                                                        ?.credit_limit ||
                                                    sp.credit_limit;

                                                return (
                                                    <tr key={sp.uuid}>
                                                        <td className="px-4 py-3 font-bold text-slate-900 dark:text-white">
                                                            {sp.name}
                                                        </td>
                                                        <td className="text-gold-dark px-4 py-3 font-mono font-bold dark:text-gold">
                                                            {(
                                                                rate / 100
                                                            ).toFixed(2)}{' '}
                                                            %
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {hasCustom ? (
                                                                <span className="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                                    <Edit3 className="h-3 w-3" />
                                                                    Surcharge
                                                                    personnalisée
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                                                    <Building2 className="h-3 w-3" />
                                                                    Hérité :
                                                                    Accord La
                                                                    Poste
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right font-black text-slate-900 dark:text-white">
                                                            {limit.toLocaleString()}{' '}
                                                            FCFA
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    handleToggleOverride(
                                                                        sp.uuid,
                                                                    )
                                                                }
                                                                className="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                                            >
                                                                {hasCustom
                                                                    ? 'Rétablir héritage'
                                                                    : 'Surcharger'}
                                                            </button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Tab 3: Documents légaux */}
                    {activeTab === 'documents' && (
                        <div className="space-y-3 pt-2 text-xs">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {activeOrg.documents.map((doc) => (
                                    <div
                                        key={doc.uuid}
                                        className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/40"
                                    >
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-6 w-6 text-slate-500" />
                                            <div>
                                                <p className="font-bold text-slate-900 dark:text-white">
                                                    {doc.name}
                                                </p>
                                                <span className="text-[11px] text-slate-500">
                                                    {doc.size} · Téléversé le{' '}
                                                    {doc.uploaded_at}
                                                </span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                alert(
                                                    `Téléchargement de ${doc.name} (simulation démo)`,
                                                )
                                            }
                                            className="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        >
                                            Télécharger
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Tab 4: Utilisateurs affectés */}
                    {activeTab === 'users' && (
                        <div className="space-y-3 pt-2 text-xs">
                            <p className="text-slate-500">
                                Pour renvoyer ou émettre un accès web, utilisez
                                la liste Utilisateurs.
                            </p>
                            <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                                <table className="w-full text-left">
                                    <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-800">
                                        <tr>
                                            <th className="px-4 py-2.5 font-bold uppercase">
                                                Utilisateur
                                            </th>
                                            <th className="px-4 py-2.5 font-bold uppercase">
                                                Rôle
                                            </th>
                                            <th className="px-4 py-2.5 font-bold uppercase">
                                                Contact
                                            </th>
                                            <th className="px-4 py-2.5 text-center font-bold uppercase">
                                                Statut
                                            </th>
                                            <th className="px-4 py-2.5 text-right font-bold uppercase">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {activeOrg.users.map((u) => (
                                            <tr key={u.uuid}>
                                                <td className="px-4 py-3 font-bold text-slate-900 dark:text-white">
                                                    {u.name}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 dark:text-slate-300">
                                                    {u.role_label}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="block font-mono text-slate-900 dark:text-white">
                                                        {u.email}
                                                    </span>
                                                    <span className="font-mono text-[11px] text-slate-500">
                                                        {u.phone}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={cn(
                                                            'rounded-md px-2 py-0.5 text-[10px] font-bold',
                                                            u.status ===
                                                                'ACTIVE'
                                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                                : 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
                                                        )}
                                                    >
                                                        {u.status === 'ACTIVE'
                                                            ? 'Actif'
                                                            : 'Suspendu'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                onToggleUserStatus(
                                                                    u.uuid,
                                                                )
                                                            }
                                                            className="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                                        >
                                                            {u.status ===
                                                            'ACTIVE'
                                                                ? 'Désactiver'
                                                                : 'Activer'}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

import {
    Calendar,
    Clock,
    Download,
    FileCheck,
    FileText,
    Percent,
    Shield,
} from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    FinancialHealth,
    KoraPointProfile,
    PointAssignedUser,
    PointConvention,
    PointDocument,
} from '../mock/kora-point-mock-data';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    convention: PointConvention;
    documents: PointDocument[];
    assignedUsers: PointAssignedUser[];
    onNavigateTab?: (tab: string) => void;
};

export function KoraPointProfileView({
    point,
    health,
    convention,
    documents,
    assignedUsers,
}: Props) {
    const [activeTab, setActiveTab] = useState<
        'overview' | 'terms' | 'documents' | 'users'
    >('overview');
    const [downloadMsg, setDownloadMsg] = useState<string | null>(null);

    const isAutonomous = !point.organization;
    const ratePercent = (point.terms.commission_rate_bps / 100).toLocaleString(
        'fr-FR',
        {
            minimumFractionDigits: 1,
            maximumFractionDigits: 2,
        },
    );

    const triggerDownload = (fileName: string) => {
        setDownloadMsg(`Téléchargement simulé de : ${fileName}`);
        setTimeout(() => setDownloadMsg(null), 3000);
    };

    return (
        <div className="space-y-6">
            {/* Page Header */}
            <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA POINT / Espace Agent / Mon Point
                    </div>
                    <div className="flex items-center gap-2.5">
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            {point.name}
                        </h1>
                        <KoraStatusBadge
                            label={
                                point.organization
                                    ? `Réseau : ${point.organization.name}`
                                    : 'Point autonome'
                            }
                            tone={point.organization ? 'blue' : 'emerald'}
                            variant="solid"
                        />
                        <KoraStatusBadge
                            label={
                                health.sales_allowed
                                    ? 'Actif'
                                    : 'Bloqué / Suspendu'
                            }
                            tone={
                                health.sales_allowed ? 'emerald' : 'destructive'
                            }
                            variant="solid"
                        />
                    </div>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        Code guichet : {point.code} · {point.address},{' '}
                        {point.city} · Gérant : {point.manager_name} (
                        {point.manager_phone})
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onClick={() =>
                            triggerDownload(`Fiche_Synthese_${point.code}.pdf`)
                        }
                        className="btn btn-primary"
                    >
                        <Download className="size-4" />
                        <span>Télécharger la fiche</span>
                    </button>
                </div>
            </div>

            {downloadMsg && (
                <div className="animate-in rounded-xl bg-blue-500/10 p-2.5 text-center text-xs font-semibold text-digital fade-in">
                    {downloadMsg}
                </div>
            )}

            {/* Navigation Tabs */}
            <div className="border-b border-slate-200 dark:border-slate-800">
                <nav
                    className="flex space-x-6 text-xs font-extrabold tracking-wider uppercase"
                    aria-label="Onglets Mon Point"
                >
                    {[
                        { id: 'overview', label: 'Activité & Synthèse' },
                        { id: 'terms', label: 'Conditions contractuelles' },
                        { id: 'documents', label: 'Documents & Convention' },
                        { id: 'users', label: 'Utilisateurs affectés' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setActiveTab(tab.id as any)}
                            className={cn(
                                'border-b-2 pb-3.5 font-bold transition',
                                activeTab === tab.id
                                    ? 'border-gold text-kora dark:border-gold dark:text-gold'
                                    : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* TAB 1: OVERVIEW */}
            {activeTab === 'overview' && (
                <div className="animate-in space-y-6 fade-in">
                    {/* Financial Summary */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <span className="text-[0.625rem] font-bold text-slate-400 uppercase">
                                Encours actuel à reverser
                            </span>
                            <strong className="mt-1 block font-mono text-2xl font-black text-slate-900 dark:text-white">
                                {health.current_exposure.toLocaleString(
                                    'fr-FR',
                                )}{' '}
                                FCFA
                            </strong>
                            <span className="mt-1 block text-xs text-slate-500">
                                {health.usage_percentage} % du plafond consommé
                            </span>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <span className="text-[0.625rem] font-bold text-slate-400 uppercase">
                                Plafond de crédit accordé
                            </span>
                            <strong className="mt-1 block font-mono text-2xl font-black text-slate-900 dark:text-white">
                                {health.credit_limit.toLocaleString('fr-FR')}{' '}
                                FCFA
                            </strong>
                            <span className="mt-1 block text-xs text-slate-500">
                                Égalité admise, dépassement bloqué
                            </span>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <span className="text-[0.625rem] font-bold text-slate-400 uppercase">
                                Prochaine échéance
                            </span>
                            <strong className="mt-1 block text-lg font-black text-slate-900 dark:text-white">
                                {health.next_due_label}
                            </strong>
                            <span className="mt-1 block text-xs text-slate-500">
                                Délai de grâce contractuel :{' '}
                                {point.terms.grace_period_days} jours
                            </span>
                        </div>
                    </div>

                    {/* Operational info */}
                    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 className="text-sm font-extrabold text-slate-900 dark:text-white">
                            Règles d’exploitation et périmètre Bus
                        </h3>
                        <p className="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                            Ce KORA POINT est habilité à la distribution des
                            billets Bus sur l’inventaire centralisé du réseau.
                            Il ne définit pas librement les prix et ne détient
                            pas de stock indépendant.
                        </p>
                    </div>
                </div>
            )}

            {/* TAB 2: TERMS & CONDITIONS */}
            {activeTab === 'terms' && (
                <div className="animate-in space-y-6 fade-in">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="border-b border-slate-100 pb-3 dark:border-slate-800">
                            <h3 className="text-sm font-extrabold text-slate-900 dark:text-white">
                                Paramètres contractuels effectifs
                            </h3>
                            <p className="text-xs text-slate-500">
                                Hiérarchie de résolution :{' '}
                                <strong>
                                    Point → Organisation éventuelle →
                                    Exploitation KORA
                                </strong>
                            </p>
                        </div>

                        <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {/* Commission Rate */}
                            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Percent className="size-4 text-slate-400" />
                                        <span>Taux de commission</span>
                                    </span>
                                    <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.625rem] font-black text-emerald-700 dark:text-emerald-400">
                                        {isAutonomous
                                            ? 'Propre au Point'
                                            : 'Hérité Organisation'}
                                    </span>
                                </div>
                                <strong className="mt-2 block font-mono text-2xl font-black text-slate-900 dark:text-white">
                                    {ratePercent} %
                                </strong>
                                <span className="text-[0.6875rem] text-slate-400">
                                    Enregistré à{' '}
                                    {point.terms.commission_rate_bps} points de
                                    base. Retenu à la source.
                                </span>
                            </div>

                            {/* Credit Limit */}
                            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Shield className="size-4 text-slate-400" />
                                        <span>Plafond d'encours</span>
                                    </span>
                                    <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.625rem] font-black text-emerald-700 dark:text-emerald-400">
                                        {isAutonomous
                                            ? 'Propre au Point'
                                            : 'Hérité Organisation'}
                                    </span>
                                </div>
                                <strong className="mt-2 block font-mono text-2xl font-black text-slate-900 dark:text-white">
                                    {point.terms.credit_limit_amount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </strong>
                                <span className="text-[0.6875rem] text-slate-400">
                                    Blocage automatique de la billetterie en cas
                                    de dépassement.
                                </span>
                            </div>

                            {/* Schedule */}
                            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Calendar className="size-4 text-slate-400" />
                                        <span>Fréquence des reversements</span>
                                    </span>
                                    <span className="rounded-full bg-blue-500/15 px-2 py-0.5 text-[0.625rem] font-black text-blue-700 dark:text-blue-400">
                                        Contractuel
                                    </span>
                                </div>
                                <strong className="mt-2 block text-base font-black text-slate-900 dark:text-white">
                                    {point.terms.settlement_schedule
                                        .frequency === 'weekly'
                                        ? 'Hebdomadaire (Chaque lundi à 18:00)'
                                        : 'Mensuel (Le 28 du mois à 17:00)'}
                                </strong>
                                <span className="text-[0.6875rem] text-slate-400">
                                    Fuseau horaire :{' '}
                                    {point.terms.settlement_schedule.timezone}
                                </span>
                            </div>

                            {/* Grace Period */}
                            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Clock className="size-4 text-slate-400" />
                                        <span>Délai de grâce</span>
                                    </span>
                                    <span className="rounded-full bg-slate-200 px-2 py-0.5 text-[0.625rem] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        Exploitation
                                    </span>
                                </div>
                                <strong className="mt-2 block text-base font-black text-slate-900 dark:text-white">
                                    {point.terms.grace_period_days} jours ouvrés
                                </strong>
                                <span className="text-[0.6875rem] text-slate-400">
                                    Suspension automatique si aucun bordereau
                                    validé après expiration du délai.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* TAB 3: DOCUMENTS & CONVENTION */}
            {activeTab === 'documents' && (
                <div className="animate-in space-y-6 fade-in">
                    {/* Convention Hero Card */}
                    <div className="rounded-3xl border-2 border-gold/40 bg-gradient-to-br from-gold/10 via-white to-gold/5 p-6 shadow-sm dark:border-gold/30 dark:from-slate-900 dark:via-slate-900 dark:to-slate-950">
                        <div className="flex flex-col gap-4 border-b border-gold/20 pb-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="grid size-12 place-items-center rounded-2xl bg-gold font-black text-kora">
                                    <FileCheck className="size-6" />
                                </div>
                                <div>
                                    <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.625rem] font-black text-emerald-700 uppercase dark:text-emerald-400">
                                        Convention en vigueur
                                    </span>
                                    <h3 className="text-base font-black text-slate-900 dark:text-white">
                                        {convention.title}
                                    </h3>
                                    <span className="font-mono text-xs font-bold text-slate-400">
                                        Réf : {convention.ref}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={() =>
                                    triggerDownload(convention.file_name)
                                }
                                className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-gold px-4 py-2 text-xs font-black tracking-wider text-kora uppercase shadow-md hover:bg-gold/90"
                            >
                                <Download className="size-4" />
                                <span>Télécharger l'accord</span>
                            </button>
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                            <div>
                                <span className="text-slate-400">
                                    Signée le :
                                </span>
                                <div className="font-bold text-slate-800 dark:text-slate-200">
                                    {convention.signed_at}
                                </div>
                            </div>
                            <div>
                                <span className="text-slate-400">
                                    Expire le :
                                </span>
                                <div className="font-bold text-slate-800 dark:text-slate-200">
                                    {convention.expires_at}
                                </div>
                            </div>
                            <div>
                                <span className="text-slate-400">
                                    Taux contractuel :
                                </span>
                                <div className="font-bold text-slate-800 dark:text-slate-200">
                                    {ratePercent} %
                                </div>
                            </div>
                            <div>
                                <span className="text-slate-400">
                                    Fichier numérisé :
                                </span>
                                <div className="font-mono text-slate-600 dark:text-slate-300">
                                    {convention.file_size}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Supporting Legal Documents */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                            <h3 className="text-sm font-extrabold text-slate-900 dark:text-white">
                                Pièces justificatives et dossier légal
                            </h3>
                            <span className="text-xs font-semibold text-slate-400">
                                {documents.length} fichiers vérifiés
                            </span>
                        </div>

                        <div className="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                            {documents.map((doc) => (
                                <div
                                    key={doc.uuid}
                                    className="flex items-center justify-between py-3 text-xs"
                                >
                                    <div className="flex items-center gap-3">
                                        <FileText className="size-5 shrink-0 text-slate-400" />
                                        <div>
                                            <div className="font-bold text-slate-800 dark:text-slate-200">
                                                {doc.title}
                                            </div>
                                            <div className="font-mono text-[0.6875rem] text-slate-400">
                                                {doc.file_name} ·{' '}
                                                {doc.file_size} · Ajouté le{' '}
                                                {doc.uploaded_at}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.625rem] font-bold text-emerald-700 dark:text-emerald-400">
                                            Vérifié
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                triggerDownload(doc.file_name)
                                            }
                                            className="rounded-lg p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white"
                                            title="Télécharger la pièce"
                                        >
                                            <Download className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* TAB 4: USERS */}
            {activeTab === 'users' && (
                <div className="animate-in space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm fade-in dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                        <h3 className="text-sm font-extrabold text-slate-900 dark:text-white">
                            Comptes et agents habilités au Point
                        </h3>
                        <span className="text-xs text-slate-500">
                            {assignedUsers.length} utilisateurs actifs
                        </span>
                    </div>

                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                        {assignedUsers.map((user) => (
                            <div
                                key={user.uuid}
                                className="flex flex-col gap-2 py-3.5 text-xs sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="grid size-9 place-items-center rounded-xl bg-slate-100 font-black text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {user.name.slice(0, 2).toUpperCase()}
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <strong className="text-slate-900 dark:text-white">
                                                {user.name}
                                            </strong>
                                            {user.is_primary && (
                                                <span className="py-0.2 rounded bg-gold/25 px-1.5 text-[0.5625rem] font-black text-kora uppercase dark:text-gold">
                                                    Gérant principal
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-[0.6875rem] text-slate-400">
                                            {user.email} · {user.phone}
                                        </div>
                                    </div>
                                </div>

                                <div className="text-right">
                                    <span className="font-semibold text-slate-700 dark:text-slate-300">
                                        {user.role_label}
                                    </span>
                                    <div className="text-[0.625rem] text-slate-400">
                                        Affecté le {user.assigned_at}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

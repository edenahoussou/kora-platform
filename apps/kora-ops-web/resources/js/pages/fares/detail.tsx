import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, Clock, MapPin, Pencil } from 'lucide-react';
import { useState } from 'react';
import { FareFormDialog } from '@/components/fares/fare-form-dialog';
import type { FareRecord } from '@/components/fares/fare-form-dialog';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import fares from '@/routes/fares';
import type { BreadcrumbItem } from '@/types';

type FareDetail = FareRecord & {
    reference: string;
    lineUuid: string;
    originCity: string | null;
    originCountry: string | null;
    destinationCity: string | null;
    destinationCountry: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

type FareVersion = {
    uuid: string;
    reference: string;
    baseAmount: number;
    currency: string;
    effectiveFrom: string | null;
    effectiveUntil: string | null;
    status: string;
    isCurrent: boolean;
};

type TimelineEvent = {
    label: string;
    date: string | null;
};

type StopOption = {
    uuid: string;
    name: string;
    stopOrder: number;
};

type LineOption = {
    uuid: string;
    code: string;
    name: string;
    stops: StopOption[];
};

type PageProps = {
    fare: FareDetail;
    versions: FareVersion[];
    timeline: TimelineEvent[];
    lines: LineOption[];
    permissions: {
        canUpdate: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Actif',
    draft: 'Brouillon',
    archived: 'Archivé',
};
const statusTones: Record<string, 'navy' | 'gold' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    archived: 'muted',
};

type Tab = 'segments' | 'periods' | 'history';

const BUSINESS_RULE =
    "Chaque combinaison origine–destination possède son prix commercial. Les billets déjà émis conservent leur prix d'origine, même après modification de la grille.";

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);

    return d.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateShort(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatAmount(amount: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(amount)} ${currency}`;
}

export default function FareDetailPage({
    fare,
    versions,
    timeline,
    lines,
    permissions,
}: PageProps) {
    const [tab, setTab] = useState<Tab>('segments');
    const [editDialog, setEditDialog] = useState(false);

    const tabs: { key: Tab; label: string }[] = [
        { key: 'segments', label: 'Segments' },
        { key: 'periods', label: 'Périodes' },
        { key: 'history', label: 'Historique' },
    ];

    const tabLabel = tabs.find((t) => t.key === tab)?.label ?? '';

    const infoItems: { label: string; value: string }[] = [
        { label: 'Référence', value: fare.reference },
        { label: 'Ligne', value: `${fare.lineCode} · ${fare.lineName}` },
        { label: 'Origine', value: fare.originName },
        { label: 'Destination', value: fare.destinationName },
        {
            label: 'Prix de base',
            value: formatAmount(fare.baseAmount, fare.currency),
        },
        {
            label: 'Effet au',
            value: formatDateShort(fare.effectiveFrom),
        },
        {
            label: "Jusqu'au",
            value: formatDateShort(fare.effectiveUntil),
        },
        {
            label: 'Statut',
            value: statusLabels[fare.status] ?? fare.status,
        },
    ];

    return (
        <>
            <Head title={`${fare.reference} — Grille tarifaire`} />

            {/* 1. PAGE-HEAD */}
            <div className="mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] font-bold tracking-[0.035em] text-muted-foreground">
                        KORA OPS / Tarifs / {fare.reference}
                    </div>
                    <h1 className="text-[2rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Grille tarifaire
                    </h1>
                    <p className="mt-1.5 text-sm text-muted-foreground">
                        {fare.reference} · {fare.lineCode} · {fare.originName} →{' '}
                        {fare.destinationName}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2.5">
                    <button
                        type="button"
                        onClick={() => router.get(fares.index.url())}
                        className="btn btn-ghost"
                    >
                        <ArrowLeft className="size-4" />
                        Retour
                    </button>
                    {permissions.canUpdate && (
                        <button
                            type="button"
                            onClick={() => setEditDialog(true)}
                            className="btn btn-primary"
                        >
                            <Pencil className="size-3.5" />
                            Modifier
                        </button>
                    )}
                </div>
            </div>

            {/* 2. TABS */}
            <div className="mb-5 flex gap-1 border-b border-border/60">
                {tabs.map(({ key, label }) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        className={`border-b-2 px-4 py-3 text-[0.6875rem] font-extrabold tracking-[0.035em] transition-colors ${tab === key
                            ? 'border-gold text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {/* 3. DETAIL-IDENTITY */}
            <section className="relative mb-6 flex items-center justify-between gap-6 overflow-hidden rounded-[1.25rem] bg-gradient-to-br from-[var(--kora)] to-[var(--digital)] p-7 text-white shadow-[0_18px_42px_rgba(0,29,78,0.16)]">
                <div className="relative z-10 max-w-[760px]">
                    <span className="mb-1.5 block text-[0.5625rem] font-black tracking-[0.13em] text-gold uppercase">
                        Grille tarifaire
                    </span>
                    <h2 className="text-[1.6875rem] font-extrabold tracking-[-0.03em]">
                        {fare.reference}
                    </h2>
                    <p className="mt-2 max-w-[560px] text-[0.6875rem] leading-relaxed text-white/72">
                        {fare.originName} → {fare.destinationName} ·{' '}
                        {formatAmount(fare.baseAmount, fare.currency)}
                    </p>
                </div>
                <span className="relative z-10 shrink-0">
                    <KoraStatusBadge
                        label={statusLabels[fare.status] ?? fare.status}
                        tone={statusTones[fare.status] ?? 'gold'}
                        className="border border-white/20 bg-white/5 shadow-[0_8px_24px_rgba(0,29,78,0.18)]"
                    />
                </span>
            </section>

            {/* 4. DETAIL-GRID */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.45fr_0.55fr]">
                {/* Panel principal */}
                <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-[0.9375rem] font-bold text-foreground">
                                {tabLabel}
                            </h2>
                            <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                Informations propres à ce dossier
                            </p>
                        </div>
                    </div>

                    {/* Tab: Segments (info-grid) */}
                    {tab === 'segments' && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {infoItems.map((item) => (
                                    <div
                                        key={item.label}
                                        className="rounded-[0.75rem] bg-muted p-3.5"
                                    >
                                        <small className="block text-[0.5625rem] text-muted-foreground">
                                            {item.label}
                                        </small>
                                        <strong className="mt-1.5 block text-[0.75rem] text-foreground">
                                            {item.value}
                                        </strong>
                                    </div>
                                ))}
                            </div>

                            {/* Segment O/D détaillé */}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-[0.875rem] border border-border/50 bg-card p-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span className="grid size-7 place-items-center rounded-full bg-gold text-[var(--kora)]">
                                            <MapPin className="size-3.5" />
                                        </span>
                                        <span className="text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                            Origine
                                        </span>
                                    </div>
                                    <strong className="block text-sm text-foreground">
                                        {fare.originName}
                                    </strong>
                                    <span className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                        {fare.originCity}
                                        {fare.originCountry
                                            ? `, ${fare.originCountry}`
                                            : ''}
                                    </span>
                                </div>
                                <div className="rounded-[0.875rem] border border-border/50 bg-card p-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span className="grid size-7 place-items-center rounded-full bg-[var(--digital)] text-white">
                                            <MapPin className="size-3.5" />
                                        </span>
                                        <span className="text-[0.5625rem] font-black tracking-[0.13em] text-gold uppercase">
                                            Destination
                                        </span>
                                    </div>
                                    <strong className="block text-sm text-foreground">
                                        {fare.destinationName}
                                    </strong>
                                    <span className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                        {fare.destinationCity}
                                        {fare.destinationCountry
                                            ? `, ${fare.destinationCountry}`
                                            : ''}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Tab: Périodes (versions table) */}
                    {tab === 'periods' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Historique tarifaire du segment
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        {versions.length} grille
                                        {versions.length > 1 ? 's' : ''}
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Toutes les grilles publiées sur le même
                                        segment, ordonnées par date d'effet.
                                    </p>
                                </div>
                            </div>

                            {versions.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                                <th className="pr-4 pb-3 font-medium">
                                                    Référence
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Effet au
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Jusqu'au
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Prix
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Statut
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {versions.map((version) => (
                                                <tr
                                                    key={version.uuid}
                                                    className={`border-b border-border/30 ${version.isCurrent
                                                        ? 'bg-gold/5'
                                                        : ''
                                                        }`}
                                                >
                                                    <td className="py-3 pr-4 font-medium text-foreground">
                                                        {version.reference}
                                                        {version.isCurrent && (
                                                            <span className="ml-2 inline-flex items-center rounded-[0.375rem] bg-gold/20 px-2 py-0.5 text-[0.5625rem] font-bold text-[var(--kora)]">
                                                                Actuelle
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {formatDateShort(
                                                            version.effectiveFrom,
                                                        )}
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {formatDateShort(
                                                            version.effectiveUntil,
                                                        )}
                                                    </td>
                                                    <td className="py-3 pr-4 font-bold text-foreground">
                                                        {formatAmount(
                                                            version.baseAmount,
                                                            version.currency,
                                                        )}
                                                    </td>
                                                    <td className="py-3">
                                                        <KoraStatusBadge
                                                            label={
                                                                statusLabels[
                                                                version
                                                                    .status
                                                                ] ??
                                                                version.status
                                                            }
                                                            tone={
                                                                statusTones[
                                                                version
                                                                    .status
                                                                ] ?? 'muted'
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="rounded-[1rem] border border-dashed border-border/60 bg-card p-12 text-center">
                                    <Calendar className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                    <p className="font-medium text-foreground">
                                        Aucune grille sur ce segment
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Aucune période tarifaire enregistrée
                                        pour ce segment.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab: Historique (timeline) */}
                    {tab === 'history' && (
                        <div>
                            <div className="mb-4 border-b border-border/60 pb-4">
                                <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                    Journal des événements
                                </span>
                                <h3 className="text-[1.1875rem] font-bold text-foreground">
                                    Historique de la grille
                                </h3>
                                <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                    Modifications de prix, de statut et
                                    événements de cycle de vie.
                                </p>
                            </div>

                            {timeline.length > 0 ? (
                                <div className="grid">
                                    {timeline.map((event, i) => (
                                        <div
                                            key={i}
                                            className="relative pb-5 pl-8 last:pb-0"
                                        >
                                            <span className="absolute top-[3px] left-[7px] h-[10px] w-[10px] rounded-full border-[3px] border-card bg-[var(--digital)] shadow-[0_0_0_1px_var(--digital)]" />
                                            {i < timeline.length - 1 && (
                                                <span className="absolute top-[14px] bottom-[-8px] left-[8px] w-px bg-border" />
                                            )}
                                            <strong className="block text-[0.6875rem] text-foreground">
                                                {event.label}
                                            </strong>
                                            <small className="mt-1 block text-[0.5625rem] text-muted-foreground">
                                                {formatDate(event.date)}
                                            </small>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-[1rem] border border-dashed border-border/60 bg-card p-12 text-center">
                                    <Clock className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                    <p className="font-medium text-foreground">
                                        Aucun événement enregistré
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Les modifications de prix et de statut
                                        apparaîtront ici.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Detail-rule (Règle métier) */}
                    <div className="mt-5 rounded-r-[0.875rem] border-l-[3px] border-gold bg-muted p-4">
                        <span className="block text-[0.5rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                            Règle métier
                        </span>
                        <p className="mt-1.5 text-[0.6875rem] leading-relaxed text-foreground/80">
                            {BUSINESS_RULE}
                        </p>
                    </div>
                </section>

                {/* Panel aside (milestones) */}
                <aside className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                    <div className="mb-4">
                        <h2 className="text-[0.9375rem] font-bold text-foreground">
                            Application tarifaire
                        </h2>
                        <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                            Étapes du cycle de vie de la grille
                        </p>
                    </div>
                    <div className="grid">
                        {timeline.map((event, i) => (
                            <div
                                key={i}
                                className="relative pb-5 pl-8 last:pb-0"
                            >
                                <span className="absolute top-[3px] left-[7px] h-[10px] w-[10px] rounded-full border-[3px] border-card bg-gold shadow-[0_0_0_1px_var(--gold)]" />
                                {i < timeline.length - 1 && (
                                    <span className="absolute top-[14px] bottom-[-8px] left-[8px] w-px bg-border" />
                                )}
                                <strong className="block text-[0.6875rem] text-foreground">
                                    {event.label}
                                </strong>
                                <small className="mt-1 block text-[0.5625rem] text-muted-foreground">
                                    {formatDate(event.date)}
                                </small>
                            </div>
                        ))}
                        {timeline.length === 0 && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Aucune étape enregistrée.
                            </p>
                        )}
                    </div>
                </aside>
            </div>

            <FareFormDialog
                open={editDialog}
                fare={fare}
                lines={lines}
                onOpenChange={setEditDialog}
            />
        </>
    );
}

FareDetailPage.layout = {
    breadcrumbs: [
        { title: 'Tarifs', href: '/fares' } satisfies BreadcrumbItem,
        { title: 'Détail', href: '/fares' } satisfies BreadcrumbItem,
    ],
};

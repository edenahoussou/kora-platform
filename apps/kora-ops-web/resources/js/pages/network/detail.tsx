import { Head, router } from '@inertiajs/react';
import { ArrowLeft, MapPin, Pencil, Plus, Power, Tag } from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { LineFormDialog } from '@/components/lines/line-form-dialog';
import type { LineRecord } from '@/components/lines/line-form-dialog';
import { StopFormDialog } from '@/components/lines/stop-form-dialog';
import type { StopRecord } from '@/components/lines/stop-form-dialog';
import { Button } from '@/components/ui/button';
import network from '@/routes/network';
import type { BreadcrumbItem } from '@/types';

type LineDetail = LineRecord & {
    stops: StopRecord[];
    activeStopsCount: number;
    fares: FareSummary[];
    createdAt: string | null;
    updatedAt: string | null;
};

type FareSummary = {
    uuid: string;
    originName: string;
    destinationName: string;
    serviceClassCode: string;
    serviceClassName: string;
    baseAmount: number;
    currency: string;
    effectiveFrom: string | null;
};

type TimelineEvent = {
    label: string;
    date: string | null;
};

type PageProps = {
    line: LineDetail;
    statuses: Record<string, string>;
    timeline: TimelineEvent[];
    permissions: {
        canUpdate: boolean;
        canDeactivate: boolean;
        canManageStops: boolean;
        canUpdateStop: boolean;
        canDeactivateStop: boolean;
        canManageFares: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    archived: 'Archivée',
};
const statusTones: Record<string, 'navy' | 'gold' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    archived: 'muted',
};
const typeLabels: Record<string, string> = {
    nationale: 'Nationale',
    internationale: 'Internationale',
    regionale: 'Régionale',
};
const stopStatusLabels: Record<string, string> = {
    active: 'Actif',
    draft: 'Brouillon',
    archived: 'Archivé',
};
const stopStatusTones: Record<string, 'navy' | 'gold' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    archived: 'muted',
};

type Tab = 'itinerary' | 'stops' | 'schedules' | 'fares';

const BUSINESS_RULE =
    "L'ordre des arrêts détermine les segments vendables et la disponibilité sur chaque portion.";

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

export default function NetworkDetailPage({
    line,
    statuses,
    timeline,
    permissions,
}: PageProps) {
    const [tab, setTab] = useState<Tab>('itinerary');
    const [lineDialog, setLineDialog] = useState(false);
    const [stopDialog, setStopDialog] = useState(false);
    const [selectedStop, setSelectedStop] = useState<StopRecord | null>(null);
    const [impactStop, setImpactStop] = useState<StopRecord | null>(null);

    const activeStops = line.stops.filter((s) => s.status === 'active');
    const nextOrder = Math.max(0, ...line.stops.map((s) => s.stopOrder)) + 1;

    const deactivateStop = (stop: StopRecord) => {
        router.patch(
            network.stops.deactivate.url({ line: line.uuid, stop: stop.uuid }),
            {},
            { preserveScroll: true },
        );
        setImpactStop(null);
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: 'itinerary', label: 'Itinéraire' },
        { key: 'stops', label: 'Arrêts' },
        { key: 'schedules', label: 'Horaires' },
        { key: 'fares', label: 'Tarifs' },
    ];

    const tabLabel = tabs.find((t) => t.key === tab)?.label ?? '';

    const infoItems: { label: string; value: string }[] = [
        { label: 'Code', value: line.code },
        { label: 'Nom', value: line.name },
        { label: 'Type', value: typeLabels[line.type] ?? line.type },
        {
            label: 'Statut',
            value: statusLabels[line.status] ?? line.status,
        },
        {
            label: 'Distance',
            value: line.distanceKm ? `${line.distanceKm} km` : '—',
        },
        {
            label: 'Durée estimée',
            value: line.estimatedDurationMinutes
                ? `${Math.floor(line.estimatedDurationMinutes / 60)}h${(line.estimatedDurationMinutes % 60).toString().padStart(2, '0')}`
                : '—',
        },
        {
            label: 'Pays traversés',
            value:
                line.countriesCrossed && line.countriesCrossed.length > 0
                    ? line.countriesCrossed.join(' · ')
                    : '—',
        },
        {
            label: 'Arrêts actifs',
            value: `${line.activeStopsCount} / ${line.stops.length}`,
        },
    ];

    return (
        <>
            <Head title={`${line.code} — ${line.name}`} />

            {/* 1. PAGE-HEAD (patron générique maquette) */}
            <div className="mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] font-bold tracking-[0.035em] text-muted-foreground">
                        KORA OPS / Lignes & arrêts / {line.code}
                    </div>
                    <h1 className="text-[2rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Ligne commerciale
                    </h1>
                    <p className="mt-1.5 text-sm text-muted-foreground">
                        {line.code} · {line.name} · informations métier et suivi
                        opérationnel
                    </p>
                </div>
                <div className="flex flex-wrap gap-2.5">
                    <button
                        onClick={() => router.get(network.index.url())}
                        className="inline-flex h-10 items-center gap-2 rounded-[0.75rem] border border-border/50 bg-card px-4 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        Retour
                    </button>
                    {permissions.canUpdate && (
                        <Button
                            onClick={() => setLineDialog(true)}
                            className="h-10 rounded-[0.75rem] bg-gold px-4 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                        >
                            <Pencil className="size-4" />
                            Modifier
                        </Button>
                    )}
                </div>
            </div>

            {/* 2. TABS */}
            <div className="mb-5 flex gap-1 border-b border-border/60">
                {tabs.map(({ key, label }) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        className={`border-b-2 px-4 py-3 text-[0.6875rem] font-extrabold tracking-[0.035em] transition-colors ${
                            tab === key
                                ? 'border-gold text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {/* 3. DETAIL-IDENTITY (banner gradient navy→digital) */}
            <section className="relative mb-6 flex items-center justify-between gap-6 overflow-hidden rounded-[1.25rem] bg-gradient-to-br from-[var(--kora)] to-[var(--digital)] p-7 text-white shadow-[0_18px_42px_rgba(0,29,78,0.16)]">
                <div className="relative z-10 max-w-[760px]">
                    <span className="mb-1.5 block text-[0.5625rem] font-black tracking-[0.13em] text-gold uppercase">
                        Ligne commerciale
                    </span>
                    <h2 className="text-[1.6875rem] font-extrabold tracking-[-0.03em]">
                        {line.code} · {line.name}
                    </h2>
                    <p className="mt-2 max-w-[560px] text-[0.6875rem] leading-relaxed text-white/72">
                        {BUSINESS_RULE}
                    </p>
                </div>
                <span className="relative z-10 shrink-0">
                    <KoraStatusBadge
                        label={statusLabels[line.status] ?? line.status}
                        tone={statusTones[line.status] ?? 'gold'}
                        className="border border-white/20 bg-white/5 shadow-[0_8px_24px_rgba(0,29,78,0.18)]"
                    />
                </span>
            </section>

            {/* 4. DETAIL-GRID (2 colonnes : panel principal + aside timeline) */}
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

                    {/* Tab: Itinéraire (info-grid) */}
                    {tab === 'itinerary' && (
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
                    )}

                    {/* Tab: Arrêts */}
                    {tab === 'stops' && (
                        <div className="space-y-4">
                            <div className="flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Itinéraire ordonné
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        {activeStops.length} arrêts actifs
                                        {line.distanceKm
                                            ? ` · ${line.distanceKm} km`
                                            : ''}
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Le déplacement ou la désactivation d'un
                                        arrêt conserve les billets déjà émis.
                                    </p>
                                </div>
                                {permissions.canManageStops && (
                                    <Button
                                        onClick={() => {
                                            setSelectedStop(null);
                                            setStopDialog(true);
                                        }}
                                        className="h-10 shrink-0 rounded-[0.75rem] bg-gold px-4 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                                    >
                                        <Plus className="size-4" />
                                        Ajouter un arrêt
                                    </Button>
                                )}
                            </div>
                            <div className="grid gap-2.5">
                                {line.stops.map((stop) => (
                                    <div key={stop.uuid}>
                                        <article className="grid grid-cols-[38px_1fr_auto] items-center gap-2.5 rounded-[0.875rem] border border-border/50 bg-card p-3.5 transition-shadow hover:shadow-md sm:grid-cols-[38px_1fr_auto_auto]">
                                            <span className="grid size-[34px] place-items-center rounded-full bg-gold text-sm font-bold text-[var(--kora)]">
                                                {stop.stopOrder
                                                    .toString()
                                                    .padStart(2, '0')}
                                            </span>
                                            <div>
                                                <strong className="block text-foreground">
                                                    {stop.name}
                                                </strong>
                                                <span className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                                    {stop.kmFromOrigin} km ·{' '}
                                                    {stop.city}, {stop.country}
                                                    {stop.latitude &&
                                                        ` · GPS ${stop.latitude}, ${stop.longitude}`}
                                                </span>
                                            </div>
                                            <span className="hidden sm:inline-flex">
                                                <KoraStatusBadge
                                                    label={
                                                        stopStatusLabels[
                                                            stop.status
                                                        ] ?? 'Actif'
                                                    }
                                                    tone={
                                                        stopStatusTones[
                                                            stop.status
                                                        ] ?? 'navy'
                                                    }
                                                />
                                            </span>
                                            <div className="flex gap-1.5">
                                                {permissions.canUpdateStop && (
                                                    <button
                                                        onClick={() => {
                                                            setSelectedStop(
                                                                stop,
                                                            );
                                                            setStopDialog(true);
                                                        }}
                                                        className="rounded-[0.5rem] border border-border/40 bg-muted px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted/70"
                                                    >
                                                        Modifier
                                                    </button>
                                                )}
                                                {permissions.canDeactivateStop &&
                                                    stop.status ===
                                                        'active' && (
                                                        <button
                                                            onClick={() =>
                                                                setImpactStop(
                                                                    stop,
                                                                )
                                                            }
                                                            className="flex items-center gap-1 rounded-[0.5rem] border border-border/40 bg-muted px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted/70"
                                                        >
                                                            <Power className="size-3" />
                                                            Désactiver
                                                        </button>
                                                    )}
                                            </div>
                                        </article>
                                        {impactStop?.uuid === stop.uuid && (
                                            <section className="mb-2 ml-12 rounded-r-[0.875rem] border-l-[3px] border-gold bg-muted/50 p-4">
                                                <strong className="block text-sm text-foreground">
                                                    Contrôle avant désactivation
                                                </strong>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Les billets déjà émis
                                                    restent valides. Les
                                                    nouvelles ventes vers cet
                                                    arrêt seront bloquées.
                                                </p>
                                                <div className="mt-3 flex gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setImpactStop(null)
                                                        }
                                                    >
                                                        Annuler
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        className="bg-gold font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                                                        onClick={() =>
                                                            deactivateStop(stop)
                                                        }
                                                    >
                                                        Confirmer et notifier la
                                                        Direction
                                                    </Button>
                                                </div>
                                            </section>
                                        )}
                                    </div>
                                ))}
                                {line.stops.length === 0 && (
                                    <div className="rounded-[1rem] border border-dashed border-border/60 bg-card p-12 text-center">
                                        <MapPin className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                        <p className="font-medium text-foreground">
                                            Aucun arrêt défini
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Ajoutez le premier arrêt pour
                                            structurer l'itinéraire.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Tab: Horaires */}
                    {tab === 'schedules' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Horaires théoriques
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        Arrêts · Arrivée / Départ
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Les horaires seront configurables par
                                        départ.
                                    </p>
                                </div>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                            <th className="pr-4 pb-3 font-medium">
                                                Arrêt
                                            </th>
                                            <th className="pr-4 pb-3 font-medium">
                                                Arrivée
                                            </th>
                                            <th className="pr-4 pb-3 font-medium">
                                                Départ
                                            </th>
                                            <th className="pb-3 font-medium">
                                                Temps d'arrêt
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {line.stops.map((stop, idx) => (
                                            <tr
                                                key={stop.uuid}
                                                className="border-b border-border/30"
                                            >
                                                <td className="py-3 pr-4 font-medium text-foreground">
                                                    {stop.name}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {idx === 0
                                                        ? '—'
                                                        : 'À définir'}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {idx ===
                                                    line.stops.length - 1
                                                        ? '—'
                                                        : 'À définir'}
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {idx === 0 ||
                                                    idx ===
                                                        line.stops.length - 1
                                                        ? idx === 0
                                                            ? 'Origine'
                                                            : 'Terminus'
                                                        : 'À définir'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <p className="mt-4 text-xs text-muted-foreground">
                                Les horaires théoriques seront configurables
                                dans une prochaine version.
                            </p>
                        </div>
                    )}

                    {/* Tab: Tarifs */}
                    {tab === 'fares' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Matrice O/D active
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        Tarifs origine — destination
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Chaque combinaison origine–destination
                                        possède son prix commercial par classe
                                        de service.
                                    </p>
                                </div>
                                <Button
                                    onClick={() => router.get('/fares')}
                                    variant="ghost"
                                    className="h-10 shrink-0 rounded-[0.75rem] px-4"
                                >
                                    <Tag className="size-4" />
                                    Ouvrir la matrice complète
                                </Button>
                            </div>

                            {line.fares.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                                <th className="pr-4 pb-3 font-medium">
                                                    Origine
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Destination
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Service
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Prix
                                                </th>
                                                <th className="pb-3 font-medium">
                                                    Effet au
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {line.fares.map((fare) => (
                                                <tr
                                                    key={fare.uuid}
                                                    className="border-b border-border/30"
                                                >
                                                    <td className="py-3 pr-4 font-medium text-foreground">
                                                        {fare.originName}
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {fare.destinationName}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <span className="inline-flex items-center rounded-[0.375rem] bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
                                                            {
                                                                fare.serviceClassName
                                                            }
                                                        </span>
                                                    </td>
                                                    <td className="py-3 pr-4 font-bold text-foreground">
                                                        {new Intl.NumberFormat(
                                                            'fr-FR',
                                                            {
                                                                maximumFractionDigits: 0,
                                                            },
                                                        ).format(
                                                            fare.baseAmount,
                                                        )}{' '}
                                                        {fare.currency}
                                                    </td>
                                                    <td className="py-3 text-xs text-muted-foreground">
                                                        {fare.effectiveFrom
                                                            ? new Date(
                                                                  fare.effectiveFrom,
                                                              ).toLocaleDateString(
                                                                  'fr-FR',
                                                                  {
                                                                      day: '2-digit',
                                                                      month: 'short',
                                                                      year: 'numeric',
                                                                  },
                                                              )
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="rounded-[1rem] border border-dashed border-border/60 bg-card p-12 text-center">
                                    <Tag className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                    <p className="font-medium text-foreground">
                                        Aucun tarif actif
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {activeStops.length >= 2
                                            ? 'Définissez les tarifs pour les segments de cette ligne.'
                                            : 'Ajoutez au moins 2 arrêts actifs pour générer la matrice O/D.'}
                                    </p>
                                    {permissions.canManageFares && (
                                        <Button
                                            onClick={() => router.get('/fares')}
                                            className="mt-4 h-10 rounded-[0.75rem] bg-gold px-4 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                                        >
                                            <Tag className="size-4" />
                                            Ouvrir la gestion des tarifs
                                        </Button>
                                    )}
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

                {/* Panel aside (timeline) */}
                <aside className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                    <div className="mb-4">
                        <h2 className="text-[0.9375rem] font-bold text-foreground">
                            Structure de la ligne
                        </h2>
                        <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                            Derniers événements enregistrés
                        </p>
                    </div>
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
                        {timeline.length === 0 && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Aucun événement enregistré.
                            </p>
                        )}
                    </div>
                </aside>
            </div>

            <LineFormDialog
                open={lineDialog}
                line={line}
                statuses={statuses}
                onOpenChange={setLineDialog}
            />
            <StopFormDialog
                open={stopDialog}
                lineUuid={line.uuid}
                stop={selectedStop}
                nextOrder={nextOrder}
                onOpenChange={setStopDialog}
            />
        </>
    );
}

NetworkDetailPage.layout = {
    breadcrumbs: [
        { title: 'Lignes & arrêts', href: '/network' } satisfies BreadcrumbItem,
        { title: 'Détail', href: '/network' } satisfies BreadcrumbItem,
    ],
};

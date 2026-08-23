import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BusFront,
    CheckCircle2,
    Clock,
    Package,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { DepartureFormDialog } from '@/components/departures/departure-form-dialog';
import type { DepartureRecord } from '@/components/departures/departure-form-dialog';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import departures from '@/routes/departures';
import type { BreadcrumbItem } from '@/types';

type Stop = {
    uuid: string;
    name: string;
    stopOrder: number;
    city: string;
};

type SeatItem = {
    uuid: string;
    code: string;
    type: string;
    sellable: boolean;
    status: string;
};

type DepartureDetail = DepartureRecord & {
    lineId: number;
    lineEstimatedDuration: number | null;
    stops: Stop[];
    busUuid: string;
    busSeatCount: number;
    busAgencyName: string | null;
    seats: SeatItem[];
    agencyId: number;
    agencyCity: string | null;
    agencyCountry: string | null;
    estimatedArrivalAt: string | null;
    creatorName: string | null;
    fillRate: number;
    reservationsCount: number;
    createdAt: string | null;
    updatedAt: string | null;
};

type TimelineEvent = {
    label: string;
    date: string | null;
};

type BusOption = { id: number; label: string; agencyId: number | null };
type PersonOption = { id: number; label: string };

type PageProps = {
    departure: DepartureDetail;
    statuses: Record<string, string>;
    lines: { id: number; label: string }[];
    agencies: { id: number; name: string }[];
    buses: BusOption[];
    drivers: PersonOption[];
    conductors: PersonOption[];
    timeline: TimelineEvent[];
    permissions: {
        canUpdate: boolean;
        canCancel: boolean;
    };
};

const statusLabels: Record<string, string> = {
    scheduled: 'Programmé',
    boarding: 'Embarquement',
    departed: 'En route',
    arrived: 'Arrivé',
    cancelled: 'Annulé',
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    scheduled: 'navy',
    boarding: 'gold',
    departed: 'blue',
    arrived: 'navy',
    cancelled: 'muted',
};

const BUSINESS_RULE =
    "Le véhicule, l'équipage, le quai et le manifeste doivent être conformes avant le départ.";

type Tab = 'exploitation' | 'passengers' | 'crew' | 'parcels';

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
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

function formatTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function DepartureDetailPage({
    departure,
    statuses,
    lines,
    agencies,
    buses,
    drivers,
    conductors,
    timeline,
    permissions,
}: PageProps) {
    const [tab, setTab] = useState<Tab>('exploitation');
    const [editDialog, setEditDialog] = useState(false);

    const changeStatus = (status: string) => {
        if (
            window.confirm(
                'Voulez-vous vraiment modifier le statut de ce départ ?',
            )
        ) {
            router.patch(
                departures.status.url({ departure: departure.uuid }),
                { status },
                { preserveScroll: true },
            );
        }
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: 'exploitation', label: 'Exploitation' },
        { key: 'passengers', label: 'Passagers' },
        { key: 'crew', label: 'Équipage' },
        { key: 'parcels', label: 'Colis' },
    ];

    const tabLabel = tabs.find((t) => t.key === tab)?.label ?? '';

    // Contextual actions based on status (from maquette line 613)
    const renderActions = () => {
        if (
            departure.status === 'arrived' ||
            departure.status === 'cancelled'
        ) {
            return null;
        }

        if (departure.status === 'scheduled') {
            return (
                <>
                    {permissions.canUpdate && (
                        <button
                            onClick={() => changeStatus('boarding')}
                            className="inline-flex h-10 items-center gap-2 rounded-[0.6875rem] border border-border bg-card px-4 text-[0.8125rem] font-extrabold text-foreground transition-all hover:-translate-y-px hover:border-border/80 dark:text-foreground"
                        >
                            <CheckCircle2 className="size-4" />
                            Ouvrir l'embarquement
                        </button>
                    )}
                    {permissions.canCancel && (
                        <button
                            onClick={() => changeStatus('cancelled')}
                            className="inline-flex h-10 items-center gap-2 rounded-[0.6875rem] bg-gold px-4 text-[0.8125rem] font-extrabold text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-all hover:-translate-y-px"
                        >
                            <XCircle className="size-4" />
                            Annuler le départ
                        </button>
                    )}
                </>
            );
        }

        if (departure.status === 'boarding') {
            return (
                <>
                    {permissions.canUpdate && (
                        <button
                            onClick={() => changeStatus('departed')}
                            className="inline-flex h-10 items-center gap-2 rounded-[0.6875rem] bg-gold px-4 text-[0.8125rem] font-extrabold text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-all hover:-translate-y-px"
                        >
                            <BusFront className="size-4" />
                            Enregistrer le départ effectif
                        </button>
                    )}
                    {permissions.canCancel && (
                        <button
                            onClick={() => changeStatus('cancelled')}
                            className="inline-flex h-10 items-center gap-2 rounded-[0.6875rem] bg-gold px-4 text-[0.8125rem] font-extrabold text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-all hover:-translate-y-px"
                        >
                            <XCircle className="size-4" />
                            Annuler le départ
                        </button>
                    )}
                </>
            );
        }

        if (departure.status === 'departed') {
            return (
                <>
                    {permissions.canUpdate && (
                        <button
                            onClick={() => changeStatus('arrived')}
                            className="inline-flex h-10 items-center gap-2 rounded-[0.6875rem] bg-gold px-4 text-[0.8125rem] font-extrabold text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-all hover:-translate-y-px"
                        >
                            <CheckCircle2 className="size-4" />
                            Déclarer l'arrivée
                        </button>
                    )}
                </>
            );
        }

        return null;
    };

    // Compute estimated stop times based on line duration
    const stopSchedule = () => {
        if (!departure.stops.length || !departure.departureTime) {
            return [];
        }

        const [hours, minutes] = departure.departureTime.split(':').map(Number);
        const baseTime = new Date();
        baseTime.setHours(hours, minutes, 0, 0);

        const totalDuration = departure.lineEstimatedDuration ?? 0;
        const stopCount = departure.stops.length;

        return departure.stops.map((stop, index) => {
            const fraction = stopCount > 1 ? index / (stopCount - 1) : 0;
            const offsetMinutes = Math.round(totalDuration * fraction);
            const time = new Date(baseTime.getTime() + offsetMinutes * 60000);

            return {
                ...stop,
                estimatedTime: time.toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                }),
            };
        });
    };

    const schedule = stopSchedule();
    const sellableSeatsCount = departure.seats.filter(
        (s) => s.sellable,
    ).length;
    const crewSeatsCount = departure.seats.filter((s) => !s.sellable).length;

    return (
        <>
            <Head title={`${departure.lineCode} — Départ`} />

            {/* 1. PAGE-HEAD */}
            <div className="mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] font-bold tracking-[0.035em] text-muted-foreground">
                        KORA OPS / Exploitation / Départs / {departure.lineCode}{' '}
                        · {departure.departureTime}
                    </div>
                    <h1 className="text-[2rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Départ {departure.lineCode}
                    </h1>
                    <p className="mt-1.5 text-sm text-muted-foreground">
                        {departure.lineName} · {departure.departureTime} ·{' '}
                        {departure.busRegistration}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        onClick={() => router.get(departures.index.url())}
                        className="inline-flex h-10 items-center gap-2 rounded-[0.6875rem] border border-border bg-card px-4 text-[0.8125rem] font-extrabold text-foreground transition-all hover:-translate-y-px dark:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        Retour
                    </button>
                    {renderActions()}
                </div>
            </div>

            {/* 2. TABS */}
            <div className="mb-5 flex gap-1 overflow-x-auto border-b border-border/60">
                {tabs.map(({ key, label }) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        className={`border-b-2 px-4 py-3 text-[0.6875rem] font-extrabold tracking-[0.035em] whitespace-nowrap transition-colors ${
                            tab === key
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
                        Départ
                    </span>
                    <h2 className="text-[1.6875rem] font-extrabold tracking-[-0.03em]">
                        {departure.lineCode} · {departure.departureTime}
                    </h2>
                    <p className="mt-2 max-w-[560px] text-[0.6875rem] leading-relaxed text-white/72">
                        {BUSINESS_RULE}
                    </p>
                </div>
                <span className="relative z-10 shrink-0">
                    <KoraStatusBadge
                        label={
                            statusLabels[departure.status] ?? departure.status
                        }
                        tone={statusTones[departure.status] ?? 'gold'}
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
                                Informations propres à ce départ
                            </p>
                        </div>
                    </div>

                    {/* Tab: Exploitation */}
                    {tab === 'exploitation' && (
                        <div className="space-y-5">
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <InfoCard
                                    label="Ligne"
                                    value={`${departure.lineCode} · ${departure.lineName}`}
                                />
                                <InfoCard
                                    label="Date"
                                    value={formatDateShort(
                                        departure.departureDate,
                                    )}
                                />
                                <InfoCard
                                    label="Heure de départ"
                                    value={departure.departureTime ?? '—'}
                                />
                                <InfoCard
                                    label="Arrivée estimée"
                                    value={formatTime(
                                        departure.estimatedArrivalAt,
                                    )}
                                />
                                <InfoCard
                                    label="Bus"
                                    value={departure.busRegistration}
                                />
                                <InfoCard
                                    label="Agence"
                                    value={departure.agencyName ?? '—'}
                                />
                                <InfoCard
                                    label="Places"
                                    value={`${departure.busSeatCount}`}
                                />
                                <InfoCard
                                    label="Remplissage"
                                    value={`${departure.fillRate}%`}
                                />
                                <InfoCard
                                    label="Réservations"
                                    value={`${departure.reservationsCount}`}
                                />
                            </div>

                            {/* Horaires estimés par arrêt */}
                            {schedule.length > 0 && (
                                <div>
                                    <div className="mb-3 flex items-center gap-2">
                                        <Clock className="size-3.5 text-muted-foreground" />
                                        <span className="text-[0.5625rem] font-black tracking-[0.13em] text-muted-foreground uppercase">
                                            Horaires estimés par arrêt
                                        </span>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                                    <th className="pr-4 pb-3 font-medium">
                                                        Arrêt
                                                    </th>
                                                    <th className="pr-4 pb-3 font-medium">
                                                        Ville
                                                    </th>
                                                    <th className="pr-4 pb-3 font-medium">
                                                        Heure estimée
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {schedule.map((stop) => (
                                                    <tr
                                                        key={stop.uuid}
                                                        className="border-b border-border/30 hover:bg-muted/30"
                                                    >
                                                        <td className="py-2.5 pr-4 font-bold text-foreground">
                                                            {stop.stopOrder
                                                                .toString()
                                                                .padStart(
                                                                    2,
                                                                    '0',
                                                                )}{' '}
                                                            {stop.name}
                                                        </td>
                                                        <td className="py-2.5 pr-4 text-muted-foreground">
                                                            {stop.city}
                                                        </td>
                                                        <td className="py-2.5 font-medium text-foreground">
                                                            {stop.estimatedTime}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {/* Detail-rule */}
                            <div className="rounded-[0_0.875rem_0.875rem_0] border-l-[3px] border-gold bg-muted p-4">
                                <span className="block text-[0.5rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                    Règle métier
                                </span>
                                <p className="mt-1.5 text-[0.6875rem] leading-relaxed text-foreground">
                                    {BUSINESS_RULE}
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Tab: Passagers */}
                    {tab === 'passengers' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Liste voyageurs
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        {departure.reservationsCount} passager
                                        {departure.reservationsCount > 1
                                            ? 's'
                                            : ''}{' '}
                                        · {departure.fillRate}% de remplissage
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Disponibilité calculée sur chaque
                                        segment du départ.
                                    </p>
                                </div>
                            </div>

                            {departure.reservationsCount > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                                <th className="pr-4 pb-3 font-medium">
                                                    Siège
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Passager
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Montée
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Descente
                                                </th>
                                                <th className="pr-4 pb-3 font-medium">
                                                    Billet
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {/* Réservations alimentées par le module Booking (MEZ-25) */}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="rounded-[1rem] border border-dashed border-border/60 bg-card p-12 text-center">
                                    <Users className="mx-auto size-10 text-muted-foreground/40" />
                                    <h4 className="mt-3 text-sm font-bold text-foreground">
                                        Aucune réservation
                                    </h4>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Les passagers apparaîtront ici dès que
                                        le module Booking sera alimenté.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab: Équipage */}
                    {tab === 'crew' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Équipe affectée
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        Conducteur et convoyeur
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Les habilitations sont contrôlées avant
                                        l'ouverture de l'embarquement.
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-3">
                                {/* Conducteur */}
                                <article className="flex items-center gap-3.5 rounded-[1rem] border border-border/50 bg-card p-4 shadow-[0_10px_24px_rgba(0,29,78,0.06)]">
                                    <span className="grid size-12 shrink-0 place-items-center rounded-[0.75rem] bg-gold/20 text-[0.875rem] font-black text-[var(--kora)] dark:bg-gold/15 dark:text-gold">
                                        {departure.driverName
                                            ? departure.driverName
                                                  .split(' ')
                                                  .map((n) => n[0])
                                                  .join('')
                                                  .slice(0, 2)
                                                  .toUpperCase()
                                            : '—'}
                                    </span>
                                    <div className="flex-1">
                                        <small className="block text-[0.5rem] font-bold text-[var(--digital)]">
                                            Conducteur
                                        </small>
                                        <strong className="mt-1 block text-[0.8125rem] font-bold text-foreground">
                                            {departure.driverName ??
                                                'À affecter'}
                                        </strong>
                                        <p className="mt-0.5 text-[0.5625rem] text-muted-foreground">
                                            {departure.driverName
                                                ? 'Permis · habilitation requise'
                                                : 'Aucun conducteur assigné à ce départ'}
                                        </p>
                                    </div>
                                    <KoraStatusBadge
                                        label={
                                            departure.driverName
                                                ? 'Habilité'
                                                : 'À affecter'
                                        }
                                        tone={
                                            departure.driverName
                                                ? 'navy'
                                                : 'muted'
                                        }
                                    />
                                </article>

                                {/* Convoyeur */}
                                <article className="flex items-center gap-3.5 rounded-[1rem] border border-border/50 bg-card p-4 shadow-[0_10px_24px_rgba(0,29,78,0.06)]">
                                    <span className="grid size-12 shrink-0 place-items-center rounded-[0.75rem] bg-gold/20 text-[0.875rem] font-black text-[var(--kora)] dark:bg-gold/15 dark:text-gold">
                                        {departure.conductorName
                                            ? departure.conductorName
                                                  .split(' ')
                                                  .map((n) => n[0])
                                                  .join('')
                                                  .slice(0, 2)
                                                  .toUpperCase()
                                            : '—'}
                                    </span>
                                    <div className="flex-1">
                                        <small className="block text-[0.5rem] font-bold text-[var(--digital)]">
                                            Convoyeur
                                        </small>
                                        <strong className="mt-1 block text-[0.8125rem] font-bold text-foreground">
                                            {departure.conductorName ??
                                                'À affecter'}
                                        </strong>
                                        <p className="mt-0.5 text-[0.5625rem] text-muted-foreground">
                                            {departure.conductorName
                                                ? 'Terminal P501 · session prête'
                                                : 'Aucun convoyeur assigné à ce départ'}
                                        </p>
                                    </div>
                                    <KoraStatusBadge
                                        label={
                                            departure.conductorName
                                                ? 'Disponible'
                                                : 'À affecter'
                                        }
                                        tone={
                                            departure.conductorName
                                                ? 'navy'
                                                : 'muted'
                                        }
                                    />
                                </article>
                            </div>
                        </div>
                    )}

                    {/* Tab: Colis */}
                    {tab === 'parcels' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Soute du départ
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        Module colis — Sprint 5
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Chaque colis doit être scanné avant la
                                        fermeture de la soute.
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-[1rem] border border-dashed border-border/60 bg-muted/20 p-8 text-center">
                                <span className="mx-auto grid size-12 place-items-center rounded-[0.9rem] bg-muted text-muted-foreground">
                                    <Package className="size-5" />
                                </span>
                                <h4 className="mt-3 text-sm font-bold text-foreground">
                                    Données disponibles au Sprint 5
                                </h4>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Le module colis (référence, destination,
                                    poids, scan) sera alimenté par le Sprint 5.
                                </p>
                            </div>

                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                            <th className="pr-4 pb-3 font-medium">
                                                Référence
                                            </th>
                                            <th className="pr-4 pb-3 font-medium">
                                                Destination
                                            </th>
                                            <th className="pr-4 pb-3 font-medium">
                                                Poids
                                            </th>
                                            <th className="pr-4 pb-3 font-medium">
                                                Scan
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {/* Colis alimentés par le module Colis (Sprint 5) */}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </section>

                {/* Aside — Cycle du départ */}
                <aside className="space-y-4">
                    {/* Cycle du départ — checklist */}
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-3 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Cycle du départ
                        </h3>
                        <div className="space-y-2.5">
                            <ChecklistItem
                                label="Embarquement ouvert"
                                done={departure.status !== 'scheduled'}
                            />
                            <ChecklistItem
                                label="Véhicule contrôlé"
                                done={departure.status !== 'scheduled'}
                            />
                            <ChecklistItem
                                label="Équipage affecté"
                                done={Boolean(
                                    departure.driverName &&
                                    departure.conductorName,
                                )}
                            />
                        </div>
                    </section>

                    {/* Plan de sièges du véhicule affecté */}
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-1 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Plan de sièges
                        </h3>
                        <p className="mb-3 text-[0.625rem] text-muted-foreground">
                            {departure.busRegistration} ·{' '}
                            {sellableSeatsCount} vendables ·{' '}
                            {crewSeatsCount} équipage
                        </p>
                        {departure.seats.length > 0 ? (
                            <>
                                <div className="grid grid-cols-6 gap-1.5">
                                    {departure.seats.map((seat) => (
                                        <span
                                            key={seat.uuid}
                                            title={`${seat.code} · ${seat.sellable ? 'Vendable' : 'Équipage'} · ${seat.status}`}
                                            className={`grid aspect-square place-items-center rounded-[0.4rem] text-[0.5rem] font-bold ${
                                                !seat.sellable
                                                    ? 'bg-muted text-muted-foreground'
                                                    : seat.status === 'active'
                                                      ? 'bg-gold/20 text-[var(--kora)] dark:text-gold'
                                                      : 'bg-[var(--kora)]/10 text-[var(--kora)] dark:bg-[var(--digital)]/15 dark:text-[var(--digital)]'
                                            }`}
                                        >
                                            {seat.code}
                                        </span>
                                    ))}
                                </div>
                                <div className="mt-3 flex flex-wrap gap-3 text-[0.5625rem] text-muted-foreground">
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-[0.2rem] bg-gold/20" />
                                        Vendable
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-[0.2rem] bg-[var(--kora)]/10 dark:bg-[var(--digital)]/15" />
                                        Réservé / bloqué
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-[0.2rem] bg-muted" />
                                        Équipage
                                    </span>
                                </div>
                            </>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Aucun plan de sièges disponible pour ce
                                véhicule.
                            </p>
                        )}
                    </section>

                    {/* Timeline */}
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-1 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Chronologie
                        </h3>
                        <p className="mb-4 text-[0.625rem] text-muted-foreground">
                            Derniers événements enregistrés
                        </p>
                        <div className="relative ml-2 space-y-4 border-l-2 border-border/60 pl-6">
                            {timeline.map((event, index) => (
                                <div key={index} className="relative">
                                    <span className="absolute top-1 -left-[31px] size-3 rounded-full border-2 border-card bg-gold" />
                                    <p className="text-sm font-bold text-foreground">
                                        {event.label}
                                    </p>
                                    <span className="text-xs text-muted-foreground">
                                        {formatDate(event.date)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Fiche départ résumée */}
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-3 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Fiche départ
                        </h3>
                        <div className="space-y-3 text-xs">
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">
                                    Ligne
                                </span>
                                <span className="font-bold text-foreground">
                                    {departure.lineCode}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">
                                    Bus
                                </span>
                                <span className="font-medium text-foreground">
                                    {departure.busRegistration}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">
                                    Agence
                                </span>
                                <span className="font-medium text-foreground">
                                    {departure.agencyName ?? '—'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">
                                    Départ
                                </span>
                                <span className="font-bold text-foreground">
                                    {departure.departureTime}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">
                                    Arrivée estimée
                                </span>
                                <span className="font-medium text-foreground">
                                    {formatTime(departure.estimatedArrivalAt)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">
                                    Statut
                                </span>
                                <KoraStatusBadge
                                    label={
                                        statusLabels[departure.status] ??
                                        departure.status
                                    }
                                    tone={
                                        statusTones[departure.status] ?? 'gold'
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between pt-1">
                                <span className="text-muted-foreground">
                                    Créé le
                                </span>
                                <span className="text-foreground">
                                    {formatDate(departure.createdAt)}
                                </span>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            {/* Modal d'édition */}
            <DepartureFormDialog
                open={editDialog}
                departure={departure}
                statuses={statuses}
                lines={lines}
                agencies={agencies}
                buses={buses}
                drivers={drivers}
                conductors={conductors}
                onOpenChange={setEditDialog}
            />
        </>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[0.75rem] bg-muted p-3.5">
            <small className="block text-[0.5625rem] text-muted-foreground">
                {label}
            </small>
            <strong className="mt-1.5 block text-[0.75rem] font-bold text-foreground">
                {value}
            </strong>
        </div>
    );
}

function ChecklistItem({ label, done }: { label: string; done: boolean }) {
    return (
        <article className="flex items-center gap-3 rounded-[0.875rem] border border-border/50 bg-card p-3.5">
            <span
                className={`grid size-8 shrink-0 place-items-center rounded-[0.625rem] text-[0.875rem] font-black ${
                    done
                        ? 'bg-[var(--kora)]/10 text-[var(--kora)] dark:bg-[var(--digital)]/15 dark:text-[var(--digital)]'
                        : 'bg-gold/20 text-[var(--kora)] dark:bg-gold/15 dark:text-gold'
                }`}
            >
                {done ? '✓' : '!'}
            </span>
            <div className="flex-1">
                <strong className="block text-[0.75rem] font-bold text-foreground">
                    {label}
                </strong>
            </div>
            <KoraStatusBadge
                label={done ? 'Conforme' : 'À traiter'}
                tone={done ? 'navy' : 'gold'}
            />
        </article>
    );
}

DepartureDetailPage.layout = {
    breadcrumbs: [
        { title: 'Départs', href: '/departures' } satisfies BreadcrumbItem,
        { title: 'Détail départ', href: '' } satisfies BreadcrumbItem,
    ],
};

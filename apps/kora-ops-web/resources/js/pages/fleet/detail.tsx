import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BusFront,
    CheckCircle2,
    Edit3,
    Layers,
    MapPin,
    RotateCcw,
    Shield,
    Wrench,
    Archive,
} from 'lucide-react';
import { useState } from 'react';
import { BusFormDialog } from '@/components/buses/bus-form-dialog';
import type { BusRecord } from '@/components/buses/bus-form-dialog';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { Button } from '@/components/ui/button';
import fleet from '@/routes/fleet';
import type { BreadcrumbItem } from '@/types';

type SeatItem = {
    uuid: string;
    code: string;
    type: string;
    sellable: boolean;
    status: string;
};

type DepartureItem = {
    uuid: string;
    lineCode: string;
    lineName: string;
    departureDate: string | null;
    departureTime: string | null;
    estimatedArrivalAt: string | null;
    status: string;
};

type TimelineEvent = {
    label: string;
    date: string | null;
};

type BusDetail = BusRecord & {
    brand: string;
    model: string;
    compositionCode: string | null;
    layout: { type?: string; codes: string[] }[];
    agencyCity: string | null;
    agencyCountry: string | null;
    seats: SeatItem[];
    departures: DepartureItem[];
    createdAt: string | null;
    updatedAt: string | null;
};

type PageProps = {
    bus: BusDetail;
    statuses: Record<string, string>;
    agencies: { id: number; name: string }[];
    models: { id: number; label: string }[];
    compositions: { id: number; label: string; seatCount: number }[];
    timeline: TimelineEvent[];
    permissions: {
        canUpdate: boolean;
        canDeactivate: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Actif',
    draft: 'Brouillon',
    maintenance: 'En maintenance',
    retired: 'Retiré',
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    maintenance: 'gold',
    retired: 'muted',
};

const seatTypeLabels: Record<string, string> = {
    standard: 'Standard',
    crew: 'Équipage',
    premium: 'Premium',
    vip: 'VIP',
};

const seatStatusLabels: Record<string, string> = {
    active: 'Actif',
    blocked: 'Bloqué',
    reserved: 'Réservé',
    occupied: 'Occupé',
};

const departureStatusLabels: Record<string, string> = {
    scheduled: 'Programmé',
    boarding: 'Embarquement',
    in_transit: 'En route',
    arrived: 'Arrivé',
    cancelled: 'Annulé',
};

type Tab = 'technical' | 'seats' | 'departures' | 'history';

const BUSINESS_RULE =
    'Chaque bus est rattaché à une agence et possède une composition fixe générant automatiquement son inventaire de sièges vendables et équipage.';

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
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

export default function FleetDetailPage({
    bus,
    statuses,
    agencies,
    models,
    compositions,
    timeline,
    permissions,
}: PageProps) {
    const [tab, setTab] = useState<Tab>('technical');
    const [editDialog, setEditDialog] = useState(false);

    const changeStatus = (newStatus: string) => {
        if (
            window.confirm(
                'Voulez-vous vraiment modifier le statut de ce bus ?',
            )
        ) {
            router.patch(
                fleet.status.url({ bus: bus.uuid }),
                { status: newStatus },
                { preserveScroll: true },
            );
        }
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: 'technical', label: 'Fiche technique' },
        { key: 'seats', label: `Plan de sièges (${bus.seats.length})` },
        { key: 'departures', label: `Départs (${bus.departures.length})` },
        { key: 'history', label: 'Historique' },
    ];

    const tabLabel = tabs.find((t) => t.key === tab)?.label ?? '';

    const sellableSeatsCount = bus.seats.filter((s) => s.sellable).length;
    const crewSeatsCount = bus.seats.filter((s) => !s.sellable).length;

    const infoItems: { label: string; value: string }[] = [
        { label: 'Immatriculation', value: bus.registrationNumber },
        { label: 'Modèle', value: bus.modelName ?? '—' },
        { label: 'Composition', value: bus.compositionName ?? '—' },
        { label: 'Capacité totale', value: `${bus.seatCount} places` },
        {
            label: 'Sièges passagers',
            value: `${sellableSeatsCount} vendables`,
        },
        {
            label: 'Sièges équipage',
            value: `${crewSeatsCount} réservés`,
        },
        { label: 'Agence de rattachement', value: bus.agencyName ?? 'Non rattaché' },
        {
            label: 'Localisation agence',
            value:
                bus.agencyCity && bus.agencyCountry
                    ? `${bus.agencyCity}, ${bus.agencyCountry}`
                    : bus.agencyCity ?? '—',
        },
        {
            label: 'Statut',
            value: statusLabels[bus.status] ?? bus.status,
        },
    ];

    return (
        <>
            <Head title={`${bus.registrationNumber} — Flotte`} />

            {/* 1. PAGE-HEAD */}
            <div className="mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] font-bold tracking-[0.035em] text-muted-foreground">
                        KORA OPS / Flotte / {bus.registrationNumber}
                    </div>
                    <h1 className="text-[2rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Bus {bus.registrationNumber}
                    </h1>
                    <p className="mt-1.5 text-sm text-muted-foreground">
                        {bus.modelName ?? 'Modèle standard'} · {bus.seatCount} places ·{' '}
                        {bus.agencyName ?? 'Agence KORA'}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        onClick={() => router.get(fleet.index.url())}
                        className="inline-flex h-10 items-center gap-2 rounded-[0.75rem] border border-border/50 bg-card px-4 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        Retour
                    </button>
                    {permissions.canUpdate && (
                        <Button
                            onClick={() => setEditDialog(true)}
                            className="h-10 rounded-[0.75rem] bg-gold px-4 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                        >
                            <Edit3 className="size-4" />
                            Modifier
                        </Button>
                    )}
                    {permissions.canDeactivate && bus.status === 'active' && (
                        <Button
                            onClick={() => changeStatus('maintenance')}
                            variant="outline"
                            className="h-10 rounded-[0.75rem] border-orange-500/30 text-orange-700 hover:bg-orange-500/10"
                        >
                            <Wrench className="size-4" />
                            Mettre en maintenance
                        </Button>
                    )}
                    {permissions.canDeactivate && bus.status !== 'retired' && (
                        <Button
                            onClick={() => changeStatus('retired')}
                            variant="outline"
                            className="h-10 rounded-[0.75rem] border-border/60 text-muted-foreground hover:text-foreground"
                        >
                            <Archive className="size-4" />
                            Retirer
                        </Button>
                    )}
                    {permissions.canDeactivate &&
                        bus.status !== 'active' &&
                        bus.status !== 'retired' && (
                            <Button
                                onClick={() => changeStatus('active')}
                                variant="outline"
                                className="h-10 rounded-[0.75rem] border-emerald-500/30 text-emerald-700 hover:bg-emerald-500/10"
                            >
                                <RotateCcw className="size-4" />
                                Réactiver
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
                        Véhicule opérationnel
                    </span>
                    <h2 className="text-[1.6875rem] font-extrabold tracking-[-0.03em]">
                        {bus.registrationNumber} · {bus.modelName}
                    </h2>
                    <p className="mt-2 max-w-[560px] text-[0.6875rem] leading-relaxed text-white/72">
                        {BUSINESS_RULE}
                    </p>
                </div>
                <span className="relative z-10 shrink-0">
                    <KoraStatusBadge
                        label={statusLabels[bus.status] ?? bus.status}
                        tone={statusTones[bus.status] ?? 'gold'}
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
                                Informations propres à ce véhicule
                            </p>
                        </div>
                    </div>

                    {/* Tab: Fiche technique */}
                    {tab === 'technical' && (
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

                            {/* Cartes récapitulatives */}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-[0.875rem] border border-border/50 bg-card p-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span className="grid size-7 place-items-center rounded-full bg-gold text-[var(--kora)]">
                                            <Layers className="size-3.5" />
                                        </span>
                                        <span className="text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                            Gabarit & Composition
                                        </span>
                                    </div>
                                    <strong className="block text-sm text-foreground">
                                        {bus.compositionName}
                                    </strong>
                                    <span className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                        {bus.seatCount} places · Disposition{' '}
                                        {bus.compositionCode ?? 'Standard'}
                                    </span>
                                </div>
                                <div className="rounded-[0.875rem] border border-border/50 bg-card p-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span className="grid size-7 place-items-center rounded-full bg-[var(--digital)] text-white">
                                            <MapPin className="size-3.5" />
                                        </span>
                                        <span className="text-[0.5625rem] font-black tracking-[0.13em] text-gold uppercase">
                                            Agence de rattachement
                                        </span>
                                    </div>
                                    <strong className="block text-sm text-foreground">
                                        {bus.agencyName ?? 'Non rattaché'}
                                    </strong>
                                    <span className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                        {bus.agencyCity && bus.agencyCountry
                                            ? `${bus.agencyCity}, ${bus.agencyCountry}`
                                            : 'Réseau KORA'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Tab: Plan de sièges */}
                    {tab === 'seats' && (
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Inventaire des places
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        {bus.seats.length} sièges configurés
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        {sellableSeatsCount} sièges ouverts à la vente,{' '}
                                        {crewSeatsCount} places équipage/bloquées.
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-flex items-center gap-1.5 rounded-[0.5rem] bg-muted px-2.5 py-1 text-xs text-foreground">
                                        <span className="size-2 rounded-full bg-emerald-500" />
                                        Passager ({sellableSeatsCount})
                                    </span>
                                    <span className="inline-flex items-center gap-1.5 rounded-[0.5rem] bg-muted px-2.5 py-1 text-xs text-foreground">
                                        <span className="size-2 rounded-full bg-gold" />
                                        Équipage ({crewSeatsCount})
                                    </span>
                                </div>
                            </div>

                            {/* Visualisation grille des sièges */}
                            <div className="rounded-[1rem] border border-border/50 bg-muted/20 p-5">
                                <div className="mb-3 text-xs font-bold text-muted-foreground">
                                    Disposition cabine
                                </div>
                                <div className="grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8">
                                    {bus.seats.map((seat) => {
                                        const isCrew = seat.type === 'crew' || !seat.sellable;

                                        return (
                                            <div
                                                key={seat.uuid}
                                                className={`flex flex-col items-center justify-center rounded-[0.65rem] border p-2 text-center transition-all ${isCrew
                                                    ? 'border-gold/40 bg-gold/10 text-[var(--kora)]'
                                                    : 'border-border/60 bg-card text-foreground hover:border-border'
                                                    }`}
                                            >
                                                <span className="text-xs font-black">
                                                    {seat.code}
                                                </span>
                                                <span className="mt-0.5 text-[0.5625rem] text-muted-foreground">
                                                    {seatTypeLabels[seat.type] ?? seat.type}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Table détaillée des sièges */}
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                            <th className="pr-4 pb-3 font-medium">Code</th>
                                            <th className="pr-4 pb-3 font-medium">Type</th>
                                            <th className="pr-4 pb-3 font-medium">Vendable</th>
                                            <th className="pr-4 pb-3 font-medium">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {bus.seats.map((seat) => (
                                            <tr
                                                key={seat.uuid}
                                                className="border-b border-border/30 hover:bg-muted/30"
                                            >
                                                <td className="py-2.5 pr-4 font-bold text-foreground">
                                                    {seat.code}
                                                </td>
                                                <td className="py-2.5 pr-4 text-muted-foreground">
                                                    {seatTypeLabels[seat.type] ?? seat.type}
                                                </td>
                                                <td className="py-2.5 pr-4">
                                                    {seat.sellable ? (
                                                        <span className="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                                                            <CheckCircle2 className="size-3.5" />
                                                            Oui
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                            <Shield className="size-3.5 text-gold" />
                                                            Non (équipage)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-2.5">
                                                    <span
                                                        className={`inline-flex rounded-full px-2 py-0.5 text-[0.625rem] font-bold ${seat.status === 'active'
                                                            ? 'bg-emerald-500/10 text-emerald-700'
                                                            : 'bg-gold/15 text-[var(--kora)]'
                                                            }`}
                                                    >
                                                        {seatStatusLabels[seat.status] ?? seat.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Tab: Départs */}
                    {tab === 'departures' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Missions opérationnelles
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        {bus.departures.length} départ
                                        {bus.departures.length > 1 ? 's' : ''} enregistré
                                        {bus.departures.length > 1 ? 's' : ''}
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Historique des départs récents ou programmés pour ce véhicule.
                                    </p>
                                </div>
                            </div>

                            {bus.departures.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                                <th className="pr-4 pb-3 font-medium">Ligne</th>
                                                <th className="pr-4 pb-3 font-medium">Date départ</th>
                                                <th className="pr-4 pb-3 font-medium">Heure</th>
                                                <th className="pr-4 pb-3 font-medium">Arrivée estimée</th>
                                                <th className="pr-4 pb-3 font-medium">Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {bus.departures.map((departure) => (
                                                <tr
                                                    key={departure.uuid}
                                                    className="border-b border-border/30 hover:bg-muted/30"
                                                >
                                                    <td className="py-3 pr-4 font-medium text-foreground">
                                                        <span className="block font-bold">
                                                            {departure.lineCode}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {departure.lineName}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {formatDateShort(departure.departureDate)}
                                                    </td>
                                                    <td className="py-3 pr-4 font-bold text-foreground">
                                                        {departure.departureTime ?? '—'}
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {formatDate(departure.estimatedArrivalAt)}
                                                    </td>
                                                    <td className="py-3">
                                                        <span className="inline-flex rounded-full bg-muted px-2.5 py-1 text-[0.6875rem] font-bold text-foreground">
                                                            {departureStatusLabels[departure.status] ??
                                                                departure.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="rounded-[1rem] border border-dashed border-border/60 bg-card p-12 text-center">
                                    <BusFront className="mx-auto size-10 text-muted-foreground/40" />
                                    <h4 className="mt-3 text-sm font-bold text-foreground">
                                        Aucun départ affecté
                                    </h4>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Ce bus n'a pas encore de départ programmé ou historique enregistré.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab: Historique */}
                    {tab === 'history' && (
                        <div className="space-y-4">
                            <div className="mb-4 border-b border-border/60 pb-4">
                                <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                    Journal du cycle de vie
                                </span>
                                <h3 className="text-[1.1875rem] font-bold text-foreground">
                                    Historique du véhicule
                                </h3>
                            </div>

                            <div className="relative space-y-4 border-l-2 border-border/60 pl-6 ml-2">
                                {timeline.map((event, index) => (
                                    <div key={index} className="relative">
                                        <span className="absolute -left-[31px] top-1 size-3 rounded-full border-2 border-card bg-gold" />
                                        <p className="text-sm font-bold text-foreground">
                                            {event.label}
                                        </p>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(event.date)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </section>

                {/* Aside Timeline / Résumé */}
                <aside className="space-y-4">
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-3 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Suivi opérationnel
                        </h3>
                        <div className="space-y-3 text-xs">
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">Immatriculation</span>
                                <span className="font-bold text-foreground">
                                    {bus.registrationNumber}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">Modèle</span>
                                <span className="font-medium text-foreground">
                                    {bus.modelName}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">Agence</span>
                                <span className="font-medium text-foreground">
                                    {bus.agencyName ?? 'Non rattaché'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">Places totales</span>
                                <span className="font-bold text-foreground">
                                    {bus.seatCount}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                <span className="text-muted-foreground">Statut</span>
                                <KoraStatusBadge
                                    label={statusLabels[bus.status] ?? bus.status}
                                    tone={statusTones[bus.status] ?? 'gold'}
                                />
                            </div>
                            <div className="flex items-center justify-between pt-1">
                                <span className="text-muted-foreground">Enregistré le</span>
                                <span className="text-foreground">
                                    {formatDate(bus.createdAt)}
                                </span>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            {/* Modal d'édition */}
            <BusFormDialog
                open={editDialog}
                bus={bus}
                statuses={statuses}
                agencies={agencies}
                models={models}
                compositions={compositions}
                onOpenChange={setEditDialog}
            />
        </>
    );
}

FleetDetailPage.layout = {
    breadcrumbs: [
        { title: 'Flotte', href: '/fleet' } satisfies BreadcrumbItem,
        { title: 'Détail bus', href: '' } satisfies BreadcrumbItem,
    ],
};

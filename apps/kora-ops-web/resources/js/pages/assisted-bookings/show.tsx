import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Clock3,
    HandHelping,
    History,
    RefreshCcw,
    ShieldCheck,
    UserRoundCheck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    claim,
    index,
    prepareSale,
    reassign,
    refuse,
    release,
} from '@/routes/assisted-bookings';

type Seat = {
    uuid: string;
    code: string;
    status: string;
    requestedBy: number[];
};
type Leg = {
    legOrder: number;
    label: string;
    departureAt: string;
    seats: Seat[];
};
type Passenger = {
    uuid: string;
    index: number;
    firstName: string;
    lastName: string;
    phone: string | null;
};
type Props = {
    assistedBooking: {
        uuid: string;
        status: string;
        reference: string;
        contactName: string;
        contactPhone: string;
        agency: { uuid: string; name: string };
        assignee: { uuid: string; name: string } | null;
        originalAmount: number;
        currentAmount: number | null;
        currency: string;
        expiresAt: string;
        snapshot: Record<string, unknown>;
        reasonCode: string | null;
        reasonNote: string | null;
        ticketIssuanceStatus: string;
        passengers: Passenger[];
        legs: Leg[];
        history: {
            event: string;
            actor: string | null;
            properties: Record<string, unknown>;
            createdAt: string;
        }[];
    };
    agencies: { uuid: string; name: string; city: string }[];
    agents: { uuid: string; name: string; agencyUuid: string | null }[];
    permissions: {
        canClaim: boolean;
        canRelease: boolean;
        canReassign: boolean;
        canRefuse: boolean;
        canPrepareSale: boolean;
    };
};

export default function AssistedBookingShow({
    assistedBooking: item,
    agencies,
    agents,
    permissions,
}: Props) {
    const initialSeats = useMemo(
        () =>
            Object.fromEntries(
                item.passengers.flatMap((passenger) =>
                    item.legs.map((leg) => {
                        const requested = leg.seats.find(
                            (seat) =>
                                seat.requestedBy.includes(passenger.index) &&
                                seat.status === 'available',
                        );

                        return [
                            `${passenger.index}-${leg.legOrder}`,
                            requested?.uuid ?? '',
                        ];
                    }),
                ),
            ),
        [item],
    );
    const [selections, setSelections] =
        useState<Record<string, string>>(initialSeats);
    const [reason, setReason] = useState('');
    const [reasonCode, setReasonCode] = useState('customer_request');
    const [agencyUuid, setAgencyUuid] = useState(item.agency.uuid);
    const [assigneeUuid, setAssigneeUuid] = useState(item.assignee?.uuid ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const post = (url: string, data: RequestPayload = {}) =>
        router.post(url, data, { preserveScroll: true, onError: setErrors });
    const prepare = () =>
        post(prepareSale.url(item.uuid), {
            passengers: item.passengers.map((passenger) => ({
                passenger_index: passenger.index,
                seat_assignments: item.legs.map((leg) => ({
                    leg_order: leg.legOrder,
                    seat_uuid: selections[`${passenger.index}-${leg.legOrder}`],
                })),
            })),
        });

    return (
        <>
            <Head title={`Assistance ${item.reference}`} />
            <Link
                href={index.url()}
                className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft className="size-4" />
                Retour à la file
            </Link>
            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div className="mb-2 text-xs font-black tracking-[0.12em] text-gold uppercase">
                        Réservation assistée
                    </div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        {item.reference}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {item.contactName} · {item.contactPhone} ·{' '}
                        {item.agency.name}
                    </p>
                </div>
                <div className="rounded-xl border bg-card px-4 py-3 text-sm">
                    <strong>{item.status.replaceAll('_', ' ')}</strong>
                    <span className="ml-3 text-muted-foreground">
                        <Clock3 className="mr-1 inline size-4" />
                        {new Date(item.expiresAt).toLocaleString('fr-FR')}
                    </span>
                </div>
            </div>

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(19rem,0.8fr)]">
                <div className="space-y-5">
                    <section className="rounded-2xl border bg-card p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-3">
                            <HandHelping className="size-5 text-gold" />
                            <h2 className="font-bold">Dossier et devis</h2>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div>
                                <span className="text-xs text-muted-foreground">
                                    Montant initial
                                </span>
                                <strong className="block text-lg">
                                    {item.originalAmount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    {item.currency}
                                </strong>
                            </div>
                            <div>
                                <span className="text-xs text-muted-foreground">
                                    Devis actuel
                                </span>
                                <strong className="block text-lg">
                                    {(
                                        item.currentAmount ??
                                        item.originalAmount
                                    ).toLocaleString('fr-FR')}{' '}
                                    {item.currency}
                                </strong>
                            </div>
                            <div>
                                <span className="text-xs text-muted-foreground">
                                    Attributaire
                                </span>
                                <strong className="block text-lg">
                                    {item.assignee?.name ?? 'Non attribuée'}
                                </strong>
                            </div>
                        </div>
                        {item.reasonNote && (
                            <p className="mt-4 rounded-lg bg-muted p-3 text-sm">
                                {item.reasonCode} — {item.reasonNote}
                            </p>
                        )}
                    </section>

                    <section className="rounded-2xl border bg-card p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-3">
                            <UserRoundCheck className="size-5 text-gold" />
                            <h2 className="font-bold">Sièges à revalider</h2>
                        </div>
                        <div className="space-y-5">
                            {item.passengers.map((passenger) => (
                                <div
                                    key={passenger.uuid}
                                    className="rounded-xl bg-muted/45 p-4"
                                >
                                    <strong>
                                        {passenger.firstName}{' '}
                                        {passenger.lastName}
                                    </strong>
                                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                                        {item.legs.map((leg) => (
                                            <div key={leg.legOrder}>
                                                <Label>{leg.label}</Label>
                                                <select
                                                    value={
                                                        selections[
                                                            `${passenger.index}-${leg.legOrder}`
                                                        ] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setSelections(
                                                            (current) => ({
                                                                ...current,
                                                                [`${passenger.index}-${leg.legOrder}`]:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    className="mt-1 h-11 w-full rounded-md border bg-background px-3 text-sm"
                                                >
                                                    <option value="">
                                                        Choisir un siège
                                                    </option>
                                                    {leg.seats
                                                        .filter(
                                                            (seat) =>
                                                                seat.status ===
                                                                'available',
                                                        )
                                                        .map((seat) => (
                                                            <option
                                                                key={seat.uuid}
                                                                value={
                                                                    seat.uuid
                                                                }
                                                            >
                                                                {seat.code}
                                                                {seat.requestedBy.includes(
                                                                    passenger.index,
                                                                )
                                                                    ? ' — souhaité'
                                                                    : ''}
                                                            </option>
                                                        ))}
                                                </select>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <InputError
                            message={errors.passengers ?? errors.seats}
                        />
                        {permissions.canPrepareSale && (
                            <Button
                                onClick={prepare}
                                className="mt-5 bg-gold text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                            >
                                <ShieldCheck className="size-4" />
                                Reprendre dans le guichet
                            </Button>
                        )}
                    </section>

                    <section className="rounded-2xl border bg-card p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-3">
                            <History className="size-5 text-gold" />
                            <h2 className="font-bold">Historique immuable</h2>
                        </div>
                        <div className="space-y-3">
                            {item.history.map((entry, indexEntry) => (
                                <div
                                    key={`${entry.event}-${indexEntry}`}
                                    className="border-l-2 border-gold pl-4"
                                >
                                    <strong className="text-sm">
                                        {entry.event.replaceAll('_', ' ')}
                                    </strong>
                                    <p className="text-xs text-muted-foreground">
                                        {entry.actor ?? 'Système'} ·{' '}
                                        {new Date(
                                            entry.createdAt,
                                        ).toLocaleString('fr-FR')}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                <aside className="space-y-5">
                    {permissions.canClaim && (
                        <section className="rounded-2xl border bg-card p-5">
                            <h2 className="font-bold">Prendre en charge</h2>
                            <p className="my-3 text-sm text-muted-foreground">
                                Le devis est recalculé, sans bloquer de siège.
                            </p>
                            <Button
                                onClick={() => post(claim.url(item.uuid))}
                                className="w-full bg-[var(--kora)]"
                            >
                                Me l’attribuer
                            </Button>
                        </section>
                    )}
                    {(permissions.canRelease || permissions.canRefuse) && (
                        <section className="rounded-2xl border bg-card p-5">
                            <h2 className="font-bold">Décision</h2>
                            <Label className="mt-3 block" htmlFor="reason">
                                Motif obligatoire
                            </Label>
                            <Input
                                id="reason"
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                className="mt-1"
                            />
                            <InputError
                                message={errors.reason ?? errors.reason_note}
                            />
                            {permissions.canRelease && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        post(release.url(item.uuid), { reason })
                                    }
                                    className="mt-3 w-full"
                                >
                                    <RefreshCcw className="size-4" />
                                    Libérer le dossier
                                </Button>
                            )}
                            {permissions.canRefuse && (
                                <>
                                    <select
                                        value={reasonCode}
                                        onChange={(event) =>
                                            setReasonCode(event.target.value)
                                        }
                                        className="mt-3 h-10 w-full rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="customer_request">
                                            Demande du client
                                        </option>
                                        <option value="payment_not_received">
                                            Paiement non reçu
                                        </option>
                                        <option value="fraud_risk">
                                            Risque de fraude
                                        </option>
                                        <option value="other">Autre</option>
                                    </select>
                                    <Button
                                        variant="destructive"
                                        onClick={() =>
                                            post(refuse.url(item.uuid), {
                                                reason_code: reasonCode,
                                                reason_note: reason,
                                            })
                                        }
                                        className="mt-3 w-full"
                                    >
                                        Refuser et clôturer
                                    </Button>
                                </>
                            )}
                        </section>
                    )}
                    {permissions.canReassign && (
                        <section className="rounded-2xl border bg-card p-5">
                            <h2 className="font-bold">Réaffecter</h2>
                            <Label className="mt-3 block">Agence</Label>
                            <select
                                value={agencyUuid}
                                onChange={(event) => {
                                    setAgencyUuid(event.target.value);
                                    setAssigneeUuid('');
                                }}
                                className="mt-1 h-10 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                {agencies.map((agency) => (
                                    <option
                                        key={agency.uuid}
                                        value={agency.uuid}
                                    >
                                        {agency.name} — {agency.city}
                                    </option>
                                ))}
                            </select>
                            <Label className="mt-3 block">
                                Agent (facultatif)
                            </Label>
                            <select
                                value={assigneeUuid}
                                onChange={(event) =>
                                    setAssigneeUuid(event.target.value)
                                }
                                className="mt-1 h-10 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Retour dans la file</option>
                                {agents
                                    .filter(
                                        (agent) =>
                                            agent.agencyUuid === agencyUuid,
                                    )
                                    .map((agent) => (
                                        <option
                                            key={agent.uuid}
                                            value={agent.uuid}
                                        >
                                            {agent.name}
                                        </option>
                                    ))}
                            </select>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    post(reassign.url(item.uuid), {
                                        agency_uuid: agencyUuid,
                                        assignee_uuid: assigneeUuid || null,
                                        reason,
                                    })
                                }
                                className="mt-3 w-full"
                            >
                                Confirmer l’affectation
                            </Button>
                        </section>
                    )}
                </aside>
            </div>
        </>
    );
}

import { Loader2, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    ParcelAssignmentDeparture,
    ParcelAssignmentOptions,
    ParcelAssignmentOptionsResponse,
    ParcelListItem,
} from '@/components/parcels/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    parcel: ParcelListItem | null;
    onOpenChange: (open: boolean) => void;
    onAssigned: () => void;
};

function xsrfToken(): string {
    const value = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    return decodeURIComponent(value);
}

function responseMessage(payload: unknown, fallback: string): string {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'message' in payload &&
        typeof payload.message === 'string'
    ) {
        return payload.message;
    }

    return fallback;
}

function formatGrams(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

function formatDate(value: string): string {
    const parsed = new Date(`${value}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
    }).format(parsed);
}

function eligibilityLabel(departure: ParcelAssignmentDeparture): string {
    if (departure.eligible) {
        return 'Capacité disponible';
    }

    if (departure.reason === 'capacity_not_configured') {
        return 'Soute non configurée';
    }

    return 'Soute insuffisante';
}

export function ParcelAssignmentDialog({
    open,
    parcel,
    onOpenChange,
    onAssigned,
}: Props) {
    const [options, setOptions] = useState<ParcelAssignmentOptions | null>(
        null,
    );
    const [selected, setSelected] = useState<string>('');
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open || parcel === null) {
            return;
        }

        let cancelled = false;

        // Reset dialog state when a new parcel is opened; external fetch follows.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setLoading(true);
        setError(null);
        setOptions(null);
        setSelected('');

        const load = async () => {
            try {
                const response = await fetch(
                    `/parcels/data/${parcel.uuid}/assignment-options`,
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    },
                );
                const payload = (await response.json().catch(() => null)) as
                    | ParcelAssignmentOptionsResponse
                    | { message?: string }
                    | null;

                if (!response.ok) {
                    throw new Error(
                        responseMessage(
                            payload,
                            'Impossible de charger les départs compatibles.',
                        ),
                    );
                }

                if (cancelled) {
                    return;
                }

                const data = (payload as ParcelAssignmentOptionsResponse).data;
                setOptions(data);
                const current = data.departures.find(
                    (departure) => departure.is_current && departure.eligible,
                );
                const firstEligible = data.departures.find(
                    (departure) => departure.eligible,
                );
                setSelected(
                    current?.departure_uuid ??
                        firstEligible?.departure_uuid ??
                        '',
                );
            } catch (reason) {
                if (!cancelled) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Erreur de chargement des départs.',
                    );
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [open, parcel]);

    const confirm = async () => {
        if (parcel === null || selected === '') {
            return;
        }

        setSubmitting(true);
        setError(null);

        try {
            const response = await fetch(
                `/parcels/data/${parcel.uuid}/assign`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({ departure_uuid: selected }),
                },
            );
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(
                    responseMessage(
                        payload,
                        'Affectation impossible pour ce départ.',
                    ),
                );
            }

            toast.success('Colis affecté au départ.');
            onOpenChange(false);
            onAssigned();
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Erreur lors de l’affectation.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    const selectedDeparture = options?.departures.find(
        (departure) => departure.departure_uuid === selected,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[86vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Truck className="size-4 text-kora" />
                        Affecter au départ Bus
                    </DialogTitle>
                    <DialogDescription>
                        {parcel?.reference ?? '—'} ·{' '}
                        {formatGrams(options?.required_grams ?? null)} à
                        réserver en soute. Seuls les départs réels compatibles
                        avec le trajet et la capacité sont retenus.
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        Calcul des capacités de soute…
                    </div>
                ) : error !== null && options === null ? (
                    <div className="rounded-xl border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {error}
                    </div>
                ) : options === null || options.departures.length === 0 ? (
                    <div className="rounded-xl border border-black/[0.06] bg-muted/30 px-4 py-5 text-sm text-muted-foreground">
                        Aucun départ Bus compatible dans l’horizon de
                        planification. Vérifiez la ligne, les arrêts et les
                        départs programmés.
                    </div>
                ) : (
                    <div className="space-y-2">
                        {options.departures.map((departure) => (
                            <button
                                key={departure.departure_uuid}
                                type="button"
                                disabled={!departure.eligible}
                                onClick={() =>
                                    setSelected(departure.departure_uuid)
                                }
                                className={[
                                    'flex w-full items-start justify-between gap-4 rounded-xl border px-4 py-3 text-left transition-colors',
                                    departure.eligible
                                        ? 'cursor-pointer border-black/[0.08] hover:border-kora/40 hover:bg-kora/[0.03]'
                                        : 'cursor-not-allowed border-black/[0.04] bg-muted/20 opacity-60',
                                    selected === departure.departure_uuid
                                        ? 'border-kora/60 bg-kora/[0.05]'
                                        : '',
                                ].join(' ')}
                            >
                                <span className="min-w-0">
                                    <strong className="block text-sm font-bold text-foreground">
                                        {formatDate(departure.departure_date)} ·{' '}
                                        {departure.departure_time}
                                    </strong>
                                    <small className="mt-0.5 block text-xs text-muted-foreground">
                                        {departure.line_code ?? '—'} ·{' '}
                                        {departure.origin_stop ?? '—'} →{' '}
                                        {departure.destination_stop ?? '—'}
                                        {departure.bus_registration
                                            ? ` · Bus ${departure.bus_registration}`
                                            : ''}
                                    </small>
                                    {departure.is_current && (
                                        <small className="mt-1 block text-[0.6875rem] font-bold text-kora">
                                            Affectation actuelle
                                        </small>
                                    )}
                                </span>
                                <span className="shrink-0 text-right">
                                    <strong className="block text-xs font-bold text-foreground">
                                        {formatGrams(departure.remaining_grams)}{' '}
                                        libres
                                    </strong>
                                    <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                        {eligibilityLabel(departure)}
                                    </small>
                                </span>
                            </button>
                        ))}
                    </div>
                )}

                {error !== null && options !== null && (
                    <div className="rounded-xl border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {selectedDeparture !== undefined && (
                    <div className="rounded-xl border border-kora/15 bg-kora/[0.04] px-4 py-3 text-xs text-muted-foreground">
                        Réservation :{' '}
                        <strong className="text-foreground">
                            {formatGrams(selectedDeparture.required_grams)}
                        </strong>{' '}
                        sur{' '}
                        <strong className="text-foreground">
                            {formatGrams(selectedDeparture.remaining_grams)}
                        </strong>{' '}
                        disponibles. La capacité sera verrouillée au moment de
                        l’affectation.
                    </div>
                )}

                <DialogFooter>
                    <ParcelButton
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                    >
                        Annuler
                    </ParcelButton>
                    <ParcelButton
                        variant="primary"
                        disabled={
                            submitting ||
                            loading ||
                            selected === '' ||
                            selectedDeparture?.eligible !== true
                        }
                        onClick={() => void confirm()}
                    >
                        {submitting ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <Truck className="size-3.5" />
                        )}
                        Confirmer l’affectation
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

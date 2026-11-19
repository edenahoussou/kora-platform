import {
    ArrowRight,
    Loader2,
    MapPin,
    Power,
    Route,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    ParcelFieldLabel,
    ParcelRequiredNote,
} from '@/components/parcels/field-label';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    CorridorPayload,
    DepositHub,
    LaravelValidationErrors,
    ParcelCorridor,
    StopOption,
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
    onOpenChange: (open: boolean) => void;
    corridor: ParcelCorridor | null;
    hubs: DepositHub[];
    stops: StopOption[];
    busy: boolean;
    generalError: string | null;
    validationErrors: LaravelValidationErrors;
    onSubmit: (payload: CorridorPayload) => Promise<void>;
};

function firstError(
    errors: LaravelValidationErrors,
    key: string,
): string | undefined {
    return errors[key]?.[0];
}

export function CorridorDialog({
    open,
    onOpenChange,
    corridor,
    hubs,
    stops,
    busy,
    generalError,
    validationErrors,
    onSubmit,
}: Props) {
    const [originUuid, setOriginUuid] = useState('');
    const [destinationUuid, setDestinationUuid] = useState('');
    const [enabled, setEnabled] = useState(false);
    const [walletCashEnabled, setWalletCashEnabled] = useState(false);

    const stopsByUuid = useMemo(
        () => new Map(stops.map((stop) => [stop.uuid, stop])),
        [stops],
    );

    const activeHubs = useMemo(
        () => hubs.filter((hub) => hub.enabled && hub.archived_at === null),
        [hubs],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect
        setOriginUuid(corridor?.origin_hub_uuid ?? '');
        setDestinationUuid(corridor?.destination_hub_uuid ?? '');
        setEnabled(corridor?.enabled ?? false);
        setWalletCashEnabled(corridor?.wallet_cash_enabled ?? false);
    }, [corridor, open]);

    const originHub = activeHubs.find((hub) => hub.uuid === originUuid);
    const originStop = originHub?.stop_uuid
        ? stopsByUuid.get(originHub.stop_uuid)
        : undefined;

    const destinationOptions = useMemo(() => {
        if (!originHub || !originStop) {
            return activeHubs.filter((hub) => hub.uuid !== originUuid);
        }

        return activeHubs.filter((hub) => {
            if (hub.uuid === originUuid || !hub.stop_uuid) {
                return false;
            }

            const stop = stopsByUuid.get(hub.stop_uuid);

            return (
                stop !== undefined &&
                stop.line_uuid === originStop.line_uuid &&
                stop.stop_order > originStop.stop_order
            );
        });
    }, [activeHubs, originHub, originStop, originUuid, stopsByUuid]);

    const destinationHub = destinationOptions.find(
        (hub) => hub.uuid === destinationUuid,
    );

    const canSubmit =
        !busy &&
        originUuid !== '' &&
        destinationUuid !== '' &&
        destinationHub !== undefined;

    const submit = async () => {
        if (!canSubmit) {
            return;
        }

        await onSubmit({
            origin_hub_uuid: originUuid,
            destination_hub_uuid: destinationUuid,
            enabled,
            wallet_cash_enabled: walletCashEnabled,
        });
    };

    const editing = corridor !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-[42rem]">
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? 'Configurer le corridor'
                            : 'Créer un corridor Colis'}
                    </DialogTitle>
                    <DialogDescription>
                        Un corridor relie un lieu de dépôt à une destination
                        desservie dans le bon ordre. Il doit disposer d’un tarif
                        publié avant d’être activé.
                    </DialogDescription>
                    <ParcelRequiredNote />
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="field">
                        <ParcelFieldLabel
                            htmlFor="corridor-origin"
                            icon={MapPin}
                            required
                            hint="Point de départ du corridor, relié à un arrêt de ligne actif."
                        >
                            Lieu de dépôt d’origine
                        </ParcelFieldLabel>
                        <select
                            id="corridor-origin"
                            value={originUuid}
                            onChange={(event) => {
                                setOriginUuid(event.target.value);
                                setDestinationUuid('');
                            }}
                            className="control"
                            disabled={editing}
                            aria-required="true"
                        >
                            <option value="">Sélectionner une origine</option>
                            {activeHubs.map((hub) => {
                                const stop = hub.stop_uuid
                                    ? stopsByUuid.get(hub.stop_uuid)
                                    : undefined;

                                return (
                                    <option key={hub.uuid} value={hub.uuid}>
                                        {hub.name ?? 'Lieu sans nom'} ·{' '}
                                        {hub.city ?? '—'}
                                        {stop?.line_code
                                            ? ` · ${stop.line_code}`
                                            : ''}
                                    </option>
                                );
                            })}
                        </select>
                        {firstError(validationErrors, 'origin_hub_uuid') && (
                            <p className="field-error-text">
                                {firstError(
                                    validationErrors,
                                    'origin_hub_uuid',
                                )}
                            </p>
                        )}
                    </div>

                    <div className="flex justify-center text-muted-foreground">
                        <ArrowRight className="size-4" />
                    </div>

                    <div className="field">
                        <ParcelFieldLabel
                            htmlFor="corridor-destination"
                            icon={Route}
                            required
                            hint="Arrêt situé après l’origine sur la même ligne, dans le sens du trajet."
                        >
                            Destination
                        </ParcelFieldLabel>
                        <select
                            id="corridor-destination"
                            value={destinationUuid}
                            onChange={(event) =>
                                setDestinationUuid(event.target.value)
                            }
                            className="control"
                            disabled={editing || originUuid === ''}
                            aria-required="true"
                        >
                            <option value="">
                                Sélectionner une destination
                            </option>
                            {destinationOptions.map((hub) => {
                                const stop = hub.stop_uuid
                                    ? stopsByUuid.get(hub.stop_uuid)
                                    : undefined;

                                return (
                                    <option key={hub.uuid} value={hub.uuid}>
                                        {hub.name ?? 'Lieu sans nom'} ·{' '}
                                        {hub.city ?? '—'} ·{' '}
                                        {stop?.name ?? 'Arrêt non renseigné'}
                                    </option>
                                );
                            })}
                        </select>
                        {firstError(
                            validationErrors,
                            'destination_hub_uuid',
                        ) && (
                            <p className="field-error-text">
                                {firstError(
                                    validationErrors,
                                    'destination_hub_uuid',
                                )}
                            </p>
                        )}
                        {originUuid !== '' &&
                            destinationOptions.length === 0 && (
                                <p className="text-xs text-amber-700">
                                    Aucun lieu actif ne suit cette origine sur
                                    la même ligne.
                                </p>
                            )}
                    </div>

                    <div className="grid gap-2 rounded-2xl border border-border bg-muted/25 p-4">
                        <label className="flex items-center gap-2 text-sm">
                            <Power className="size-3.5 shrink-0 text-gold" />
                            <input
                                type="checkbox"
                                checked={enabled}
                                onChange={(event) =>
                                    setEnabled(event.target.checked)
                                }
                                className="size-4 accent-[var(--kora)]"
                            />
                            Activer le corridor dès l’enregistrement
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Wallet className="size-3.5 shrink-0 text-gold" />
                            <input
                                type="checkbox"
                                checked={walletCashEnabled}
                                onChange={(event) =>
                                    setWalletCashEnabled(event.target.checked)
                                }
                                className="size-4 accent-[var(--kora)]"
                            />
                            Autoriser le paiement Wallet et espèces au guichet
                        </label>
                        <p className="text-[0.6875rem] leading-5 text-muted-foreground">
                            L’activation est refusée tant qu’aucun tarif n’est
                            publié pour ce corridor.
                        </p>
                    </div>
                </div>

                {generalError && (
                    <div
                        role="alert"
                        className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        {generalError}
                    </div>
                )}

                <DialogFooter>
                    <ParcelButton
                        type="button"
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={busy}
                    >
                        Annuler
                    </ParcelButton>
                    <ParcelButton
                        type="button"
                        variant="primary"
                        onClick={() => void submit()}
                        disabled={!canSubmit}
                    >
                        {busy ? (
                            <>
                                <Loader2 className="size-4 animate-spin" />
                                Enregistrement…
                            </>
                        ) : (
                            'Enregistrer le corridor'
                        )}
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

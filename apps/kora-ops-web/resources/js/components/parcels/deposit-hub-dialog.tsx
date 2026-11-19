import {
    Building2,
    CheckCircle2,
    Loader2,
    MapPin,
    Power,
    Store,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    ParcelFieldLabel,
    ParcelRequiredNote,
} from '@/components/parcels/field-label';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    DepositHubKind,
    DepositHubPayload,
    InternalAgencyOption,
    LaravelValidationErrors,
    PartnerPointOption,
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
import { cn } from '@/lib/utils';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    internalAgencies: InternalAgencyOption[];
    partnerPoints: PartnerPointOption[];
    stops: StopOption[];
    linkedAgencyUuids: string[];
    linkedPartnerPointUuids: string[];
    busy: boolean;
    generalError: string | null;
    validationErrors: LaravelValidationErrors;
    onSubmit: (payload: DepositHubPayload) => Promise<void>;
};

function firstError(
    errors: LaravelValidationErrors,
    key: string,
): string | undefined {
    return errors[key]?.[0];
}

export function DepositHubDialog({
    open,
    onOpenChange,
    internalAgencies,
    partnerPoints,
    stops,
    linkedAgencyUuids,
    linkedPartnerPointUuids,
    busy,
    generalError,
    validationErrors,
    onSubmit,
}: Props) {
    const [kind, setKind] = useState<DepositHubKind>('agency');
    const [agencyUuid, setAgencyUuid] = useState('');
    const [partnerPointUuid, setPartnerPointUuid] = useState('');
    const [stopUuid, setStopUuid] = useState('');
    const [enabled, setEnabled] = useState(true);

    const linkedAgencies = useMemo(
        () => new Set(linkedAgencyUuids),
        [linkedAgencyUuids],
    );

    const linkedPartnerPoints = useMemo(
        () => new Set(linkedPartnerPointUuids),
        [linkedPartnerPointUuids],
    );

    const availableAgencies = useMemo(
        () =>
            internalAgencies.filter(
                (agency) => !linkedAgencies.has(agency.uuid),
            ),
        [internalAgencies, linkedAgencies],
    );

    const availablePartnerPoints = useMemo(
        () =>
            partnerPoints.filter(
                (point) => !linkedPartnerPoints.has(point.uuid),
            ),
        [linkedPartnerPoints, partnerPoints],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect
        setKind(availableAgencies.length > 0 ? 'agency' : 'partner_point');
        setAgencyUuid('');
        setPartnerPointUuid('');
        setStopUuid('');
        setEnabled(true);
    }, [open, availableAgencies.length]);

    const selectedAgency = useMemo(
        () => internalAgencies.find((agency) => agency.uuid === agencyUuid),
        [agencyUuid, internalAgencies],
    );

    const selectedPartnerPoint = useMemo(
        () => partnerPoints.find((point) => point.uuid === partnerPointUuid),
        [partnerPointUuid, partnerPoints],
    );

    const selectedCountry =
        kind === 'agency'
            ? (selectedAgency?.country ?? null)
            : (selectedPartnerPoint?.agency_country ?? null);

    const eligibleStops = useMemo(() => {
        if (!selectedCountry) {
            return stops;
        }

        return stops.filter(
            (stop) =>
                stop.country?.toUpperCase() === selectedCountry.toUpperCase(),
        );
    }, [selectedCountry, stops]);

    const selectedStop = useMemo(
        () => eligibleStops.find((stop) => stop.uuid === stopUuid),
        [eligibleStops, stopUuid],
    );

    const selectedStopIsEligible = eligibleStops.some(
        (stop) => stop.uuid === stopUuid,
    );

    const canSubmit =
        !busy &&
        stopUuid !== '' &&
        selectedStopIsEligible &&
        (kind === 'agency'
            ? agencyUuid !== '' && !linkedAgencies.has(agencyUuid)
            : partnerPointUuid !== '' &&
              !linkedPartnerPoints.has(partnerPointUuid));

    const submit = async () => {
        if (!canSubmit) {
            return;
        }

        await onSubmit({
            ...(kind === 'agency'
                ? { agency_uuid: agencyUuid }
                : { kora_point_uuid: partnerPointUuid }),
            stop_uuid: stopUuid,
            enabled,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-[44rem]">
                <DialogHeader>
                    <DialogTitle>Ajouter un lieu de dépôt</DialogTitle>
                    <DialogDescription>
                        Reliez une entité commerciale à un arrêt physique de
                        ligne. Le lieu devient éligible aux corridors Colis.
                    </DialogDescription>
                    <ParcelRequiredNote />
                </DialogHeader>

                <div className="grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        onClick={() => {
                            setKind('agency');
                            setPartnerPointUuid('');
                            setStopUuid('');
                        }}
                        className={cn(
                            'rounded-2xl border p-4 text-left transition',
                            kind === 'agency'
                                ? 'border-kora bg-kora text-white shadow-[0_12px_30px_rgba(0,29,78,0.18)]'
                                : 'border-border bg-card hover:border-gold/60',
                        )}
                    >
                        <span
                            className={cn(
                                'mb-3 grid size-10 place-items-center rounded-xl',
                                kind === 'agency'
                                    ? 'bg-white/10 text-gold'
                                    : 'bg-gold/10 text-gold',
                            )}
                        >
                            <Building2 className="size-5" />
                        </span>
                        <strong className="block text-sm">
                            Agence interne
                        </strong>
                        <small
                            className={cn(
                                'mt-1 block text-xs',
                                kind === 'agency'
                                    ? 'text-white/65'
                                    : 'text-muted-foreground',
                            )}
                        >
                            L’agence KORA EXPRESS est l’entité commerciale et le
                            lieu physique.
                        </small>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setKind('partner_point');
                            setAgencyUuid('');
                            setStopUuid('');
                        }}
                        className={cn(
                            'rounded-2xl border p-4 text-left transition',
                            kind === 'partner_point'
                                ? 'border-kora bg-kora text-white shadow-[0_12px_30px_rgba(0,29,78,0.18)]'
                                : 'border-border bg-card hover:border-gold/60',
                        )}
                    >
                        <span
                            className={cn(
                                'mb-3 grid size-10 place-items-center rounded-xl',
                                kind === 'partner_point'
                                    ? 'bg-white/10 text-gold'
                                    : 'bg-gold/10 text-gold',
                            )}
                        >
                            <Store className="size-5" />
                        </span>
                        <strong className="block text-sm">
                            Point partenaire
                        </strong>
                        <small
                            className={cn(
                                'mt-1 block text-xs',
                                kind === 'partner_point'
                                    ? 'text-white/65'
                                    : 'text-muted-foreground',
                            )}
                        >
                            Une agence partenaire active porte le KORA POINT
                            utilisé comme lieu de dépôt.
                        </small>
                    </button>
                </div>

                {kind === 'agency' && availableAgencies.length === 0 && (
                    <div className="rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Aucune agence interne active n’est disponible. Les
                        agences déjà rattachées à un lieu de dépôt sont retirées
                        de la liste des options.
                    </div>
                )}

                {kind === 'partner_point' &&
                    availablePartnerPoints.length === 0 && (
                        <div className="rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Aucun point partenaire éligible. Le point doit être
                            actif, rattaché à une agence partenaire active et ne
                            pas déjà disposer d’un lieu de dépôt.
                        </div>
                    )}

                <div className="grid gap-4">
                    {kind === 'agency' ? (
                        <div className="field">
                            <ParcelFieldLabel
                                htmlFor="deposit-agency"
                                icon={Building2}
                                required
                                hint="Seules les agences actives non encore rattachées sont proposées."
                            >
                                Agence interne
                            </ParcelFieldLabel>
                            <select
                                id="deposit-agency"
                                value={agencyUuid}
                                onChange={(event) =>
                                    setAgencyUuid(event.target.value)
                                }
                                className="control"
                                disabled={availableAgencies.length === 0}
                                aria-required="true"
                            >
                                <option value="">
                                    Sélectionner une agence
                                </option>
                                {availableAgencies.map((agency) => (
                                    <option
                                        key={agency.uuid}
                                        value={agency.uuid}
                                    >
                                        {agency.name} · {agency.city ?? '—'} ·{' '}
                                        {agency.country ?? '—'}
                                    </option>
                                ))}
                            </select>
                            {firstError(validationErrors, 'agency_uuid') && (
                                <p className="field-error-text">
                                    {firstError(
                                        validationErrors,
                                        'agency_uuid',
                                    )}
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="field">
                            <ParcelFieldLabel
                                htmlFor="deposit-partner-point"
                                icon={Store}
                                required
                                hint="Seuls les points actifs sans lieu de dépôt existant sont proposés."
                            >
                                Point partenaire
                            </ParcelFieldLabel>
                            <select
                                id="deposit-partner-point"
                                value={partnerPointUuid}
                                onChange={(event) =>
                                    setPartnerPointUuid(event.target.value)
                                }
                                className="control"
                                disabled={availablePartnerPoints.length === 0}
                                aria-required="true"
                            >
                                <option value="">Sélectionner un point</option>
                                {availablePartnerPoints.map((point) => (
                                    <option key={point.uuid} value={point.uuid}>
                                        {point.agency_name ?? 'Partenaire'} ·{' '}
                                        {point.address ??
                                            'Adresse non renseignée'}
                                    </option>
                                ))}
                            </select>
                            {firstError(
                                validationErrors,
                                'kora_point_uuid',
                            ) && (
                                <p className="field-error-text">
                                    {firstError(
                                        validationErrors,
                                        'kora_point_uuid',
                                    )}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="field">
                        <ParcelFieldLabel
                            htmlFor="deposit-stop"
                            icon={MapPin}
                            required
                            hint="Arrêt physique actif de la ligne, dans le pays du lieu de dépôt."
                        >
                            Arrêt de chargement
                        </ParcelFieldLabel>
                        <select
                            id="deposit-stop"
                            value={stopUuid}
                            onChange={(event) =>
                                setStopUuid(event.target.value)
                            }
                            className="control"
                            disabled={eligibleStops.length === 0}
                            aria-required="true"
                        >
                            <option value="">Sélectionner un arrêt</option>
                            {eligibleStops.map((stop) => (
                                <option key={stop.uuid} value={stop.uuid}>
                                    {stop.line_code ?? '—'} · {stop.name} ·{' '}
                                    {stop.city ?? '—'} · {stop.country ?? '—'}
                                </option>
                            ))}
                        </select>
                        {firstError(validationErrors, 'stop_uuid') && (
                            <p className="field-error-text">
                                {firstError(validationErrors, 'stop_uuid')}
                            </p>
                        )}
                        {selectedCountry && eligibleStops.length === 0 && (
                            <p className="text-xs text-amber-700">
                                Aucun arrêt actif dans le pays de ce lieu.
                            </p>
                        )}
                    </div>

                    <label className="flex items-center gap-2 rounded-xl border border-border bg-muted/30 px-3 py-2.5 text-sm">
                        <Power className="size-3.5 shrink-0 text-gold" />
                        <input
                            type="checkbox"
                            checked={enabled}
                            onChange={(event) =>
                                setEnabled(event.target.checked)
                            }
                            className="size-4 accent-[var(--kora)]"
                        />
                        Activer ce lieu dès l’enregistrement
                    </label>
                </div>

                <div className="rounded-2xl border border-border bg-muted/25 p-4">
                    <div className="mb-3 flex items-center gap-2 text-[0.6875rem] font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                        <MapPin className="size-3.5 text-gold" />
                        Vérification du rattachement
                    </div>
                    <ul className="grid gap-2 text-sm">
                        <li className="flex items-center gap-2">
                            <CheckCircle2
                                className={cn(
                                    'size-4',
                                    selectedAgency || selectedPartnerPoint
                                        ? 'text-emerald-600'
                                        : 'text-muted-foreground/40',
                                )}
                            />
                            Entité commerciale sélectionnée
                        </li>
                        <li className="flex items-center gap-2">
                            <CheckCircle2
                                className={cn(
                                    'size-4',
                                    selectedStop
                                        ? 'text-emerald-600'
                                        : 'text-muted-foreground/40',
                                )}
                            />
                            Arrêt actif rattaché à une ligne active
                        </li>
                        <li className="flex items-center gap-2">
                            <CheckCircle2
                                className={cn(
                                    'size-4',
                                    selectedCountry && selectedStop
                                        ? 'text-emerald-600'
                                        : 'text-muted-foreground/40',
                                )}
                            />
                            Pays du lieu et de l’arrêt identiques
                        </li>
                    </ul>
                    {selectedStop && (
                        <p className="mt-3 text-xs text-muted-foreground">
                            Ligne : {selectedStop.line_code ?? '—'} ·{' '}
                            {selectedStop.line_name ?? 'Ligne non renseignée'}
                        </p>
                    )}
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
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                Enregistrement…
                            </>
                        ) : (
                            'Enregistrer le lieu'
                        )}
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

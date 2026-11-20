import {
    ArrowRight,
    CheckCircle2,
    Coins,
    FileText,
    Loader2,
    MapPin,
    Package,
    Phone,
    Route,
    User,
    UserRoundCheck,
    Weight,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useMemo, useState } from 'react';
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import {
    ParcelFieldLabel,
    ParcelRequiredNote,
} from '@/components/parcels/field-label';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    DepositHub,
    LaravelValidationErrors,
    ParcelCorridor,
    ParcelIntakePayload,
    ParcelIntakeResult,
    ParcelQuote,
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
    hubs: DepositHub[];
    corridors: ParcelCorridor[];
    stops: StopOption[];
    busy: boolean;
    generalError: string | null;
    validationErrors: LaravelValidationErrors;
    onCreate: (
        payload: ParcelIntakePayload,
    ) => Promise<ParcelIntakeResult | null>;
    onQuote: (payload: {
        deposit_hub_uuid: string;
        destination_hub_uuid: string;
        weight_grams: number;
    }) => Promise<ParcelQuote | null>;
};

function firstError(
    errors: LaravelValidationErrors,
    key: string,
): string | undefined {
    return errors[key]?.[0];
}

function newIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}

function formatAmount(value: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency}`;
}

function fieldError(
    errors: LaravelValidationErrors,
    key: string,
): string | undefined {
    return firstError(errors, key);
}

export function ParcelIntakeDialog({
    open,
    onOpenChange,
    hubs,
    corridors,
    stops,
    busy,
    generalError,
    validationErrors,
    onCreate,
    onQuote,
}: Props) {
    const [depositHubUuid, setDepositHubUuid] = useState('');
    const [destinationHubUuid, setDestinationHubUuid] = useState('');
    const [senderName, setSenderName] = useState('');
    const [senderPhone, setSenderPhone] = useState('');
    const [recipientName, setRecipientName] = useState('');
    const [recipientPhone, setRecipientPhone] = useState('');
    const [contents, setContents] = useState('');
    const [weight, setWeight] = useState('');
    const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);
    const [created, setCreated] = useState<ParcelIntakeResult | null>(null);
    const [quote, setQuote] = useState<ParcelQuote | null>(null);
    const [quoteUnavailable, setQuoteUnavailable] = useState(false);

    const stopsByUuid = useMemo(
        () => new Map(stops.map((stop) => [stop.uuid, stop])),
        [stops],
    );

    const activeHubs = useMemo(
        () => hubs.filter((hub) => hub.enabled && hub.archived_at === null),
        [hubs],
    );

    const hubsByUuid = useMemo(
        () => new Map(activeHubs.map((hub) => [hub.uuid, hub])),
        [activeHubs],
    );

    const openCorridors = useMemo(
        () =>
            corridors.filter(
                (corridor) =>
                    corridor.enabled &&
                    corridor.origin_hub_uuid !== null &&
                    corridor.destination_hub_uuid !== null &&
                    hubsByUuid.has(corridor.origin_hub_uuid) &&
                    hubsByUuid.has(corridor.destination_hub_uuid),
            ),
        [corridors, hubsByUuid],
    );

    const depositHubs = useMemo(() => {
        const uuids = new Set(
            openCorridors
                .map((corridor) => corridor.origin_hub_uuid)
                .filter((uuid): uuid is string => uuid !== null),
        );

        return activeHubs.filter((hub) => uuids.has(hub.uuid));
    }, [activeHubs, openCorridors]);

    const destinationCorridors = useMemo(
        () =>
            openCorridors.filter(
                (corridor) => corridor.origin_hub_uuid === depositHubUuid,
            ),
        [depositHubUuid, openCorridors],
    );

    const destinationHubs = useMemo(() => {
        return destinationCorridors
            .map((corridor) =>
                corridor.destination_hub_uuid === null
                    ? undefined
                    : hubsByUuid.get(corridor.destination_hub_uuid),
            )
            .filter((hub): hub is DepositHub => hub !== undefined);
    }, [destinationCorridors, hubsByUuid]);

    const selectedCorridor = destinationCorridors.find(
        (corridor) => corridor.destination_hub_uuid === destinationHubUuid,
    );

    const selectedDestination = destinationHubUuid
        ? hubsByUuid.get(destinationHubUuid)
        : undefined;

    const selectedDeposit = depositHubUuid
        ? hubsByUuid.get(depositHubUuid)
        : undefined;

    const maximumWeight =
        selectedCorridor?.tariffs[0]?.maximum_grams ?? 1_000_000;
    const weightValue = /^\d+$/.test(weight.trim()) ? Number(weight) : null;
    const senderPhoneValid = isValidPhoneNumber(senderPhone);
    const recipientPhoneValid = isValidPhoneNumber(recipientPhone);

    const canSubmit =
        !busy &&
        depositHubUuid !== '' &&
        destinationHubUuid !== '' &&
        senderName.trim() !== '' &&
        senderPhoneValid &&
        recipientName.trim() !== '' &&
        recipientPhoneValid &&
        contents.trim() !== '' &&
        weightValue !== null &&
        weightValue >= 1 &&
        weightValue <= maximumWeight;

    const reset = () => {
        setDepositHubUuid('');
        setDestinationHubUuid('');
        setSenderName('');
        setSenderPhone('');
        setRecipientName('');
        setRecipientPhone('');
        setContents('');
        setWeight('');
        setIdempotencyKey(newIdempotencyKey());
        setCreated(null);
        setQuote(null);
        setQuoteUnavailable(false);
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect
        reset();
    }, [open]);

    useEffect(() => {
        if (
            !open ||
            created !== null ||
            depositHubUuid === '' ||
            destinationHubUuid === '' ||
            weightValue === null ||
            weightValue < 1 ||
            weightValue > maximumWeight
        ) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setQuote(null);
            setQuoteUnavailable(false);

            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            void onQuote({
                deposit_hub_uuid: depositHubUuid,
                destination_hub_uuid: destinationHubUuid,
                weight_grams: weightValue,
            }).then((result) => {
                if (cancelled) {
                    return;
                }

                setQuote(result);
                setQuoteUnavailable(result === null);
            });
        }, 450);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [
        open,
        created,
        depositHubUuid,
        destinationHubUuid,
        weightValue,
        maximumWeight,
        onQuote,
    ]);

    const submit = async () => {
        if (!canSubmit || weightValue === null) {
            return;
        }

        const result = await onCreate({
            deposit_hub_uuid: depositHubUuid,
            destination_hub_uuid: destinationHubUuid,
            idempotency_key: idempotencyKey,
            sender_name: senderName.trim(),
            sender_phone: senderPhone.trim(),
            recipient_name: recipientName.trim(),
            recipient_phone: recipientPhone.trim(),
            contents: contents.trim(),
            weight_grams: weightValue,
        });

        if (result !== null) {
            setCreated(result);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-[48rem]">
                <DialogHeader>
                    <DialogTitle>
                        {created === null
                            ? 'Créer un colis au guichet'
                            : 'Colis enregistré'}
                    </DialogTitle>
                    <DialogDescription>
                        {created === null
                            ? 'Dépôt direct : le colis est pré-enregistré, une référence et un QR de suivi sont générés immédiatement.'
                            : 'La référence et le QR sont prêts. Le colis reste au statut pré-enregistré jusqu’à la pesée et au paiement.'}
                    </DialogDescription>
                    {created === null && <ParcelRequiredNote />}
                </DialogHeader>

                {created === null ? (
                    <>
                        <div className="grid gap-4">
                            <div className="field">
                                <ParcelFieldLabel
                                    htmlFor="parcel-deposit"
                                    icon={MapPin}
                                    required
                                    hint="Agence ou point partenaire relié à un corridor actif."
                                    hintId="parcel-deposit-hint"
                                >
                                    Lieu de dépôt
                                </ParcelFieldLabel>
                                <select
                                    id="parcel-deposit"
                                    value={depositHubUuid}
                                    onChange={(event) => {
                                        setDepositHubUuid(event.target.value);
                                        setDestinationHubUuid('');
                                    }}
                                    className="control"
                                    disabled={depositHubs.length === 0}
                                    aria-required="true"
                                    aria-describedby="parcel-deposit-hint"
                                    aria-invalid={Boolean(
                                        fieldError(
                                            validationErrors,
                                            'deposit_hub_uuid',
                                        ),
                                    )}
                                >
                                    <option value="">
                                        Sélectionner un lieu de dépôt
                                    </option>
                                    {depositHubs.map((hub) => {
                                        const stop = hub.stop_uuid
                                            ? stopsByUuid.get(hub.stop_uuid)
                                            : undefined;

                                        return (
                                            <option
                                                key={hub.uuid}
                                                value={hub.uuid}
                                            >
                                                {hub.name ?? 'Lieu sans nom'} ·{' '}
                                                {hub.city ?? '—'}
                                                {stop?.line_code
                                                    ? ` · ${stop.line_code}`
                                                    : ''}
                                            </option>
                                        );
                                    })}
                                </select>
                                {fieldError(
                                    validationErrors,
                                    'deposit_hub_uuid',
                                ) && (
                                    <p className="field-error-text">
                                        {fieldError(
                                            validationErrors,
                                            'deposit_hub_uuid',
                                        )}
                                    </p>
                                )}
                            </div>

                            <div className="field">
                                <ParcelFieldLabel
                                    htmlFor="parcel-destination"
                                    icon={Route}
                                    required
                                    hint="Arrêt desservi dans le bon ordre par le corridor choisi."
                                    hintId="parcel-destination-hint"
                                >
                                    Destination
                                </ParcelFieldLabel>
                                <select
                                    id="parcel-destination"
                                    value={destinationHubUuid}
                                    onChange={(event) =>
                                        setDestinationHubUuid(
                                            event.target.value,
                                        )
                                    }
                                    className="control"
                                    disabled={
                                        depositHubUuid === '' ||
                                        destinationHubs.length === 0
                                    }
                                    aria-required="true"
                                    aria-describedby="parcel-destination-hint"
                                    aria-invalid={Boolean(
                                        fieldError(
                                            validationErrors,
                                            'destination_hub_uuid',
                                        ),
                                    )}
                                >
                                    <option value="">
                                        Sélectionner une destination
                                    </option>
                                    {destinationHubs.map((hub) => {
                                        const stop = hub.stop_uuid
                                            ? stopsByUuid.get(hub.stop_uuid)
                                            : undefined;

                                        return (
                                            <option
                                                key={hub.uuid}
                                                value={hub.uuid}
                                            >
                                                {hub.name ?? 'Lieu sans nom'} ·{' '}
                                                {hub.city ?? '—'} ·{' '}
                                                {stop?.name ??
                                                    'Arrêt non renseigné'}
                                            </option>
                                        );
                                    })}
                                </select>
                                {fieldError(
                                    validationErrors,
                                    'destination_hub_uuid',
                                ) && (
                                    <p className="field-error-text">
                                        {fieldError(
                                            validationErrors,
                                            'destination_hub_uuid',
                                        )}
                                    </p>
                                )}
                                {depositHubs.length === 0 && (
                                    <p className="text-xs text-amber-700">
                                        Aucun corridor actif. Publiez et activez
                                        un corridor avant de créer un colis.
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-3 rounded-2xl border border-border bg-muted/25 p-4 sm:grid-cols-2">
                                <div className="field">
                                    <ParcelFieldLabel
                                        htmlFor="parcel-sender-name"
                                        icon={User}
                                        required
                                        hint="Nom complet du client qui dépose le colis."
                                    >
                                        Nom de l’expéditeur
                                    </ParcelFieldLabel>
                                    <input
                                        id="parcel-sender-name"
                                        value={senderName}
                                        onChange={(event) =>
                                            setSenderName(event.target.value)
                                        }
                                        maxLength={120}
                                        autoComplete="name"
                                        aria-required="true"
                                        className="control"
                                    />
                                    {fieldError(
                                        validationErrors,
                                        'sender_name',
                                    ) && (
                                        <p className="field-error-text">
                                            {fieldError(
                                                validationErrors,
                                                'sender_name',
                                            )}
                                        </p>
                                    )}
                                </div>
                                <div className="field">
                                    <ParcelFieldLabel
                                        htmlFor="parcel-sender-phone"
                                        icon={Phone}
                                        required
                                        hint="Numéro joignable pour les notifications de suivi."
                                        hintId="parcel-sender-phone-hint"
                                    >
                                        Téléphone expéditeur
                                    </ParcelFieldLabel>
                                    <PhoneInput
                                        id="parcel-sender-phone"
                                        className={`control phone-field-control ${
                                            senderPhone !== '' &&
                                            !senderPhoneValid
                                                ? 'phone-field-control-invalid'
                                                : ''
                                        }`}
                                        defaultCountry="BJ"
                                        international
                                        labels={phoneLabels}
                                        value={senderPhone || undefined}
                                        onChange={(value) =>
                                            setSenderPhone(value ?? '')
                                        }
                                        numberInputProps={{
                                            'aria-required': true,
                                            'aria-describedby':
                                                'parcel-sender-phone-hint',
                                            'aria-invalid':
                                                senderPhone !== '' &&
                                                !senderPhoneValid,
                                            autoComplete: 'tel',
                                        }}
                                    />
                                    {fieldError(
                                        validationErrors,
                                        'sender_phone',
                                    ) && (
                                        <p className="field-error-text">
                                            {fieldError(
                                                validationErrors,
                                                'sender_phone',
                                            )}
                                        </p>
                                    )}
                                    {senderPhone !== '' &&
                                        !senderPhoneValid && (
                                            <p className="field-error-text">
                                                Numéro de téléphone invalide.
                                            </p>
                                        )}
                                </div>
                                <div className="field">
                                    <ParcelFieldLabel
                                        htmlFor="parcel-recipient-name"
                                        icon={UserRoundCheck}
                                        required
                                        hint="Personne habilitée à retirer le colis à destination."
                                    >
                                        Nom du destinataire
                                    </ParcelFieldLabel>
                                    <input
                                        id="parcel-recipient-name"
                                        value={recipientName}
                                        onChange={(event) =>
                                            setRecipientName(event.target.value)
                                        }
                                        maxLength={120}
                                        autoComplete="name"
                                        aria-required="true"
                                        className="control"
                                    />
                                    {fieldError(
                                        validationErrors,
                                        'recipient_name',
                                    ) && (
                                        <p className="field-error-text">
                                            {fieldError(
                                                validationErrors,
                                                'recipient_name',
                                            )}
                                        </p>
                                    )}
                                </div>
                                <div className="field">
                                    <ParcelFieldLabel
                                        htmlFor="parcel-recipient-phone"
                                        icon={Phone}
                                        required
                                        hint="Numéro utilisé pour l’avis d’arrivée et le retrait."
                                        hintId="parcel-recipient-phone-hint"
                                    >
                                        Téléphone destinataire
                                    </ParcelFieldLabel>
                                    <PhoneInput
                                        id="parcel-recipient-phone"
                                        className={`control phone-field-control ${
                                            recipientPhone !== '' &&
                                            !recipientPhoneValid
                                                ? 'phone-field-control-invalid'
                                                : ''
                                        }`}
                                        defaultCountry="BJ"
                                        international
                                        labels={phoneLabels}
                                        value={recipientPhone || undefined}
                                        onChange={(value) =>
                                            setRecipientPhone(value ?? '')
                                        }
                                        numberInputProps={{
                                            'aria-required': true,
                                            'aria-describedby':
                                                'parcel-recipient-phone-hint',
                                            'aria-invalid':
                                                recipientPhone !== '' &&
                                                !recipientPhoneValid,
                                            autoComplete: 'tel',
                                        }}
                                    />
                                    {fieldError(
                                        validationErrors,
                                        'recipient_phone',
                                    ) && (
                                        <p className="field-error-text">
                                            {fieldError(
                                                validationErrors,
                                                'recipient_phone',
                                            )}
                                        </p>
                                    )}
                                    {recipientPhone !== '' &&
                                        !recipientPhoneValid && (
                                            <p className="field-error-text">
                                                Numéro de téléphone invalide.
                                            </p>
                                        )}
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-[1fr_10rem]">
                                <div className="field">
                                    <ParcelFieldLabel
                                        htmlFor="parcel-contents"
                                        icon={FileText}
                                        required
                                        hint="Description courte du contenu déclaré par le client."
                                    >
                                        Contenu déclaré
                                    </ParcelFieldLabel>
                                    <input
                                        id="parcel-contents"
                                        value={contents}
                                        onChange={(event) =>
                                            setContents(event.target.value)
                                        }
                                        maxLength={500}
                                        placeholder="Ex. documents, vêtements, livres…"
                                        aria-required="true"
                                        className="control"
                                    />
                                    {fieldError(
                                        validationErrors,
                                        'contents',
                                    ) && (
                                        <p className="field-error-text">
                                            {fieldError(
                                                validationErrors,
                                                'contents',
                                            )}
                                        </p>
                                    )}
                                </div>
                                <div className="field">
                                    <ParcelFieldLabel
                                        htmlFor="parcel-weight"
                                        icon={Weight}
                                        required
                                        hint="Poids constaté à la balance du guichet, en grammes."
                                    >
                                        Poids déclaré (g)
                                    </ParcelFieldLabel>
                                    <input
                                        id="parcel-weight"
                                        type="number"
                                        min={1}
                                        max={maximumWeight}
                                        step={1}
                                        value={weight}
                                        onChange={(event) =>
                                            setWeight(event.target.value)
                                        }
                                        aria-required="true"
                                        className="control"
                                    />
                                    {fieldError(
                                        validationErrors,
                                        'weight_grams',
                                    ) && (
                                        <p className="field-error-text">
                                            {fieldError(
                                                validationErrors,
                                                'weight_grams',
                                            )}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {quote !== null ? (
                                <div className="rounded-2xl border border-gold/30 bg-gold/10 px-4 py-3">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-start gap-3">
                                            <Coins className="mt-0.5 size-4 shrink-0 text-gold" />
                                            <div>
                                                <p className="text-xs font-semibold text-foreground">
                                                    Devis estimatif · grille{' '}
                                                    {quote.deposit_kind ===
                                                    'partner_point'
                                                        ? 'point partenaire'
                                                        : 'agence'}{' '}
                                                    V{quote.tariff_version}
                                                </p>
                                                <p className="mt-0.5 text-[0.6875rem] text-muted-foreground">
                                                    Base{' '}
                                                    {formatAmount(
                                                        quote.base_amount,
                                                        quote.currency,
                                                    )}{' '}
                                                    +{' '}
                                                    {formatAmount(
                                                        quote.step_amount,
                                                        quote.currency,
                                                    )}{' '}
                                                    par tranche de{' '}
                                                    {new Intl.NumberFormat(
                                                        'fr-FR',
                                                    ).format(
                                                        quote.step_grams,
                                                    )}{' '}
                                                    g · {quote.extra_steps}{' '}
                                                    {`tranche${
                                                        quote.extra_steps > 1
                                                            ? 's'
                                                            : ''
                                                    }`}
                                                </p>
                                            </div>
                                        </div>
                                        <p className="text-base font-semibold text-foreground">
                                            {formatAmount(
                                                quote.total,
                                                quote.currency,
                                            )}
                                        </p>
                                    </div>
                                    <dl className="mt-2 grid gap-1 border-t border-gold/20 pt-2 text-[0.6875rem] text-foreground/70 sm:grid-cols-3">
                                        <div>
                                            <dt>Net</dt>
                                            <dd>
                                                {formatAmount(
                                                    quote.net,
                                                    quote.currency,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>
                                                TVA {quote.tax_code} (
                                                {quote.tax_rate})
                                            </dt>
                                            <dd>
                                                {formatAmount(
                                                    quote.tax,
                                                    quote.currency,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Poids tarifé</dt>
                                            <dd>
                                                {new Intl.NumberFormat(
                                                    'fr-FR',
                                                ).format(
                                                    quote.weight_grams,
                                                )}{' '}
                                                g
                                            </dd>
                                        </div>
                                    </dl>
                                    {quote.tiers.length > 1 && (
                                        <p className="mt-2 text-[0.6875rem] text-muted-foreground">
                                            {quote.tiers
                                                .slice(1)
                                                .map(
                                                    (tier) =>
                                                        `${new Intl.NumberFormat(
                                                            'fr-FR',
                                                        ).format(
                                                            tier.from_grams,
                                                        )}–${new Intl.NumberFormat(
                                                            'fr-FR',
                                                        ).format(
                                                            tier.to_grams,
                                                        )} g : ${formatAmount(
                                                            tier.amount,
                                                            quote.currency,
                                                        )}`,
                                                )
                                                .join(' · ')}
                                        </p>
                                    )}
                                    <p className="mt-2 text-[0.625rem] text-muted-foreground">
                                        Montant prévisionnel : la pesée
                                        d’inspection fixe le devis définitif.
                                    </p>
                                </div>
                            ) : (
                                selectedCorridor &&
                                selectedDeposit &&
                                selectedDestination && (
                                    <div className="flex items-start gap-3 rounded-2xl border border-gold/30 bg-gold/10 px-4 py-3 text-xs leading-5 text-foreground/75">
                                        <Package className="mt-0.5 size-4 shrink-0 text-gold" />
                                        <p>
                                            {selectedDeposit.name ?? 'Lieu'} →{' '}
                                            {selectedDestination.name ??
                                                'Destination'}
                                            {'. '}
                                            Poids maximal couvert par le tarif :{' '}
                                            {new Intl.NumberFormat(
                                                'fr-FR',
                                            ).format(maximumWeight)}{' '}
                                            g.{' '}
                                            {quoteUnavailable
                                                ? 'Aucune grille active pour ce type de lieu de dépôt : devis indisponible.'
                                                : 'Saisissez un poids valide pour afficher le détail du devis.'}
                                        </p>
                                    </div>
                                )
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
                                variant="ghost"
                                onClick={() => onOpenChange(false)}
                                disabled={busy}
                            >
                                Annuler
                            </ParcelButton>
                            <ParcelButton
                                variant="primary"
                                onClick={() => void submit()}
                                disabled={!canSubmit}
                            >
                                {busy ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Création…
                                    </>
                                ) : (
                                    <>
                                        <Package className="size-4" />
                                        Créer le colis
                                    </>
                                )}
                            </ParcelButton>
                        </DialogFooter>
                    </>
                ) : (
                    <>
                        <div className="grid gap-5 sm:grid-cols-[1fr_11rem]">
                            <div className="grid gap-3">
                                <div className="rounded-2xl border border-emerald-300/50 bg-emerald-50 px-4 py-4">
                                    <div className="flex items-center gap-2 text-sm font-bold text-emerald-800">
                                        <CheckCircle2 className="size-4" />
                                        Référence générée
                                    </div>
                                    <p className="mt-2 font-mono text-xl font-black tracking-[0.04em] text-kora">
                                        {created.reference}
                                    </p>
                                    <p className="mt-1 text-xs text-emerald-800/70">
                                        Statut : {created.status} · Expire le{' '}
                                        {new Intl.DateTimeFormat('fr-FR', {
                                            dateStyle: 'long',
                                        }).format(new Date(created.expires_at))}
                                    </p>
                                </div>

                                <dl className="grid gap-2 rounded-2xl border border-border bg-card px-4 py-4 text-sm">
                                    <div className="flex items-center justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Trajet
                                        </dt>
                                        <dd className="text-right font-semibold text-foreground">
                                            {created.origin ?? '—'}{' '}
                                            <ArrowRight className="inline size-3.5" />{' '}
                                            {created.destination ?? '—'}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Destinataire
                                        </dt>
                                        <dd className="text-right font-semibold text-foreground">
                                            {created.recipient_name}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Poids déclaré
                                        </dt>
                                        <dd className="text-right font-semibold text-foreground">
                                            {new Intl.NumberFormat(
                                                'fr-FR',
                                            ).format(
                                                created.declared_grams,
                                            )}{' '}
                                            g
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Montant
                                        </dt>
                                        <dd className="text-right font-semibold text-foreground">
                                            {created.quote === null
                                                ? 'Calculé à la pesée'
                                                : formatAmount(
                                                      created.quote.total,
                                                      created.quote.currency,
                                                  )}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div className="grid place-items-center rounded-2xl border border-border bg-white p-4">
                                {created.qr_payload ? (
                                    <QRCodeSVG
                                        value={created.qr_payload}
                                        size={144}
                                        bgColor="#ffffff"
                                        fgColor="#001D4E"
                                        level="M"
                                        includeMargin
                                    />
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        QR indisponible
                                    </span>
                                )}
                                <p className="mt-2 text-center text-[0.625rem] leading-4 text-muted-foreground">
                                    QR de suivi
                                    <br />
                                    {created.reference}
                                </p>
                            </div>
                        </div>

                        <DialogFooter>
                            <ParcelButton variant="ghost" onClick={reset}>
                                Nouveau colis
                            </ParcelButton>
                            <ParcelButton
                                variant="primary"
                                onClick={() => onOpenChange(false)}
                            >
                                Fermer
                            </ParcelButton>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

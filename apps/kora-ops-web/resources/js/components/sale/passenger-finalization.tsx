import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    FileText,
    Globe,
    Mail,
    Phone,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PhoneInput, {
    isValidPhoneNumber,
    parsePhoneNumber,
} from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { cn } from '@/lib/utils';
import { NationalitySelect } from './nationality-select';
import { SaleJourneySummary } from './sale-journey-summary';
import type {
    SaleDeparture,
    SaleItineraryQuote,
    SalePassengerForm,
    SaleReservation,
    SaleSearch,
} from './sale-types';
import { useHoldCountdown } from './use-hold-countdown';

type Props = {
    departure: SaleDeparture | null;
    reservation: SaleReservation;
    processing: boolean;
    releasing: boolean;
    errors: Record<string, string>;
    onSubmit: (passengers: SalePassengerForm[]) => void;
    onDraftChange: (passengers: SalePassengerForm[]) => void;
    draftSaving: boolean;
    draftSaved: boolean;
    onBack: () => void;
    onHoldExpired: () => void;
    search?: SaleSearch | null;
    itineraryQuote?: SaleItineraryQuote | null;
};

function initialPassengers(reservation: SaleReservation): SalePassengerForm[] {
    const passengerIndexes = Array.from(
        new Set(
            reservation.segments.map(
                (segment, index) => segment.passengerIndex ?? index,
            ),
        ),
    ).sort((a, b) => a - b);

    return passengerIndexes.map((passengerIndex) => {
        const segment = reservation.segments.find(
            (candidate, index) =>
                (candidate.passengerIndex ?? index) === passengerIndex,
        );
        const passenger = reservation.passengers.find(
            (candidate) => candidate.passengerIndex === passengerIndex,
        );
        const draft = reservation.draftPassengers.find(
            (candidate) => candidate.passengerIndex === passengerIndex,
        );

        return {
            passengerIndex,
            firstName: draft?.firstName ?? passenger?.firstName ?? '',
            lastName: draft?.lastName ?? passenger?.lastName ?? '',
            phone: normalizePhoneValue(draft?.phone ?? passenger?.phone),
            email: draft?.email ?? passenger?.email ?? '',
            documentType:
                draft?.documentType ?? passenger?.documentType ?? '',
            documentNumber:
                draft?.documentNumber ?? passenger?.documentNumber ?? '',
            nationality: draft?.nationality ?? passenger?.nationality ?? '',
            birthDate: draft?.birthDate ?? passenger?.birthDate ?? '',
            yellowFeverVaccinated:
                draft?.yellowFeverVaccinated ??
                passenger?.yellowFeverVaccinated ??
                null,
            seatUuid: segment?.seatUuid ?? '',
            seatCode: reservation.segments
                .filter(
                    (candidate, index) =>
                        (candidate.passengerIndex ?? index) === passengerIndex,
                )
                .map((candidate) => candidate.seatCode)
                .filter(Boolean)
                .join(' · '),
        };
    });
}

function normalizePhoneValue(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    try {
        return parsePhoneNumber(value)?.number ?? value;
    } catch {
        return value;
    }
}

function isPassengerComplete(passenger: SalePassengerForm): boolean {
    const hasValidOptionalPhone =
        passenger.phone.trim() === '' || isValidPhoneNumber(passenger.phone);
    const hasCompleteDocumentPair =
        (passenger.documentType.trim() === '' &&
            passenger.documentNumber.trim() === '') ||
        (passenger.documentType.trim() !== '' &&
            passenger.documentNumber.trim() !== '');

    return (
        passenger.firstName.trim() !== '' &&
        passenger.lastName.trim() !== '' &&
        hasValidOptionalPhone &&
        hasCompleteDocumentPair
    );
}

function hasSamePassengerDetails(
    current: SalePassengerForm,
    persisted: SalePassengerForm | undefined,
): boolean {
    if (!persisted) {
        return false;
    }

    return (
        current.firstName === persisted.firstName &&
        current.lastName === persisted.lastName &&
        current.phone === persisted.phone &&
        current.email === persisted.email &&
        current.documentType === persisted.documentType &&
        current.documentNumber === persisted.documentNumber &&
        current.nationality === persisted.nationality &&
        current.birthDate === persisted.birthDate
        && current.yellowFeverVaccinated === persisted.yellowFeverVaccinated
    );
}

export function PassengerFinalization({
    departure,
    reservation,
    processing,
    releasing,
    errors,
    onSubmit,
    onDraftChange,
    draftSaving,
    draftSaved,
    onBack,
    onHoldExpired,
    search = null,
    itineraryQuote = null,
}: Props) {
    const [passengers, setPassengers] = useState(() =>
        initialPassengers(reservation),
    );
    const [confirmed, setConfirmed] = useState(
        reservation.passengers.length > 0,
    );
    const [activeIndex, setActiveIndex] = useState(0);
    const [confirmingBack, setConfirmingBack] = useState(false);
    const holdCountdown = useHoldCountdown(reservation.expiresAt);
    const expirationHandled = useRef(false);
    const latestPassengers = useRef(passengers);
    const flushOnUnmount = useRef(true);

    useEffect(() => {
        latestPassengers.current = passengers;
    }, [passengers]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            onDraftChange(passengers);
        }, 700);

        return () => window.clearTimeout(timeout);
    }, [onDraftChange, passengers]);

    useEffect(
        () => () => {
            if (flushOnUnmount.current && latestPassengers.current.length > 0) {
                onDraftChange(latestPassengers.current);
            }
        },
        [onDraftChange],
    );

    useEffect(() => {
        if (!holdCountdown.isExpired || expirationHandled.current) {
            return;
        }

        expirationHandled.current = true;
        flushOnUnmount.current = false;
        onHoldExpired();
    }, [holdCountdown.isExpired, onHoldExpired]);

    const isInternational = departure?.line.isInternational ?? false;
    const completeCount = useMemo(
        () =>
            passengers.filter((passenger) =>
                isPassengerComplete(passenger),
            ).length,
        [passengers],
    );
    const completedPassengers = completeCount === passengers.length;
    const savedPassengers = reservation.passengers.length;
    const persistedPassengers = useMemo(
        () => initialPassengers(reservation),
        [reservation],
    );
    const savedAllPassengers =
        savedPassengers === passengers.length &&
        passengers.length > 0 &&
        passengers.every((passenger, index) =>
            hasSamePassengerDetails(passenger, persistedPassengers[index]),
        );
    const progress =
        passengers.length > 0 ? (completeCount / passengers.length) * 100 : 0;

    const updatePassenger = (
        index: number,
        field: keyof SalePassengerForm,
        value: string | boolean | null,
    ) => {
        setPassengers((current) =>
            current.map((passenger, passengerIndex) =>
                passengerIndex === index
                    ? { ...passenger, [field]: value }
                    : passenger,
            ),
        );
    };

    const fieldError = (index: number, field: string): string | undefined =>
        errors[`passengers.${index}.${field}`];

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!completedPassengers || !confirmed || processing) {
            return;
        }

        flushOnUnmount.current = false;
        onSubmit(passengers);
    };

    const goBackToSeats = () => {
        flushOnUnmount.current = false;
        onBack();
    };

    return (
        <form className="checkout-layout" onSubmit={submit} noValidate>
            <section className="checkout-main kora-panel">
                <div className="checkout-title">
                    <div className="finalization-heading">
                        <div>
                            <span className="section-kicker">
                                {isInternational
                                    ? 'Trajet international'
                                    : 'Trajet national'}{' '}
                                · {passengers.length} voyageur
                                {passengers.length > 1 ? 's' : ''}
                            </span>
                            <h2>Renseigner chaque voyageur</h2>
                            <p>
                                {isInternational
                                    ? 'Le prénom et le nom suffisent pour valider la vente. Les informations de document, nationalité et vaccination restent facultatives.'
                                    : 'Le prénom et le nom sont obligatoires pour chaque billet. Le téléphone et les autres informations restent facultatifs.'}
                            </p>
                        </div>
                        <div
                            className={cn(
                                'finalization-status',
                                completedPassengers && 'is-complete',
                            )}
                            aria-live="polite"
                        >
                            {completedPassengers ? (
                                <CheckCircle2 className="size-4" />
                            ) : (
                                <span>{completeCount}</span>
                            )}
                            <strong>
                                {completedPassengers && savedAllPassengers
                                    ? 'Informations enregistrées'
                                    : completedPassengers
                                        ? 'Prêt à enregistrer'
                                        : `${completeCount}/${passengers.length} complété${completeCount > 1 ? 's' : ''}`}
                            </strong>
                        </div>
                    </div>
                    <div className="mt-3 flex items-center justify-end gap-2 text-[0.7rem] font-semibold text-muted-foreground">
                        <span
                            className={cn(
                                'size-1.5 rounded-full bg-emerald-500',
                                draftSaving && 'animate-pulse bg-gold',
                            )}
                        />
                        {draftSaving
                            ? 'Enregistrement du brouillon…'
                            : draftSaved
                                ? 'Brouillon enregistré'
                                : 'Brouillon local prêt à enregistrer'}
                    </div>
                    <div
                        className="finalization-progress"
                        role="progressbar"
                        aria-label="Progression des informations voyageurs"
                        aria-valuemin={0}
                        aria-valuemax={passengers.length}
                        aria-valuenow={completeCount}
                    >
                        <span style={{ width: `${progress}%` }} />
                    </div>
                </div>

                <nav className="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="Voyageurs du groupe">
                    {passengers.map((passenger, index) => (
                        <button
                            key={passenger.passengerIndex}
                            type="button"
                            className={cn('btn btn-ghost shrink-0', activeIndex === index && 'border-gold bg-gold/10')}
                            onClick={() => setActiveIndex(index)}
                            aria-current={activeIndex === index ? 'step' : undefined}
                        >
                            {isPassengerComplete(passenger) ? <CheckCircle2 className="size-3.5 text-emerald-600" /> : <span>{index + 1}</span>}
                            Voyageur {index + 1} · {passenger.seatCode || 'sans siège'}
                        </button>
                    ))}
                </nav>

                {passengers.map((passenger, index) => {
                    if (index !== activeIndex) {
                        return null;
                    }

                    const phoneError =
                        fieldError(index, 'phone') ??
                        (passenger.phone.trim() !== '' &&
                            !isValidPhoneNumber(passenger.phone)
                            ? 'Saisissez un numéro international valide.'
                            : undefined);

                    return (
                        <article
                            className={cn(
                                'passenger-card',
                                isPassengerComplete(passenger) && 'is-complete',
                                Object.keys(errors).some((key) =>
                                    key.startsWith(`passengers.${index}.`),
                                ) && 'has-server-error',
                            )}
                            key={passenger.seatUuid || index}
                        >
                            <div className="passenger-card-head">
                                <span>
                                    <b>{index + 1}</b>
                                    <span>
                                        <strong>Voyageur {index + 1}</strong>
                                        <small>Identité et coordonnées</small>
                                    </span>
                                </span>
                                <span className="passenger-seat-meta">
                                    {isPassengerComplete(passenger) && (
                                        <CheckCircle2
                                            className="size-3.5"
                                            aria-label="Informations complètes"
                                        />
                                    )}
                                    <em>Siège {passenger.seatCode}</em>
                                </span>
                            </div>

                            <div className="form-grid premium-form">
                                <div className="form-group-heading">
                                    <span className="form-group-heading-title">
                                        <UserRound className="size-3.5" />
                                        Coordonnées du voyageur
                                    </span>
                                    <small>
                                        Les champs marqués d’un astérisque sont
                                        obligatoires.
                                    </small>
                                </div>
                                <label className="field">
                                    <span>
                                        Prénom{' '}
                                        <span className="required-asterisk">
                                            *
                                        </span>
                                    </span>
                                    <input
                                        id={`passenger-${index}-first-name`}
                                        className="control"
                                        value={passenger.firstName}
                                        onChange={(event) =>
                                            updatePassenger(
                                                index,
                                                'firstName',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            fieldError(index, 'first_name'),
                                        )}
                                        autoComplete="given-name"
                                    />
                                    {fieldError(index, 'first_name') && (
                                        <small className="field-error-text">
                                            {fieldError(index, 'first_name')}
                                        </small>
                                    )}
                                </label>
                                <label className="field">
                                    <span>
                                        Nom{' '}
                                        <span className="required-asterisk">
                                            *
                                        </span>
                                    </span>
                                    <input
                                        id={`passenger-${index}-last-name`}
                                        className="control"
                                        value={passenger.lastName}
                                        onChange={(event) =>
                                            updatePassenger(
                                                index,
                                                'lastName',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            fieldError(index, 'last_name'),
                                        )}
                                        autoComplete="family-name"
                                    />
                                    {fieldError(index, 'last_name') && (
                                        <small className="field-error-text">
                                            {fieldError(index, 'last_name')}
                                        </small>
                                    )}
                                </label>
                                <label className="field">
                                    <span className="field-label">
                                        <Phone className="size-3.5" />
                                        Téléphone personnel · optionnel
                                    </span>
                                    <PhoneInput
                                        id={`passenger-${index}-phone`}
                                        className="control phone-field-control"
                                        defaultCountry="BJ"
                                        international
                                        labels={phoneLabels}
                                        value={passenger.phone || undefined}
                                        onChange={(event) =>
                                            updatePassenger(
                                                index,
                                                'phone',
                                                event ?? '',
                                            )
                                        }
                                        numberInputProps={{
                                            'aria-invalid': Boolean(phoneError),
                                            'aria-describedby': phoneError
                                                ? `passenger-${index}-phone-error`
                                                : undefined,
                                            autoComplete: 'tel',
                                        }}
                                    />
                                    {phoneError && (
                                        <small
                                            className="field-error-text"
                                            id={`passenger-${index}-phone-error`}
                                        >
                                            {phoneError}
                                        </small>
                                    )}
                                </label>
                                <label className="field">
                                    <span className="field-label">
                                        <Mail className="size-3.5" />
                                        Email · optionnel
                                    </span>
                                    <input
                                        id={`passenger-${index}-email`}
                                        className="control"
                                        type="email"
                                        value={passenger.email}
                                        onChange={(event) =>
                                            updatePassenger(
                                                index,
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            fieldError(index, 'email'),
                                        )}
                                        autoComplete="email"
                                    />
                                </label>

                                {(
                                    <>
                                        <div className="form-group-heading">
                                            <span className="form-group-heading-title">
                                                <FileText className="size-3.5" />
                                                Document de voyage
                                            </span>
                                            <small>
                                                Facultatif. Si vous renseignez un document, le type et le numéro sont tous deux requis.
                                            </small>
                                        </div>
                                        <label className="field">
                                            <span>
                                                Type de document
                                            </span>
                                            <select
                                                id={`passenger-${index}-document-type`}
                                                className="control"
                                                value={passenger.documentType}
                                                onChange={(event) =>
                                                    updatePassenger(
                                                        index,
                                                        'documentType',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">Aucun document</option>
                                                <option value="national_id">
                                                    Carte nationale
                                                </option>
                                                <option value="biometric_card">
                                                    Carte biométrique
                                                </option>
                                                <option value="passport">
                                                    Passeport
                                                </option>
                                                <option value="consular_card">
                                                    Carte consulaire
                                                </option>
                                            </select>
                                        </label>
                                        <label className="field">
                                            <span>
                                                Numéro du document
                                            </span>
                                            <input
                                                id={`passenger-${index}-document-number`}
                                                className="control"
                                                value={passenger.documentNumber}
                                                onChange={(event) =>
                                                    updatePassenger(
                                                        index,
                                                        'documentNumber',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </label>
                                        <div className="field">
                                            <span className="field-label">
                                                <Globe className="size-3.5" />
                                                Nationalité · optionnelle
                                            </span>
                                            <NationalitySelect
                                                id={`passenger-${index}-nationality`}
                                                value={passenger.nationality}
                                                onChange={(value) =>
                                                    updatePassenger(
                                                        index,
                                                        'nationality',
                                                        value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <label className="field">
                                            <span className="field-label">
                                                <CalendarDays className="size-3.5" />
                                                Date de naissance · optionnelle
                                            </span>
                                            <input
                                                id={`passenger-${index}-birth-date`}
                                                className="control"
                                                type="date"
                                                value={passenger.birthDate}
                                                onChange={(event) =>
                                                    updatePassenger(
                                                        index,
                                                        'birthDate',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </label>
                                        <label className="field sm:col-span-2">
                                            <span className="field-label">Vaccination fièvre jaune</span>
                                            <select
                                                className="control"
                                                value={passenger.yellowFeverVaccinated === null ? '' : passenger.yellowFeverVaccinated ? 'yes' : 'no'}
                                                onChange={(event) =>
                                                    updatePassenger(
                                                        index,
                                                        'yellowFeverVaccinated',
                                                        event.target.value === '' ? null : event.target.value === 'yes',
                                                    )
                                                }
                                            >
                                                <option value="">Non renseignée</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                            </select>
                                            <small className="payment-field-help">Information enregistrée sans bloquer la vente.</small>
                                        </label>
                                    </>
                                )}
                            </div>
                        </article>
                    );
                })}

                <div className="mb-5 flex items-center justify-between gap-3">
                    <button type="button" className="btn btn-ghost" disabled={activeIndex === 0} onClick={() => setActiveIndex((value) => Math.max(0, value - 1))}>
                        <ArrowLeft className="size-4" /> Précédent
                    </button>
                    <span className="text-xs font-semibold text-muted-foreground">{activeIndex + 1} / {passengers.length}</span>
                    <button type="button" className="btn btn-ghost" disabled={activeIndex === passengers.length - 1} onClick={() => setActiveIndex((value) => Math.min(passengers.length - 1, value + 1))}>
                        Suivant <ArrowRight className="size-4" />
                    </button>
                </div>

                <label className="finalization-check">
                    <input
                        type="checkbox"
                        checked={confirmed}
                        onChange={(event) => setConfirmed(event.target.checked)}
                    />
                    <span>
                        L’acheteur confirme les informations fournies pour
                        chaque voyageur.
                    </span>
                </label>

                {errors.passengers && (
                    <div className="finalization-error" role="alert">
                        <AlertCircle className="size-4" />
                        {errors.passengers}
                    </div>
                )}

                <div className="checkout-actions">
                    <div className="checkout-actions-nav">
                        {!confirmingBack ? (
                            <button
                                type="button"
                                className="btn btn-ghost"
                                disabled={processing || releasing}
                                onClick={() => setConfirmingBack(true)}
                            >
                                <ArrowLeft className="size-4" />
                                Modifier les places
                            </button>
                        ) : (
                            <div
                                className="back-confirmation"
                                role="alertdialog"
                                aria-labelledby="back-confirmation-title"
                            >
                                <strong id="back-confirmation-title">
                                    Revenir à la sélection des places ?
                                </strong>
                                <p>
                                    Le blocage actuel sera libéré. Il faudra
                                    confirmer à nouveau les sièges avant de
                                    poursuivre. Les informations voyageurs de ce
                                    dossier devront être ressaisies.
                                </p>
                                <div>
                                    <button
                                        type="button"
                                        className="btn btn-ghost"
                                        disabled={releasing}
                                        onClick={() => setConfirmingBack(false)}
                                    >
                                        Continuer la saisie
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={releasing}
                                        onClick={goBackToSeats}
                                    >
                                        {releasing
                                            ? 'Retour…'
                                            : 'Confirmer le retour'}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                    {!confirmingBack && (
                        <span className="checkout-actions-note">
                            <CheckCircle2 className="size-3.5" />
                            <span>
                                {holdCountdown.isExpired
                                    ? 'Le blocage a expiré. Revenez aux places pour le renouveler.'
                                    : completedPassengers
                                        ? 'Toutes les informations requises sont prêtes. Vous pourrez ensuite encaisser la vente.'
                                        : 'Complétez les champs requis pour continuer.'}
                            </span>
                        </span>
                    )}
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={
                            !completedPassengers ||
                            !confirmed ||
                            processing ||
                            releasing ||
                            confirmingBack ||
                            holdCountdown.isExpired
                        }
                    >
                        {processing
                            ? 'Enregistrement…'
                            : savedAllPassengers
                                ? 'Mettre à jour les voyageurs'
                                : 'Enregistrer les voyageurs'}
                        <ArrowRight className="size-4" />
                    </button>
                </div>
            </section>

            <SaleJourneySummary
                departure={departure}
                reservation={reservation}
                variant="passengers"
                search={search}
                itineraryQuote={itineraryQuote}
            />
        </form>
    );
}

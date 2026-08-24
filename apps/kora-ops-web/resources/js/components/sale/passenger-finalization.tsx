import {
    AlertCircle,
    Armchair,
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    BusFront,
    CalendarDays,
    CheckCircle2,
    Clock3,
    FileText,
    Mail,
    Phone,
    Route as RouteIcon,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PhoneInput, {
    isValidPhoneNumber,
    parsePhoneNumber,
} from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { cn } from '@/lib/utils';
import type {
    SaleDeparture,
    SalePassengerForm,
    SaleReservation,
} from './sale-types';
import { formatDate, formatMoney, formatTime } from './sale-utils';
import { useHoldCountdown } from './use-hold-countdown';

type Props = {
    departure: SaleDeparture | null;
    reservation: SaleReservation;
    processing: boolean;
    releasing: boolean;
    errors: Record<string, string>;
    onSubmit: (passengers: SalePassengerForm[]) => void;
    onBack: () => void;
    onHoldExpired: () => void;
};

function initialPassengers(reservation: SaleReservation): SalePassengerForm[] {
    return reservation.segments.map((segment) => {
        const passenger = reservation.passengers.find(
            (candidate) => candidate.seatUuid === segment.seatUuid,
        );

        return {
            firstName: passenger?.firstName ?? '',
            lastName: passenger?.lastName ?? '',
            phone: normalizePhoneValue(passenger?.phone),
            email: passenger?.email ?? '',
            documentType: passenger?.documentType ?? 'national_id',
            documentNumber: passenger?.documentNumber ?? '',
            nationality: passenger?.nationality ?? '',
            birthDate: passenger?.birthDate ?? '',
            seatUuid: segment.seatUuid ?? '',
            seatCode: segment.seatCode ?? '—',
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

function isPassengerComplete(
    passenger: SalePassengerForm,
    isInternational: boolean,
): boolean {
    return (
        passenger.firstName.trim() !== '' &&
        passenger.lastName.trim() !== '' &&
        passenger.phone.trim() !== '' &&
        isValidPhoneNumber(passenger.phone) &&
        (!isInternational ||
            (passenger.documentType.trim() !== '' &&
                passenger.documentNumber.trim() !== '' &&
                passenger.nationality.trim() !== '' &&
                passenger.birthDate.trim() !== ''))
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
    );
}

export function PassengerFinalization({
    departure,
    reservation,
    processing,
    releasing,
    errors,
    onSubmit,
    onBack,
    onHoldExpired,
}: Props) {
    const [passengers, setPassengers] = useState(() =>
        initialPassengers(reservation),
    );
    const [confirmed, setConfirmed] = useState(
        reservation.passengers.length > 0,
    );
    const [confirmingBack, setConfirmingBack] = useState(false);
    const holdCountdown = useHoldCountdown(reservation.expiresAt);
    const expirationHandled = useRef(false);

    useEffect(() => {
        if (!holdCountdown.isExpired || expirationHandled.current) {
            return;
        }

        expirationHandled.current = true;
        onHoldExpired();
    }, [holdCountdown.isExpired, onHoldExpired]);

    const isInternational = departure?.line.isInternational ?? false;
    const completeCount = useMemo(
        () =>
            passengers.filter((passenger) =>
                isPassengerComplete(passenger, isInternational),
            ).length,
        [isInternational, passengers],
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
        value: string,
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

        onSubmit(passengers);
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
                                    ? 'Identité, document, nationalité et date de naissance sont obligatoires pour le manifeste frontière.'
                                    : 'Le prénom, le nom et le téléphone sont obligatoires pour chaque billet.'}
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

                {passengers.map((passenger, index) => {
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
                                isPassengerComplete(
                                    passenger,
                                    isInternational,
                                ) && 'is-complete',
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
                                    {isPassengerComplete(
                                        passenger,
                                        isInternational,
                                    ) && (
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
                                    <span>Prénom *</span>
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
                                    <span>Nom *</span>
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
                                        Téléphone international *
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

                                {isInternational && (
                                    <>
                                        <div className="form-group-heading">
                                            <span className="form-group-heading-title">
                                                <FileText className="size-3.5" />
                                                Document de voyage
                                            </span>
                                            <small>
                                                Ces informations seront reprises
                                                sur le manifeste frontière.
                                            </small>
                                        </div>
                                        <label className="field">
                                            <span>Type de document *</span>
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
                                                <option value="national_id">
                                                    Carte nationale
                                                </option>
                                                <option value="passport">
                                                    Passeport
                                                </option>
                                                <option value="consular_card">
                                                    Carte consulaire
                                                </option>
                                                <option value="birth_certificate">
                                                    Acte de naissance
                                                </option>
                                            </select>
                                        </label>
                                        <label className="field">
                                            <span>Numéro du document *</span>
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
                                        <label className="field">
                                            <span>Nationalité *</span>
                                            <input
                                                id={`passenger-${index}-nationality`}
                                                className="control"
                                                maxLength={2}
                                                value={passenger.nationality}
                                                onChange={(event) =>
                                                    updatePassenger(
                                                        index,
                                                        'nationality',
                                                        event.target.value.toUpperCase(),
                                                    )
                                                }
                                            />
                                        </label>
                                        <label className="field">
                                            <span className="field-label">
                                                <CalendarDays className="size-3.5" />
                                                Date de naissance *
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
                                    </>
                                )}
                            </div>
                        </article>
                    );
                })}

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
                                        onClick={onBack}
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
                                      ? 'Toutes les informations requises sont prêtes.'
                                      : 'Complétez les champs requis pour continuer.'}
                                <small>
                                    Le paiement sera disponible dans le prochain
                                    lot.
                                </small>
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

            <aside className="journey-summary summary">
                <div className="summary-hero">
                    <small>Dossier de vente</small>
                    <h3>Finalisation</h3>
                    <p>Réservation temporaire</p>
                </div>
                <div className="summary-route">
                    <span>
                        <b>{departure?.originStop.city ?? '—'}</b>
                        <small>{departure?.originStop.name ?? 'Origine'}</small>
                    </span>
                    <i aria-hidden="true">
                        <em>K</em>
                    </i>
                    <span>
                        <b>{departure?.destinationStop.city ?? '—'}</b>
                        <small>
                            {departure?.destinationStop.name ?? 'Destination'}
                        </small>
                    </span>
                </div>
                <div className="summary-row">
                    <span className="summary-label">
                        <CalendarDays className="size-3.5" />
                        Date
                    </span>
                    <strong>
                        {departure ? formatDate(departure.departureDate) : '—'}
                    </strong>
                </div>
                <div className="summary-row">
                    <span className="summary-label">
                        <Clock3 className="size-3.5" />
                        Départ
                    </span>
                    <strong>
                        {departure ? formatTime(departure.departureTime) : '—'}
                    </strong>
                </div>
                <div className="summary-row">
                    <span className="summary-label">
                        <Armchair className="size-3.5" />
                        Sièges
                    </span>
                    <strong>
                        {reservation.segments
                            .map((segment) => segment.seatCode)
                            .join(', ')}
                    </strong>
                </div>
                <div className="summary-row">
                    <span className="summary-label">
                        <RouteIcon className="size-3.5" />
                        Ligne
                    </span>
                    <strong>{departure?.line.code ?? '—'}</strong>
                </div>
                <div className="summary-row">
                    <span className="summary-label">
                        <BusFront className="size-3.5" />
                        Véhicule
                    </span>
                    <strong>
                        {departure
                            ? [
                                  [departure.bus.brand, departure.bus.model]
                                      .filter(Boolean)
                                      .join(' ') || 'Véhicule',
                                  departure.bus.registrationNumber,
                              ].join(' · ')
                            : '—'}
                    </strong>
                </div>
                <div className="summary-row">
                    <span className="summary-label">
                        <Users className="size-3.5" />
                        Capacité
                    </span>
                    <strong>
                        {departure ? `${departure.bus.seatCount} places` : '—'}
                    </strong>
                </div>
                <div className="summary-row">
                    <span className="summary-label">
                        <BadgeCheck className="size-3.5" />
                        Service
                    </span>
                    <strong>
                        {departure?.farePreview?.service_class_name ??
                            departure?.farePreview?.service_class_code ??
                            'Standard'}
                    </strong>
                </div>
                <div className="summary-total">
                    <span>Total à encaisser</span>
                    <strong>
                        {formatMoney(
                            reservation.totalAmount,
                            reservation.currency,
                        )}
                    </strong>
                </div>
                <div
                    className={cn(
                        'summary-callout',
                        'hold-countdown',
                        holdCountdown.isUrgent && 'is-urgent',
                        holdCountdown.isExpired && 'is-expired',
                    )}
                    role="status"
                    aria-live="polite"
                >
                    <div className="hold-countdown-top">
                        <span className="hold-countdown-icon">
                            <Clock3 className="size-4" />
                        </span>
                        <span className="hold-countdown-copy">
                            <small>Blocage temporaire</small>
                            <strong>
                                {holdCountdown.isExpired
                                    ? 'Hold expiré'
                                    : holdCountdown.isUrgent
                                      ? 'À finaliser rapidement'
                                      : 'Places sécurisées'}
                            </strong>
                        </span>
                        <time dateTime={reservation.expiresAt ?? undefined}>
                            {holdCountdown.label}
                        </time>
                    </div>
                    <div className="hold-countdown-track" aria-hidden="true">
                        <span
                            style={{ width: `${holdCountdown.percentage}%` }}
                        />
                    </div>
                    <p>
                        {holdCountdown.isExpired
                            ? 'Le blocage doit être renouvelé avant de poursuivre.'
                            : holdCountdown.isUrgent
                              ? 'Finalisez les informations ou modifiez les places avant la libération automatique.'
                              : 'Le temps restant est actualisé automatiquement.'}
                    </p>
                </div>
                <div className="summary-callout success">
                    <div>
                        <ShieldCheck className="size-4" />
                        Places sécurisées
                    </div>
                    <p>Les sièges restent bloqués pendant la saisie.</p>
                </div>
                <div className="payment-disabled-note">
                    <CheckCircle2 className="size-4" />
                    Paiement désactivé · MSF sera raccordé au prochain lot.
                </div>
            </aside>
        </form>
    );
}

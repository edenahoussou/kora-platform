import {
    MapPin,
    Calendar,
    ArrowRight,
    Check,
    Smartphone,
    Banknote,
    User,
    Phone,
    FileBadge,
    ChevronLeft,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import type {
    FinancialHealth,
    KoraPointProfile,
    PointSaleRecord,
    SaleDepartureMock,
} from '../mock/kora-point-mock-data';
import { MOCK_DEPARTURES } from '../mock/kora-point-mock-data';
import { KoraPointSaleCapacityCard } from './kora-point-sale-capacity-card';
import { KoraPointSaleSuccessDialog } from './kora-point-sale-success-dialog';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    onSaleCompleted: (sale: {
        passengerName: string;
        passengerPhone: string;
        departureRef: string;
        serviceClass: string;
        departureTime: string;
        routeLabel: string;
        originCity: string;
        destinationCity: string;
        seatNumber: string;
        amountPaid: number;
        paymentMethod: 'CASH' | 'MOBILE_MONEY';
    }) => PointSaleRecord;
    onBackToDashboard: () => void;
};

export function KoraPointSaleView({
    point,
    health,
    onSaleCompleted,
    onBackToDashboard,
}: Props) {
    const [step, setStep] = useState<1 | 2 | 3 | 4>(1);

    // Step 1: Trip selection
    const [selectedOrigin, setSelectedOrigin] = useState('Cotonou');
    const [selectedDestination, setSelectedDestination] = useState('Parakou');
    const [travelDate, setTravelDate] = useState('2026-08-01');
    const [selectedDeparture, setSelectedDeparture] =
        useState<SaleDepartureMock>(MOCK_DEPARTURES[0]);

    // Step 2: Seat selection
    const [selectedSeats, setSelectedSeats] = useState<string[]>(['12']);

    // Step 3: Passenger info
    const [passengerName, setPassengerName] = useState('');
    const [passengerPhone, setPassengerPhone] = useState('');
    const [documentType, setDocumentType] = useState('CNI');
    const [documentNumber, setDocumentNumber] = useState('');
    const [formErrors, setFormErrors] = useState<{ [key: string]: string }>({});

    // Step 4: Payment
    const [paymentMethod, setPaymentMethod] = useState<'CASH' | 'MOBILE_MONEY'>(
        'CASH',
    );
    const [receivedAmount, setReceivedAmount] = useState('10000');
    const [mobileMoneyRef, setMobileMoneyRef] = useState('');
    const [completedSale, setCompletedSale] = useState<PointSaleRecord | null>(
        null,
    );

    const passengerCount = selectedSeats.length;
    const totalAmount = selectedDeparture.unit_price * passengerCount;
    const isExceeded =
        totalAmount > health.available_balance || !health.sales_allowed;

    // Available seat options
    const availableSeatOptions = [
        '04',
        '08',
        '11',
        '12',
        '14',
        '18',
        '22',
        '24',
        '28',
    ];

    const handleSeatToggle = (seat: string) => {
        if (selectedSeats.includes(seat)) {
            if (selectedSeats.length > 1) {
                setSelectedSeats(selectedSeats.filter((s) => s !== seat));
            }
        } else {
            setSelectedSeats([...selectedSeats, seat]);
        }
    };

    const validatePassengerStep = () => {
        const errors: { [key: string]: string } = {};

        if (!passengerName.trim()) {
            errors.passengerName =
                'Le prénom et nom du voyageur sont obligatoires.';
        }

        if (!passengerPhone.trim()) {
            errors.passengerPhone =
                'Le numéro de téléphone est obligatoire pour le billet.';
        }

        setFormErrors(errors);

        return Object.keys(errors).length === 0;
    };

    const handleNextToPayment = () => {
        if (validatePassengerStep()) {
            setStep(4);
        }
    };

    const handleConfirmSale = () => {
        if (isExceeded) {
            return;
        }

        const record = onSaleCompleted({
            passengerName: passengerName || 'Passager Guichet',
            passengerPhone: passengerPhone || '+229 97 00 00 00',
            departureRef: selectedDeparture.ref,
            serviceClass: selectedDeparture.service_class,
            departureTime: selectedDeparture.departure_time,
            routeLabel: `${selectedOrigin} → ${selectedDestination}`,
            originCity: selectedOrigin,
            destinationCity: selectedDestination,
            seatNumber: selectedSeats.join(', '),
            amountPaid: totalAmount,
            paymentMethod,
        });

        setCompletedSale(record);
    };

    const resetSaleForm = () => {
        setStep(1);
        setSelectedSeats(['12']);
        setPassengerName('');
        setPassengerPhone('');
        setCompletedSale(null);
    };

    return (
        <div className="space-y-6">
            {/* Success Dialog */}
            <KoraPointSaleSuccessDialog
                saleRecord={completedSale}
                onClose={() => setCompletedSale(null)}
                onNewSale={resetSaleForm}
            />

            {/* Top Navigation & Stepper */}
            <div className="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={onBackToDashboard}
                        className="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        title="Retour à l'accueil"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[0.625rem] font-bold text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                Guichet Partenaire
                            </span>
                            <span className="text-xs text-slate-400">
                                Étape {step} sur 4
                            </span>
                        </div>
                        <h1 className="text-lg font-black text-slate-900 sm:text-xl dark:text-white">
                            {step === 1 && '1. Choix du voyage et départ'}
                            {step === 2 && '2. Sélection des places'}
                            {step === 3 && '3. Coordonnées des passagers'}
                            {step === 4 && '4. Encaissement guichet'}
                        </h1>
                    </div>
                </div>

                {/* Progress Indicators */}
                <div className="flex items-center gap-1.5 sm:gap-2">
                    {[
                        { num: 1, label: 'Trajet' },
                        { num: 2, label: 'Places' },
                        { num: 3, label: 'Passagers' },
                        { num: 4, label: 'Paiement' },
                    ].map((s) => (
                        <button
                            key={s.num}
                            type="button"
                            onClick={() =>
                                s.num < step && setStep(s.num as any)
                            }
                            disabled={s.num > step}
                            className={cn(
                                'flex items-center gap-1.5 rounded-xl px-2.5 py-1 text-xs font-bold transition',
                                step === s.num
                                    ? 'bg-[var(--kora)] text-white dark:bg-digital dark:text-kora'
                                    : s.num < step
                                      ? 'cursor-pointer bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                      : 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800',
                            )}
                        >
                            <span>{s.num < step ? '✓' : s.num}</span>
                            <span className="hidden sm:inline">{s.label}</span>
                        </button>
                    ))}
                </div>
            </div>

            {/* Layout Grid */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.3fr_0.7fr]">
                {/* Main Interactive Step Area */}
                <div className="space-y-6">
                    {/* STEP 1: Itinerary and Departures */}
                    {step === 1 && (
                        <div className="space-y-5">
                            {/* Route Search Inputs */}
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <span className="text-[0.625rem] font-black tracking-wider text-slate-500 uppercase">
                                    Recherche par segment
                                </span>
                                <h3 className="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                                    Où voyage le client ?
                                </h3>

                                <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <MapPin className="size-3.5 text-slate-400" />
                                            <span>Ville de départ *</span>
                                        </label>
                                        <select
                                            value={selectedOrigin}
                                            onChange={(e) =>
                                                setSelectedOrigin(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        >
                                            <option value="Cotonou">
                                                Cotonou (Siège)
                                            </option>
                                            <option value="Allada">
                                                Allada
                                            </option>
                                            <option value="Bohicon">
                                                Bohicon
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <MapPin className="size-3.5 text-slate-400" />
                                            <span>Destination *</span>
                                        </label>
                                        <select
                                            value={selectedDestination}
                                            onChange={(e) =>
                                                setSelectedDestination(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        >
                                            <option value="Parakou">
                                                Parakou
                                            </option>
                                            <option value="Kandi">Kandi</option>
                                            <option value="Malanville">
                                                Malanville
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <Calendar className="size-3.5 text-slate-400" />
                                            <span>Date du départ *</span>
                                        </label>
                                        <input
                                            type="date"
                                            value={travelDate}
                                            onChange={(e) =>
                                                setTravelDate(e.target.value)
                                            }
                                            className="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Departure Cards */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                                        3 départs disponibles aujourd'hui
                                    </h3>
                                    <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                        Prix garanti au guichet
                                    </span>
                                </div>

                                {MOCK_DEPARTURES.map((dep) => {
                                    const isSelected =
                                        selectedDeparture.uuid === dep.uuid;

                                    return (
                                        <button
                                            key={dep.uuid}
                                            type="button"
                                            onClick={() =>
                                                setSelectedDeparture(dep)
                                            }
                                            className={cn(
                                                'flex w-full flex-col justify-between rounded-2xl border p-4 text-left transition sm:flex-row sm:items-center',
                                                isSelected
                                                    ? 'border-gold bg-gold/5 shadow-md ring-1 ring-gold dark:bg-gold/10'
                                                    : 'border-slate-200/90 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900',
                                            )}
                                        >
                                            <div className="flex items-center gap-4">
                                                <div className="text-center sm:text-left">
                                                    <span className="font-mono text-base font-black text-slate-900 dark:text-white">
                                                        {dep.departure_time}
                                                    </span>
                                                    <span className="block text-[0.625rem] text-slate-400">
                                                        Arrivée{' '}
                                                        {dep.arrival_time}
                                                    </span>
                                                </div>

                                                <div className="border-l border-slate-200 pl-4 dark:border-slate-800">
                                                    <div className="flex items-center gap-2">
                                                        <strong className="text-xs font-bold text-slate-900 dark:text-white">
                                                            {dep.ref}
                                                        </strong>
                                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[0.625rem] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                            {dep.service_class}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {dep.origin} →{' '}
                                                        {dep.destination} (
                                                        {dep.duration})
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="mt-3 flex items-center justify-between sm:mt-0 sm:flex-col sm:items-end">
                                                <div className="text-right">
                                                    <span className="font-mono text-base font-black text-slate-900 dark:text-white">
                                                        {dep.unit_price.toLocaleString(
                                                            'fr-FR',
                                                        )}{' '}
                                                        FCFA
                                                    </span>
                                                    <span className="block text-[0.625rem] text-emerald-600 dark:text-emerald-400">
                                                        {dep.available_seats}{' '}
                                                        places libres
                                                    </span>
                                                </div>

                                                <div className="mt-2">
                                                    <span
                                                        className={cn(
                                                            'rounded-lg px-3 py-1 text-xs font-bold',
                                                            isSelected
                                                                ? 'bg-gold text-kora'
                                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                                        )}
                                                    >
                                                        {isSelected
                                                            ? 'Sélectionné'
                                                            : 'Choisir'}
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>

                            <div className="flex justify-end pt-2">
                                <button
                                    type="button"
                                    onClick={() => setStep(2)}
                                    className="inline-flex items-center gap-2 rounded-xl bg-gold px-5 py-2.5 text-xs font-black tracking-wider text-kora uppercase shadow-md transition hover:bg-gold/90"
                                >
                                    <span>Choisir les places</span>
                                    <ArrowRight className="size-4" />
                                </button>
                            </div>
                        </div>
                    )}

                    {/* STEP 2: Seat Map Selection */}
                    {step === 2 && (
                        <div className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div>
                                <span className="text-[0.625rem] font-black tracking-wider text-slate-500 uppercase">
                                    Plan de bus · {selectedDeparture.ref}
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    Sélectionnez les sièges pour ce voyage
                                </h3>
                                <p className="text-xs text-slate-500">
                                    {selectedSeats.length} place
                                    {selectedSeats.length > 1 ? 's' : ''}{' '}
                                    sélectionnée
                                    {selectedSeats.length > 1 ? 's' : ''} :{' '}
                                    <strong className="font-mono text-slate-900 dark:text-white">
                                        {selectedSeats.join(', ')}
                                    </strong>
                                </p>
                            </div>

                            {/* Simplified Responsive Seat Grid */}
                            <div className="rounded-2xl border border-slate-200/80 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950">
                                <div className="mb-4 text-center text-xs font-bold tracking-widest text-slate-400 uppercase">
                                    Avant du car (Chauffeur)
                                </div>

                                <div className="mx-auto grid max-w-xs grid-cols-4 gap-3">
                                    {availableSeatOptions.map((seat) => {
                                        const isSelected =
                                            selectedSeats.includes(seat);

                                        return (
                                            <button
                                                key={seat}
                                                type="button"
                                                onClick={() =>
                                                    handleSeatToggle(seat)
                                                }
                                                className={cn(
                                                    'grid h-12 place-items-center rounded-xl border font-mono text-xs font-extrabold transition',
                                                    isSelected
                                                        ? 'border-gold bg-gold font-black text-kora shadow-md'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
                                                )}
                                            >
                                                <span>{seat}</span>
                                            </button>
                                        );
                                    })}
                                </div>

                                <div className="mt-6 flex items-center justify-center gap-6 text-xs text-slate-500">
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-3 rounded bg-gold" />
                                        Sélectionné
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-3 rounded border border-slate-200 bg-white dark:bg-slate-800" />
                                        Disponible
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-2">
                                <button
                                    type="button"
                                    onClick={() => setStep(1)}
                                    className="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white"
                                >
                                    ← Changer le départ
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setStep(3)}
                                    className="inline-flex items-center gap-2 rounded-xl bg-gold px-5 py-2.5 text-xs font-black tracking-wider text-kora uppercase shadow-md transition hover:bg-gold/90"
                                >
                                    <span>Renseigner les passagers</span>
                                    <ArrowRight className="size-4" />
                                </button>
                            </div>
                        </div>
                    )}

                    {/* STEP 3: Passenger Information */}
                    {step === 3 && (
                        <div className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div>
                                <span className="text-[0.625rem] font-black tracking-wider text-slate-500 uppercase">
                                    Coordonnées obligatoires
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    Informations du voyageur principal (Siège{' '}
                                    {selectedSeats[0]})
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Le nom et le téléphone sont obligatoires
                                    pour l'embarquement et la réception du code
                                    court.
                                </p>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <User className="size-3.5 text-slate-400" />
                                        <span>Nom et prénom du voyageur</span>
                                        <span
                                            className="text-rose-500"
                                            title="Champ obligatoire"
                                        >
                                            *
                                        </span>
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Ex: Bio Kora Seidou"
                                        value={passengerName}
                                        onChange={(e) => {
                                            setPassengerName(e.target.value);

                                            if (formErrors.passengerName) {
                                                setFormErrors({
                                                    ...formErrors,
                                                    passengerName: '',
                                                });
                                            }
                                        }}
                                        className={cn(
                                            'mt-1.5 h-10 w-full rounded-xl border bg-white px-3 text-xs font-semibold text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white',
                                            formErrors.passengerName
                                                ? 'border-rose-500 focus:ring-rose-500'
                                                : 'border-slate-200 dark:border-slate-700',
                                        )}
                                    />
                                    {formErrors.passengerName && (
                                        <p className="mt-1 text-[0.6875rem] font-bold text-rose-500">
                                            {formErrors.passengerName}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Phone className="size-3.5 text-slate-400" />
                                        <span>Téléphone (Mobile)</span>
                                        <span
                                            className="text-rose-500"
                                            title="Champ obligatoire"
                                        >
                                            *
                                        </span>
                                    </label>
                                    <input
                                        type="tel"
                                        placeholder="+229 97 00 00 00"
                                        value={passengerPhone}
                                        onChange={(e) => {
                                            setPassengerPhone(e.target.value);

                                            if (formErrors.passengerPhone) {
                                                setFormErrors({
                                                    ...formErrors,
                                                    passengerPhone: '',
                                                });
                                            }
                                        }}
                                        className={cn(
                                            'mt-1.5 h-10 w-full rounded-xl border bg-white px-3 text-xs font-semibold text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white',
                                            formErrors.passengerPhone
                                                ? 'border-rose-500 focus:ring-rose-500'
                                                : 'border-slate-200 dark:border-slate-700',
                                        )}
                                    />
                                    {formErrors.passengerPhone && (
                                        <p className="mt-1 text-[0.6875rem] font-bold text-rose-500">
                                            {formErrors.passengerPhone}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <FileBadge className="size-3.5 text-slate-400" />
                                            <span>
                                                Type de document (Optionnel)
                                            </span>
                                        </label>
                                        <select
                                            value={documentType}
                                            onChange={(e) =>
                                                setDocumentType(e.target.value)
                                            }
                                            className="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        >
                                            <option value="CNI">
                                                Carte d'Identité Nationale (CNI)
                                            </option>
                                            <option value="Passeport">
                                                Passeport
                                            </option>
                                            <option value="CIP">
                                                Certificat CIP
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <span>Numéro de pièce</span>
                                        </label>
                                        <input
                                            type="text"
                                            placeholder="Ex: 012345678"
                                            value={documentNumber}
                                            onChange={(e) =>
                                                setDocumentNumber(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-2">
                                <button
                                    type="button"
                                    onClick={() => setStep(2)}
                                    className="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white"
                                >
                                    ← Modifier les places
                                </button>

                                <button
                                    type="button"
                                    onClick={handleNextToPayment}
                                    className="inline-flex items-center gap-2 rounded-xl bg-gold px-5 py-2.5 text-xs font-black tracking-wider text-kora uppercase shadow-md transition hover:bg-gold/90"
                                >
                                    <span>Procéder au paiement</span>
                                    <ArrowRight className="size-4" />
                                </button>
                            </div>
                        </div>
                    )}

                    {/* STEP 4: Counter Payment */}
                    {step === 4 && (
                        <div className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div>
                                <span className="text-[0.625rem] font-black tracking-wider text-slate-500 uppercase">
                                    Encaissement immédiat
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    Modalité de règlement par le client
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Montant à percevoir :{' '}
                                    <strong className="font-mono text-sm text-slate-900 dark:text-white">
                                        {totalAmount.toLocaleString('fr-FR')}{' '}
                                        FCFA
                                    </strong>
                                </p>
                            </div>

                            {/* Payment Method Selector */}
                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onClick={() => setPaymentMethod('CASH')}
                                    className={cn(
                                        'flex items-center gap-3 rounded-2xl border p-4 text-left transition',
                                        paymentMethod === 'CASH'
                                            ? 'border-gold bg-gold/10 font-bold text-kora ring-1 ring-gold dark:text-gold'
                                            : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200',
                                    )}
                                >
                                    <Banknote className="size-5 shrink-0" />
                                    <div>
                                        <div className="text-xs font-black">
                                            Espèces
                                        </div>
                                        <div className="text-[0.625rem] text-slate-500">
                                            Paiement guichet
                                        </div>
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    onClick={() =>
                                        setPaymentMethod('MOBILE_MONEY')
                                    }
                                    className={cn(
                                        'flex items-center gap-3 rounded-2xl border p-4 text-left transition',
                                        paymentMethod === 'MOBILE_MONEY'
                                            ? 'border-gold bg-gold/10 font-bold text-kora ring-1 ring-gold dark:text-gold'
                                            : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200',
                                    )}
                                >
                                    <Smartphone className="size-5 shrink-0" />
                                    <div>
                                        <div className="text-xs font-black">
                                            Mobile Money
                                        </div>
                                        <div className="text-[0.625rem] text-slate-500">
                                            MTN / Moov Money
                                        </div>
                                    </div>
                                </button>
                            </div>

                            {/* Payment specific details */}
                            {paymentMethod === 'CASH' ? (
                                <div className="space-y-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-950">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="text-[0.6875rem] font-bold text-slate-600 dark:text-slate-300">
                                                Montant remis par le voyageur :
                                            </label>
                                            <input
                                                type="number"
                                                value={receivedAmount}
                                                onChange={(e) =>
                                                    setReceivedAmount(
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 font-mono text-xs font-bold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                        </div>
                                        <div>
                                            <label className="text-[0.6875rem] font-bold text-slate-600 dark:text-slate-300">
                                                Monnaie à rendre :
                                            </label>
                                            <div className="mt-1 flex h-9 items-center rounded-lg bg-white px-3 font-mono text-xs font-black text-emerald-600 shadow-inner dark:bg-slate-900 dark:text-emerald-400">
                                                {Math.max(
                                                    0,
                                                    Number(
                                                        receivedAmount || 0,
                                                    ) - totalAmount,
                                                ).toLocaleString('fr-FR')}{' '}
                                                FCFA
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-2 rounded-xl bg-slate-50 p-4 dark:bg-slate-950">
                                    <label className="text-[0.6875rem] font-bold text-slate-600 dark:text-slate-300">
                                        Référence de la transaction Mobile Money
                                        :
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Ex: MPX-260801-987654"
                                        value={mobileMoneyRef}
                                        onChange={(e) =>
                                            setMobileMoneyRef(e.target.value)
                                        }
                                        className="h-9 w-full rounded-lg border border-slate-200 px-3 font-mono text-xs font-bold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                    <p className="text-[0.6875rem] text-slate-500">
                                        Contrôle visuel obligatoire du SMS de
                                        confirmation sur le terminal du Point.
                                    </p>
                                </div>
                            )}

                            {/* Final Action */}
                            <div className="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setStep(3)}
                                    className="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white"
                                >
                                    ← Modifier les passagers
                                </button>

                                <button
                                    type="button"
                                    onClick={handleConfirmSale}
                                    disabled={isExceeded}
                                    className="inline-flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-6 text-xs font-black tracking-wider text-white uppercase shadow-lg transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 dark:disabled:bg-slate-800"
                                >
                                    <Check className="size-4" />
                                    <span>Valider et émettre le billet</span>
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Right Column: Journey Summary & Capacity Card */}
                <div className="space-y-5">
                    {/* Journey recap */}
                    <div className="space-y-3 rounded-2xl border border-slate-200/90 bg-white p-5 text-xs shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="border-b border-slate-100 pb-2 dark:border-slate-800">
                            <span className="text-[0.625rem] font-black tracking-wider text-slate-400 uppercase">
                                Récapitulatif voyage
                            </span>
                            <div className="text-sm font-extrabold text-slate-900 dark:text-white">
                                {selectedOrigin} → {selectedDestination}
                            </div>
                            <div className="text-[0.6875rem] text-slate-500">
                                {travelDate} · Départ{' '}
                                {selectedDeparture.departure_time}
                            </div>
                        </div>

                        <div className="space-y-1.5 text-slate-600 dark:text-slate-300">
                            <div className="flex justify-between">
                                <span>Départ :</span>
                                <strong className="text-slate-900 dark:text-white">
                                    {selectedDeparture.ref} (
                                    {selectedDeparture.service_class})
                                </strong>
                            </div>
                            <div className="flex justify-between">
                                <span>Places :</span>
                                <strong className="font-mono text-slate-900 dark:text-white">
                                    {selectedSeats.join(', ')} (
                                    {selectedSeats.length} passager
                                    {selectedSeats.length > 1 ? 's' : ''})
                                </strong>
                            </div>
                            <div className="flex justify-between">
                                <span>Prix unitaire :</span>
                                <span className="font-mono">
                                    {selectedDeparture.unit_price.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-2 text-sm font-black text-slate-900 dark:border-slate-800 dark:text-white">
                            <span>Total à payer :</span>
                            <span className="font-mono text-base text-gold">
                                {totalAmount.toLocaleString('fr-FR')} FCFA
                            </span>
                        </div>
                    </div>

                    {/* Financial Capacity Evaluation Card */}
                    <KoraPointSaleCapacityCard
                        point={point}
                        health={health}
                        totalSaleAmount={totalAmount}
                        passengerCount={passengerCount}
                    />
                </div>
            </div>
        </div>
    );
}

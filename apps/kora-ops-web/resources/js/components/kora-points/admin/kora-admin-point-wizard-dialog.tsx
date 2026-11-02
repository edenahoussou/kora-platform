import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Building2,
    Calendar,
    CheckCircle2,
    Coins,
    FileText,
    Mail,
    MapPin,
    Phone,
    Shield,
    Store,
    UploadCloud,
    User,
    X,
} from 'lucide-react';
import { useState } from 'react';
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import 'react-phone-number-input/style.css';
import { cn } from '@/lib/utils';

type Country = 'BJ' | 'TG' | 'CI';
type SettlementFrequency = 'daily' | 'weekly' | 'monthly';

type WizardSubmission = {
    name: string;
    type: 'AUTONOMOUS' | 'ORGANIZATION_ATTACHED';
    organizationUuid?: string;
    country: Country;
    city: string;
    department: string;
    address: string;
    managerName: string;
    phone: string | null;
    email: string;
    creditLimit: number;
    commissionRateBps?: number;
    settlementFrequency?: SettlementFrequency;
    documentFile?: File | null;
};

type WizardOrganization = {
    uuid: string;
    name: string;
    country?: Country;
    status?: string;
    allocated_credit_limit?: number;
    default_terms: {
        commission_rate_bps: number;
        credit_limit: number;
        settlement_frequency_days: number;
    };
    settlement_calendar?: { frequency?: string } | null;
};

type Props = {
    isOpen: boolean;
    organizations: WizardOrganization[];
    partnerOrganization?: WizardOrganization;
    onClose: () => void;
    onSubmit: (data: WizardSubmission) => void;
};

const countries: { code: Country; label: string }[] = [
    { code: 'BJ', label: 'Bénin' },
    { code: 'TG', label: 'Togo' },
    { code: 'CI', label: 'Côte d’Ivoire' },
];

const frequencies: { value: SettlementFrequency; label: string }[] = [
    { value: 'daily', label: 'Quotidien' },
    { value: 'weekly', label: 'Hebdomadaire' },
    { value: 'monthly', label: 'Mensuel' },
];

const fieldClass =
    'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white';

export function KoraAdminPointWizardDialog({
    isOpen,
    organizations,
    partnerOrganization,
    onClose,
    onSubmit,
}: Props) {
    const [step, setStep] = useState<1 | 2 | 3 | 4>(1);
    const [pointType, setPointType] = useState<
        'AUTONOMOUS' | 'ORGANIZATION_ATTACHED'
    >(partnerOrganization ? 'ORGANIZATION_ATTACHED' : 'AUTONOMOUS');
    const [selectedOrgUuid, setSelectedOrgUuid] = useState(
        partnerOrganization?.uuid ??
            organizations.find(
                (organization) =>
                    organization.status === undefined ||
                    organization.status === 'active',
            )?.uuid ??
            '',
    );
    const [country, setCountry] = useState<Country>('BJ');
    const [name, setName] = useState('');
    const [city, setCity] = useState('');
    const [department, setDepartment] = useState('');
    const [address, setAddress] = useState('');
    const [commissionRate, setCommissionRate] = useState('3');
    const [creditLimit, setCreditLimit] = useState('');
    const [frequency, setFrequency] = useState<SettlementFrequency>('weekly');
    const [managerName, setManagerName] = useState('');
    const [phone, setPhone] = useState('');
    const [email, setEmail] = useState('');
    const [documentFile, setDocumentFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);

    const selectedOrganization = organizations.find(
        (organization) => organization.uuid === selectedOrgUuid,
    );
    const effectiveCountry =
        pointType === 'ORGANIZATION_ATTACHED'
            ? (selectedOrganization?.country ?? 'BJ')
            : country;

    if (!isOpen) {
        return null;
    }

    const validateStep = (): boolean => {
        setError(null);

        if (step === 1 && pointType === 'ORGANIZATION_ATTACHED') {
            if (!selectedOrganization) {
                setError('Sélectionnez une organisation active.');

                return false;
            }

            if (
                selectedOrganization.default_terms.commission_rate_bps ==
                    null ||
                selectedOrganization.default_terms.credit_limit < 1 ||
                selectedOrganization.settlement_calendar === null
            ) {
                setError(
                    'Complétez les conditions commerciales de l’organisation avant de lui rattacher un Point.',
                );

                return false;
            }
        }

        if (step === 2) {
            if (!name.trim() || !city.trim()) {
                setError('Le nom du Point et sa ville sont obligatoires.');

                return false;
            }
        }

        if (step === 3) {
            const amount = Number(creditLimit);

            if (!Number.isInteger(amount) || amount < 1) {
                setError('L’allocation du Point doit être un montant positif.');

                return false;
            }

            if (pointType === 'ORGANIZATION_ATTACHED' && selectedOrganization) {
                const remaining =
                    selectedOrganization.default_terms.credit_limit -
                    (selectedOrganization.allocated_credit_limit ?? 0);

                if (amount > remaining) {
                    setError(
                        'Cette allocation dépasse le plafond encore disponible pour l’organisation.',
                    );

                    return false;
                }
            } else {
                const rate = Number(commissionRate);

                if (!Number.isFinite(rate) || rate < 0 || rate > 100) {
                    setError(
                        'La commission doit être comprise entre 0 et 100 %.',
                    );

                    return false;
                }
            }
        }

        if (step === 4) {
            if (!managerName.trim() || !email.trim()) {
                setError(
                    'Le nom du gérant et son e-mail de connexion sont obligatoires.',
                );

                return false;
            }

            if (phone.trim() && !isValidPhoneNumber(phone)) {
                setError('Vérifiez le numéro de téléphone ou laissez-le vide.');

                return false;
            }
        }

        return true;
    };

    const goNext = () => {
        if (!validateStep()) {
            return;
        }

        setStep((current) => Math.min(4, current + 1) as 1 | 2 | 3 | 4);
    };

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!validateStep()) {
            return;
        }

        onSubmit({
            name: name.trim(),
            type: partnerOrganization ? 'ORGANIZATION_ATTACHED' : pointType,
            organizationUuid:
                partnerOrganization || pointType === 'ORGANIZATION_ATTACHED'
                    ? (partnerOrganization?.uuid ?? selectedOrgUuid)
                    : undefined,
            country: effectiveCountry,
            city: city.trim(),
            department: department.trim(),
            address: address.trim(),
            managerName: managerName.trim(),
            phone: phone || null,
            email: email.trim().toLowerCase(),
            creditLimit: Number(creditLimit),
            ...(pointType === 'AUTONOMOUS'
                ? {
                      commissionRateBps: Math.round(
                          Number(commissionRate) * 100,
                      ),
                      settlementFrequency: frequency,
                      documentFile,
                  }
                : {}),
        });
    };

    const stepLabels = partnerOrganization
        ? (['Organisation', 'Point', 'Conditions', 'Agent'] as const)
        : (['Mode', 'Point', 'Conditions', 'Gérant'] as const);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="point-wizard-title"
        >
            <div className="relative my-6 w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <div className="flex items-center gap-3">
                        <span className="text-gold-dark flex size-10 items-center justify-center rounded-xl bg-gold/15 dark:text-gold">
                            <Store className="size-5" />
                        </span>
                        <div>
                            <h2
                                id="point-wizard-title"
                                className="text-base font-black text-slate-900 dark:text-white"
                            >
                                Déployer un nouveau Point de vente KORA
                            </h2>
                            <p className="text-xs text-slate-500">
                                Étape {step} sur 4 ·{' '}
                                {partnerOrganization
                                    ? `Organisation : ${partnerOrganization.name}`
                                    : 'Administration du réseau'}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        aria-label="Fermer"
                    >
                        <X className="size-5" />
                    </button>
                </header>

                <div className="grid grid-cols-4 border-b border-slate-200 text-center text-xs font-bold dark:border-slate-800">
                    {stepLabels.map((label, index) => (
                        <div
                            key={label}
                            className={cn(
                                'border-b-2 py-2.5',
                                step >= index + 1
                                    ? 'border-gold text-slate-900 dark:text-white'
                                    : 'border-transparent text-slate-400',
                            )}
                        >
                            {index + 1}. {label}
                        </div>
                    ))}
                </div>

                <form onSubmit={handleSubmit} className="space-y-5 p-6">
                    {error && (
                        <div className="flex items-center gap-2 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                            <AlertCircle className="size-4 shrink-0 text-rose-600" />
                            <span>{error}</span>
                        </div>
                    )}

                    {step === 1 && (
                        <section className="space-y-4">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    {partnerOrganization
                                        ? 'Organisation de rattachement'
                                        : 'Choisissez d’abord le mode d’exploitation'}
                                </h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    {partnerOrganization
                                        ? 'Le nouveau Point sera rattaché à votre organisation et héritera de son pays et de ses conditions.'
                                        : 'Le rattachement détermine le pays et les conditions commerciales du Point.'}
                                </p>
                            </div>
                            {!partnerOrganization && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {(
                                        [
                                            {
                                                value: 'AUTONOMOUS',
                                                title: 'Point autonome',
                                                detail: 'Le Point porte ses propres conditions et reversements.',
                                                Icon: Store,
                                            },
                                            {
                                                value: 'ORGANIZATION_ATTACHED',
                                                title: 'Rattaché à une organisation',
                                                detail: 'Le Point hérite du pays et des conditions de l’organisation.',
                                                Icon: Building2,
                                            },
                                        ] as const
                                    ).map(({ value, title, detail, Icon }) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => setPointType(value)}
                                            aria-pressed={pointType === value}
                                            className={cn(
                                                'rounded-2xl border-2 p-4 text-left transition-colors',
                                                pointType === value
                                                    ? 'border-gold bg-gold/5 dark:bg-gold/10'
                                                    : 'border-slate-200 hover:border-slate-300 dark:border-slate-800',
                                            )}
                                        >
                                            <span className="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                                                <Icon className="text-gold-dark size-5 dark:text-gold" />
                                                {title}
                                            </span>
                                            <span className="mt-2 block text-xs text-slate-500">
                                                {detail}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {pointType === 'ORGANIZATION_ATTACHED' ? (
                                <label className="block space-y-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Building2 className="size-4 text-slate-500" />
                                        Organisation partenaire
                                        <b className="text-rose-600">*</b>
                                    </span>
                                    <select
                                        value={selectedOrgUuid}
                                        onChange={(event) =>
                                            setSelectedOrgUuid(
                                                event.target.value,
                                            )
                                        }
                                        className={fieldClass}
                                        disabled={
                                            partnerOrganization !== undefined
                                        }
                                        required
                                    >
                                        <option value="">
                                            Sélectionner une organisation
                                        </option>
                                        {organizations
                                            .filter(
                                                (organization) =>
                                                    organization.status ===
                                                        undefined ||
                                                    organization.status ===
                                                        'active',
                                            )
                                            .map((organization) => (
                                                <option
                                                    key={organization.uuid}
                                                    value={organization.uuid}
                                                >
                                                    {organization.name} ·{' '}
                                                    {organization.country}
                                                </option>
                                            ))}
                                    </select>
                                    {selectedOrganization && (
                                        <p className="text-xs text-slate-500">
                                            Pays hérité :{' '}
                                            {countries.find(
                                                (item) =>
                                                    item.code ===
                                                    selectedOrganization.country,
                                            )?.label ??
                                                selectedOrganization.country}
                                        </p>
                                    )}
                                </label>
                            ) : (
                                <label className="block space-y-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <MapPin className="size-4 text-slate-500" />
                                        Pays du Point
                                        <b className="text-rose-600">*</b>
                                    </span>
                                    <select
                                        value={country}
                                        onChange={(event) =>
                                            setCountry(
                                                event.target.value as Country,
                                            )
                                        }
                                        className={fieldClass}
                                    >
                                        {countries.map((item) => (
                                            <option
                                                key={item.code}
                                                value={item.code}
                                            >
                                                {item.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            <p className="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-800">
                                BJ, TG et CI sont disponibles en XOF. Le Ghana
                                sera proposé quand la devise GHS sera prise en
                                charge.
                            </p>
                        </section>
                    )}

                    {step === 2 && (
                        <section className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="space-y-1.5 sm:col-span-2">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Store className="text-gold-dark size-4 dark:text-gold" />
                                        Nom commercial du Point{' '}
                                        <b className="text-rose-600">*</b>
                                    </span>
                                    <input
                                        value={name}
                                        onChange={(event) =>
                                            setName(event.target.value)
                                        }
                                        placeholder="Ex. Kiosque Étoile Rouge"
                                        className={fieldClass}
                                        required
                                    />
                                </label>
                                <label className="space-y-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <MapPin className="size-4 text-slate-500" />
                                        Ville <b className="text-rose-600">*</b>
                                    </span>
                                    <input
                                        value={city}
                                        onChange={(event) =>
                                            setCity(event.target.value)
                                        }
                                        placeholder="Ville d’implantation"
                                        className={fieldClass}
                                        required
                                    />
                                </label>
                                <label className="space-y-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <MapPin className="size-4 text-slate-500" />
                                        Département / région
                                    </span>
                                    <input
                                        value={department}
                                        onChange={(event) =>
                                            setDepartment(event.target.value)
                                        }
                                        placeholder="Facultatif"
                                        className={fieldClass}
                                    />
                                </label>
                                <label className="space-y-1.5 sm:col-span-2">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <MapPin className="size-4 text-slate-500" />
                                        Adresse / repère
                                    </span>
                                    <input
                                        value={address}
                                        onChange={(event) =>
                                            setAddress(event.target.value)
                                        }
                                        placeholder="Quartier, rue ou repère géographique"
                                        className={fieldClass}
                                    />
                                </label>
                            </div>
                            {pointType === 'ORGANIZATION_ATTACHED' && (
                                <p className="rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                                    Le pays est hérité de l’organisation :{' '}
                                    {effectiveCountry}.
                                </p>
                            )}
                        </section>
                    )}

                    {step === 3 && (
                        <section className="space-y-4">
                            {pointType === 'ORGANIZATION_ATTACHED' &&
                            selectedOrganization ? (
                                <>
                                    <div className="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 text-sm text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                                        <strong className="flex items-center gap-2">
                                            <Shield className="size-4" />
                                            Conditions héritées de{' '}
                                            {selectedOrganization.name}
                                        </strong>
                                        <p className="mt-2">
                                            Commission{' '}
                                            {(
                                                selectedOrganization
                                                    .default_terms
                                                    .commission_rate_bps / 100
                                            ).toLocaleString('fr-FR', {
                                                maximumFractionDigits: 2,
                                            })}
                                            % ·{' '}
                                            {frequencies.find(
                                                (item) =>
                                                    item.value ===
                                                    selectedOrganization
                                                        .settlement_calendar
                                                        ?.frequency,
                                            )?.label ??
                                                frequencies.find(
                                                    (item) =>
                                                        item.value ===
                                                        (selectedOrganization
                                                            .default_terms
                                                            .settlement_frequency_days ===
                                                        1
                                                            ? 'daily'
                                                            : selectedOrganization
                                                                    .default_terms
                                                                    .settlement_frequency_days ===
                                                                30
                                                              ? 'monthly'
                                                              : 'weekly'),
                                                )?.label}{' '}
                                            · Plafond global{' '}
                                            {selectedOrganization.default_terms.credit_limit.toLocaleString(
                                                'fr-FR',
                                            )}{' '}
                                            XOF
                                        </p>
                                        <p className="mt-1 text-xs">
                                            L’allocation des points actifs :{' '}
                                            {(
                                                selectedOrganization.allocated_credit_limit ??
                                                0
                                            ).toLocaleString('fr-FR')}{' '}
                                            XOF · disponible :{' '}
                                            {Math.max(
                                                0,
                                                selectedOrganization
                                                    .default_terms
                                                    .credit_limit -
                                                    (selectedOrganization.allocated_credit_limit ??
                                                        0),
                                            ).toLocaleString('fr-FR')}{' '}
                                            XOF
                                        </p>
                                    </div>
                                </>
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="space-y-1.5">
                                        <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <Coins className="text-gold-dark size-4 dark:text-gold" />
                                            Commission (%){' '}
                                            <b className="text-rose-600">*</b>
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value={commissionRate}
                                            onChange={(event) =>
                                                setCommissionRate(
                                                    event.target.value,
                                                )
                                            }
                                            className={fieldClass}
                                            required
                                        />
                                    </label>
                                    <label className="space-y-1.5">
                                        <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <Calendar className="size-4 text-slate-500" />
                                            Reversement{' '}
                                            <b className="text-rose-600">*</b>
                                        </span>
                                        <select
                                            value={frequency}
                                            onChange={(event) =>
                                                setFrequency(
                                                    event.target
                                                        .value as SettlementFrequency,
                                                )
                                            }
                                            className={fieldClass}
                                        >
                                            {frequencies.map((item) => (
                                                <option
                                                    key={item.value}
                                                    value={item.value}
                                                >
                                                    {item.label}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                </div>
                            )}
                            <label className="block space-y-1.5">
                                <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <Coins className="text-gold-dark size-4 dark:text-gold" />
                                    {pointType === 'ORGANIZATION_ATTACHED'
                                        ? 'Allocation du plafond pour ce Point (XOF)'
                                        : 'Plafond du Point (XOF)'}
                                    <b className="text-rose-600">*</b>
                                </span>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    value={creditLimit}
                                    onChange={(event) =>
                                        setCreditLimit(event.target.value)
                                    }
                                    className={fieldClass}
                                    required
                                />
                                <small className="block text-xs text-slate-500">
                                    {pointType === 'ORGANIZATION_ATTACHED'
                                        ? 'L’allocation doit être positive et la somme des allocations ne peut pas dépasser le plafond global.'
                                        : 'Le délai de grâce avant suspension reste réglé au niveau global KORA.'}
                                </small>
                            </label>
                        </section>
                    )}

                    {step === 4 && (
                        <section className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="space-y-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <User className="size-4 text-slate-500" />
                                        Gérant / responsable{' '}
                                        <b className="text-rose-600">*</b>
                                    </span>
                                    <input
                                        value={managerName}
                                        onChange={(event) =>
                                            setManagerName(event.target.value)
                                        }
                                        placeholder="Nom et prénom"
                                        className={fieldClass}
                                        required
                                    />
                                </label>
                                <label className="space-y-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Mail className="size-4 text-slate-500" />
                                        E-mail de connexion{' '}
                                        <b className="text-rose-600">*</b>
                                    </span>
                                    <input
                                        type="email"
                                        value={email}
                                        onChange={(event) =>
                                            setEmail(event.target.value)
                                        }
                                        placeholder="agent@exemple.com"
                                        className={fieldClass}
                                        required
                                    />
                                </label>
                                <label className="space-y-1.5 sm:col-span-2">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Phone className="size-4 text-slate-500" />
                                        Téléphone (facultatif)
                                    </span>
                                    <PhoneInput
                                        className="control phone-field-control"
                                        defaultCountry={effectiveCountry}
                                        international
                                        labels={phoneLabels}
                                        value={phone || undefined}
                                        onChange={(value) =>
                                            setPhone(value ?? '')
                                        }
                                        numberInputProps={{
                                            autoComplete: 'tel',
                                            'aria-label':
                                                'Téléphone facultatif du responsable',
                                        }}
                                    />
                                    <small className="block text-xs text-slate-500">
                                        L’accès provisoire est envoyé par
                                        e-mail. Un SMS est utilisé uniquement en
                                        cas d’échec de l’e-mail.
                                    </small>
                                </label>
                            </div>

                            {pointType === 'AUTONOMOUS' ? (
                                <label className="block space-y-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <UploadCloud className="size-4 text-slate-500" />
                                        Document du Point autonome (facultatif)
                                    </span>
                                    <input
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png"
                                        onChange={(event) =>
                                            setDocumentFile(
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        className={fieldClass}
                                    />
                                    <small className="flex items-center gap-1 text-xs text-slate-500">
                                        <FileText className="size-3.5" />
                                        PDF, JPG ou PNG, 10 Mo maximum. Le
                                        document sera rattaché au Point.
                                    </small>
                                </label>
                            ) : (
                                <p className="rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                                    Les documents légaux et contractuels sont
                                    déposés sur le profil de l’organisation. Ce
                                    Point n’a pas de dossier séparé.
                                </p>
                            )}
                        </section>
                    )}

                    <footer className="flex items-center justify-between border-t border-slate-100 pt-4 dark:border-slate-800">
                        {step > 1 ? (
                            <button
                                type="button"
                                onClick={() =>
                                    setStep(
                                        (current) =>
                                            Math.max(1, current - 1) as
                                                1 | 2 | 3 | 4,
                                    )
                                }
                                className="btn btn-ghost"
                            >
                                <ArrowLeft className="size-4" />
                                Précédent
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={onClose}
                                className="btn btn-ghost"
                            >
                                Annuler
                            </button>
                        )}
                        {step < 4 ? (
                            <button
                                type="button"
                                onClick={goNext}
                                className="btn btn-primary"
                            >
                                Suivant
                                <ArrowRight className="size-4" />
                            </button>
                        ) : (
                            <button type="submit" className="btn btn-primary">
                                <CheckCircle2 className="size-4" />
                                Finaliser le déploiement
                            </button>
                        )}
                    </footer>
                </form>
            </div>
        </div>
    );
}

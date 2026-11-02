import { Form, router } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CalendarClock,
    ChevronDown,
    Coins,
    Image as ImageIcon,
    Landmark,
    Mail,
    MapPin,
    Percent,
    Phone,
    Store,
    Tag,
    Upload,
    User,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PhoneInput, {
    isValidPhoneNumber,
    parsePhoneNumber,
} from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import 'react-phone-number-input/style.css';
import { cn } from '@/lib/utils';
import koraPoints from '@/routes/kora-points';
import type { AdminOrganization, AdminPoint } from './kora-admin-ui';

type Props = {
    open: boolean;
    point?: AdminPoint | null;
    organizations: AdminOrganization[];
    partnerMode?: boolean;
    onOpenChange: (open: boolean) => void;
};

export function KoraPointFormDialog({
    open,
    point,
    organizations,
    partnerMode = false,
    onOpenChange,
}: Props) {
    const [frequency, setFrequency] = useState(
        point?.terms?.settlement_calendar?.frequency ?? '',
    );
    const [commissionRate, setCommissionRate] = useState(
        point?.terms?.commission_basis_points != null
            ? String(point.terms.commission_basis_points / 100)
            : '',
    );
    const [selectedOrganizationUuid, setSelectedOrganizationUuid] = useState(
        point?.organization_uuid ?? '',
    );
    const [country, setCountry] = useState<'BJ' | 'TG' | 'CI'>(
        point?.country === 'TG' || point?.country === 'CI'
            ? point.country
            : 'BJ',
    );
    const [department, setDepartment] = useState(point?.department ?? '');
    const [phone, setPhone] = useState(() => {
        try {
            return (
                parsePhoneNumber(point?.phone ?? '')?.number ??
                point?.phone ??
                ''
            );
        } catch {
            return point?.phone ?? '';
        }
    });
    const [email, setEmail] = useState('');
    const [documentFile, setDocumentFile] = useState<File | null>(null);
    const [documentTitle, setDocumentTitle] = useState('');
    const [documentError, setDocumentError] = useState<string | null>(null);
    const [documentUploading, setDocumentUploading] = useState(false);
    const logoInputRef = useRef<HTMLInputElement>(null);
    const logoObjectUrl = useRef<string | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(
        point?.logo_url ?? null,
    );
    const [logoRemoved, setLogoRemoved] = useState(false);
    const [clientSubmissionError, setClientSubmissionError] = useState<
        string | null
    >(null);
    const [previewKey, setPreviewKey] = useState<string | null>(null);
    const dialogKey = open ? `open:${point?.uuid ?? 'new'}` : 'closed';

    if (previewKey !== dialogKey) {
        setPreviewKey(dialogKey);
        setLogoPreview(point?.logo_url ?? null);
        setLogoRemoved(false);
        setClientSubmissionError(null);
        setSelectedOrganizationUuid(point?.organization_uuid ?? '');
        setCountry(
            point?.country === 'TG' || point?.country === 'CI'
                ? point.country
                : 'BJ',
        );
        setDepartment(point?.department ?? '');

        try {
            setPhone(
                parsePhoneNumber(point?.phone ?? '')?.number ??
                    point?.phone ??
                    '',
            );
        } catch {
            setPhone(point?.phone ?? '');
        }

        setDocumentFile(null);
        setDocumentTitle('');
        setEmail('');
    }

    useEffect(() => {
        return () => {
            if (logoObjectUrl.current) {
                URL.revokeObjectURL(logoObjectUrl.current);
            }
        };
    }, []);
    const isEditing = Boolean(point);
    const inlineErrorKeys = new Set([
        'name',
        'city',
        'country',
        'department',
        'code',
        'address',
        'logo',
        'responsible_name',
        'email',
        'phone',
        'organization_uuid',
        'commission_basis_points',
        'ceiling_amount',
        'settlement_calendar',
        'settlement_calendar.frequency',
        'settlement_calendar.time',
        'settlement_calendar.timezone',
        'settlement_calendar.weekday',
        'settlement_calendar.day',
    ]);

    const [isDragging, setIsDragging] = useState(false);
    const [logoClientError, setLogoClientError] = useState<string | null>(null);

    const selectLogo = (file: File | null) => {
        if (logoObjectUrl.current) {
            URL.revokeObjectURL(logoObjectUrl.current);
            logoObjectUrl.current = null;
        }

        if (file) {
            logoObjectUrl.current = URL.createObjectURL(file);
            setLogoPreview(logoObjectUrl.current);
            setLogoRemoved(false);
        }
    };

    const acceptLogo = (file: File | null) => {
        setLogoClientError(null);

        if (!file) {
            return;
        }

        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            setLogoClientError('Format accepté : PNG, JPEG ou WebP.');

            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            setLogoClientError('Le logo ne peut pas dépasser 2 Mo.');

            return;
        }

        if (logoInputRef.current) {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            logoInputRef.current.files = transfer.files;
        }

        selectLogo(file);
    };

    const clearLogo = () => {
        selectLogo(null);
        setLogoPreview(null);
        setLogoClientError(null);
        setLogoRemoved(Boolean(point?.logo_url));

        if (logoInputRef.current) {
            logoInputRef.current.value = '';
        }
    };
    const title = isEditing
        ? 'Modifier le point de vente'
        : 'Nouveau point de vente';
    const description = isEditing
        ? 'Mettez à jour les informations du point KORA POINT.'
        : 'Créez un point de vente partenaire. Le code agence est généré automatiquement si laissé vide.';
    const selectedOrganization = organizations.find(
        (organization) => organization.uuid === selectedOrganizationUuid,
    );
    const isAttached = Boolean(selectedOrganizationUuid);
    const phoneInvalid = Boolean(phone) && !isValidPhoneNumber(phone);
    const form = isEditing
        ? koraPoints.update.form({ point: point!.uuid })
        : koraPoints.store.form();

    if (!open) {
        return null;
    }

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <Form
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                noValidate
                onSuccess={() => onOpenChange(false)}
                onBefore={() => {
                    if (phoneInvalid) {
                        setClientSubmissionError(
                            'Le numéro de téléphone est invalide. Corrigez-le avant d’enregistrer.',
                        );

                        return false;
                    } else {
                        setClientSubmissionError(null);
                    }
                }}
                className="cash-modal cash-opening-modal w-[42rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-point-modal-title"
            >
                {({ processing, errors, progress }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Store className="size-5" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="section-kicker">
                                            Réseau commercial · KORA POINT
                                        </span>
                                        {isEditing && point?.code && (
                                            <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                                {point.code}
                                            </span>
                                        )}
                                    </div>
                                    <h3 id="kora-point-modal-title">{title}</h3>
                                    <p>{description}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => onOpenChange(false)}
                                className="cash-modal-close"
                                aria-label="Fermer"
                                disabled={processing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        {(clientSubmissionError ||
                            Object.entries(errors).some(
                                ([key]) => !inlineErrorKeys.has(key),
                            )) && (
                            <div
                                className="mt-4 flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3"
                                role="alert"
                            >
                                <AlertCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                                <ul className="space-y-0.5 text-xs font-semibold text-destructive">
                                    {clientSubmissionError && (
                                        <li>{clientSubmissionError}</li>
                                    )}
                                    {Object.entries(errors)
                                        .filter(
                                            ([key]) =>
                                                !inlineErrorKeys.has(key),
                                        )
                                        .map(([key, message]) => (
                                            <li key={key}>{message}</li>
                                        ))}
                                </ul>
                            </div>
                        )}

                        <div className="cash-opening-list">
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Store className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Identité du point</strong>
                                        <small>
                                            Dénomination et localisation du
                                            guichet partenaire
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field sm:col-span-2">
                                        <span className="flex items-center gap-1.5">
                                            <Store className="size-3.5 text-gold" />
                                            Nom du point{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            name="name"
                                            className="control"
                                            defaultValue={point?.name ?? ''}
                                            placeholder="Guichet Akpakpa Centre"
                                            required
                                            aria-invalid={Boolean(errors.name)}
                                        />
                                        {errors.name && (
                                            <small className="field-error-text">
                                                {errors.name}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="size-3.5 text-gold" />
                                            Ville{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            name="city"
                                            className="control"
                                            defaultValue={point?.city ?? ''}
                                            placeholder="Cotonou"
                                            required
                                            aria-invalid={Boolean(errors.city)}
                                        />
                                        {errors.city && (
                                            <small className="field-error-text">
                                                {errors.city}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="size-3.5 text-gold" />
                                            Pays
                                        </span>
                                        <select
                                            value={
                                                selectedOrganization?.country ??
                                                country
                                            }
                                            onChange={(event) =>
                                                setCountry(
                                                    event.target.value as
                                                        'BJ' | 'TG' | 'CI',
                                                )
                                            }
                                            disabled={isAttached}
                                            className="control"
                                            aria-label="Pays du Point"
                                            aria-invalid={Boolean(
                                                errors.country,
                                            )}
                                        >
                                            <option value="BJ">
                                                Bénin (XOF)
                                            </option>
                                            <option value="TG">
                                                Togo (XOF)
                                            </option>
                                            <option value="CI">
                                                Côte d’Ivoire (XOF)
                                            </option>
                                        </select>
                                        {isAttached ? (
                                            <input
                                                type="hidden"
                                                name="country"
                                                value={
                                                    selectedOrganization?.country ??
                                                    point?.country ??
                                                    'BJ'
                                                }
                                            />
                                        ) : (
                                            <input
                                                type="hidden"
                                                name="country"
                                                value={country}
                                            />
                                        )}
                                        {errors.country && (
                                            <small
                                                className="field-error-text"
                                                role="alert"
                                            >
                                                {errors.country}
                                            </small>
                                        )}
                                        {isAttached && (
                                            <small className="text-muted-foreground">
                                                Hérité de l’organisation.
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="size-3.5 text-gold" />
                                            Département / région
                                        </span>
                                        <input
                                            type="text"
                                            name="department"
                                            value={department}
                                            onChange={(event) =>
                                                setDepartment(
                                                    event.target.value,
                                                )
                                            }
                                            className="control"
                                            placeholder="Facultatif"
                                            aria-invalid={Boolean(
                                                errors.department,
                                            )}
                                        />
                                        {errors.department && (
                                            <small className="field-error-text">
                                                {errors.department}
                                            </small>
                                        )}
                                    </label>

                                    {!partnerMode && (
                                        <label className="field">
                                            <span className="flex items-center gap-1.5">
                                                <Tag className="size-3.5 text-gold" />
                                                Code agence
                                            </span>
                                            <input
                                                type="text"
                                                name="code"
                                                className="control"
                                                defaultValue={point?.code ?? ''}
                                                placeholder="Auto : KP-XXX-0000"
                                                aria-invalid={Boolean(
                                                    errors.code,
                                                )}
                                            />
                                            {errors.code && (
                                                <small className="field-error-text">
                                                    {errors.code}
                                                </small>
                                            )}
                                        </label>
                                    )}

                                    <label className="field sm:col-span-2">
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="size-3.5 text-gold" />
                                            Adresse
                                        </span>
                                        <input
                                            type="text"
                                            name="address"
                                            className="control"
                                            defaultValue={point?.address ?? ''}
                                            placeholder="Quartier, rue, repère"
                                            aria-invalid={Boolean(
                                                errors.address,
                                            )}
                                        />
                                        {errors.address && (
                                            <small className="field-error-text">
                                                {errors.address}
                                            </small>
                                        )}
                                    </label>

                                    <div className="field sm:col-span-2">
                                        <span className="flex items-center gap-1.5">
                                            <ImageIcon className="size-3.5 text-gold" />
                                            Logo
                                        </span>
                                        {isAttached ? (
                                            <>
                                                <small className="mt-2 block rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs leading-relaxed text-muted-foreground">
                                                    Le logo de ce Point se gère
                                                    sur la fiche de son
                                                    organisation.
                                                </small>
                                                {errors.logo && (
                                                    <small
                                                        className="field-error-text"
                                                        role="alert"
                                                    >
                                                        {errors.logo}
                                                    </small>
                                                )}
                                            </>
                                        ) : (
                                            <>
                                                <div
                                                    role="button"
                                                    tabIndex={0}
                                                    aria-label="Zone de dépôt du logo"
                                                    aria-invalid={Boolean(
                                                        errors.logo,
                                                    )}
                                                    onDragOver={(e) => {
                                                        e.preventDefault();
                                                        setIsDragging(true);
                                                    }}
                                                    onDragLeave={() =>
                                                        setIsDragging(false)
                                                    }
                                                    onDrop={(e) => {
                                                        e.preventDefault();
                                                        setIsDragging(false);
                                                        acceptLogo(
                                                            e.dataTransfer
                                                                .files?.[0] ??
                                                                null,
                                                        );
                                                    }}
                                                    onKeyDown={(e) => {
                                                        if (
                                                            e.key === 'Enter' ||
                                                            e.key === ' '
                                                        ) {
                                                            e.preventDefault();
                                                            logoInputRef.current?.click();
                                                        }
                                                    }}
                                                    onClick={() =>
                                                        logoInputRef.current?.click()
                                                    }
                                                    className={cn(
                                                        'mt-2 flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed p-3 transition-colors',
                                                        isDragging
                                                            ? 'border-gold bg-gold/10'
                                                            : 'border-border hover:border-gold/50 hover:bg-muted/30',
                                                    )}
                                                >
                                                    <span className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-border bg-white">
                                                        {logoPreview ? (
                                                            <img
                                                                src={
                                                                    logoPreview
                                                                }
                                                                alt="Logo du point"
                                                                className="h-full w-full object-contain"
                                                            />
                                                        ) : (
                                                            <ImageIcon className="size-5 text-muted-foreground/60" />
                                                        )}
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                                            <Upload className="size-3.5 text-gold" />
                                                            {logoPreview
                                                                ? 'Changer le logo'
                                                                : 'Glissez-déposez le logo ici'}
                                                        </span>
                                                        <span className="text-[0.6875rem] text-muted-foreground">
                                                            ou cliquez pour
                                                            parcourir
                                                        </span>
                                                    </div>
                                                    {logoPreview && (
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-sm shrink-0 text-destructive"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                clearLogo();
                                                            }}
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Retirer
                                                        </button>
                                                    )}
                                                    <input
                                                        ref={logoInputRef}
                                                        type="file"
                                                        name="logo"
                                                        accept="image/png,image/jpeg,image/webp"
                                                        className="hidden"
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                        onChange={(e) =>
                                                            acceptLogo(
                                                                e.target
                                                                    .files?.[0] ??
                                                                    null,
                                                            )
                                                        }
                                                    />
                                                    {logoRemoved && (
                                                        <input
                                                            type="hidden"
                                                            name="remove_logo"
                                                            value="1"
                                                        />
                                                    )}
                                                </div>
                                                {processing && progress && (
                                                    <div
                                                        className="mt-2"
                                                        role="progressbar"
                                                        aria-valuemin={0}
                                                        aria-valuemax={100}
                                                        aria-valuenow={Math.round(
                                                            progress.percentage ??
                                                                0,
                                                        )}
                                                        aria-label="Progression de l’envoi du logo"
                                                    >
                                                        <div className="flex items-center justify-between text-[0.6875rem] font-semibold text-muted-foreground">
                                                            <span>
                                                                Envoi du
                                                                fichier…
                                                            </span>
                                                            <span>
                                                                {Math.round(
                                                                    progress.percentage ??
                                                                        0,
                                                                )}
                                                                {' %'}
                                                            </span>
                                                        </div>
                                                        <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                            <div
                                                                className="h-full rounded-full bg-gradient-to-r from-gold to-[var(--kora)] transition-all duration-300 ease-out"
                                                                style={{
                                                                    width: `${Math.round(progress.percentage ?? 0)}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                )}
                                                {logoClientError ||
                                                errors.logo ? (
                                                    <small
                                                        className="field-error-text"
                                                        role="alert"
                                                    >
                                                        {logoClientError ??
                                                            errors.logo}
                                                    </small>
                                                ) : (
                                                    <small className="text-muted-foreground">
                                                        PNG, JPEG ou WebP (2 Mo
                                                        max).
                                                        {
                                                            ' Affiché si le Point reste autonome.'
                                                        }
                                                    </small>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Building2 className="size-4" />
                                    </span>
                                    <div>
                                        <strong>
                                            Responsable & rattachement
                                        </strong>
                                        <small>
                                            Gestionnaire du point et
                                            organisation partenaire éventuelle
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {partnerMode ? (
                                        <div className="field">
                                            <span className="flex items-center gap-1.5">
                                                <User className="size-3.5 text-gold" />
                                                Responsable agent
                                            </span>
                                            <strong className="control block">
                                                {point?.responsible_name ||
                                                    'Non renseigné'}
                                            </strong>
                                            {errors.responsible_name && (
                                                <small
                                                    className="field-error-text"
                                                    role="alert"
                                                >
                                                    {errors.responsible_name}
                                                </small>
                                            )}
                                            <small className="text-muted-foreground">
                                                Le changement de compte agent
                                                reste géré par KORA OPS.
                                            </small>
                                        </div>
                                    ) : (
                                        <label className="field">
                                            <span className="flex items-center gap-1.5">
                                                <User className="size-3.5 text-gold" />
                                                Nom du responsable{' '}
                                                <b className="font-black text-destructive">
                                                    *
                                                </b>
                                            </span>
                                            <input
                                                type="text"
                                                name="responsible_name"
                                                className="control"
                                                defaultValue={
                                                    point?.responsible_name ??
                                                    ''
                                                }
                                                placeholder="Jean Dossou"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.responsible_name,
                                                )}
                                            />
                                            {errors.responsible_name && (
                                                <small className="field-error-text">
                                                    {errors.responsible_name}
                                                </small>
                                            )}
                                        </label>
                                    )}

                                    <label className="field">
                                        <span className="flex items-center gap-1.5">
                                            <Phone className="size-3.5 text-gold" />
                                            Téléphone (facultatif)
                                        </span>
                                        <PhoneInput
                                            className={cn(
                                                'control phone-field-control',
                                                (phoneInvalid ||
                                                    errors.phone) &&
                                                    'phone-field-control-invalid',
                                            )}
                                            defaultCountry={
                                                selectedOrganization?.country ??
                                                country
                                            }
                                            international
                                            labels={phoneLabels}
                                            value={phone || undefined}
                                            onChange={(value) => {
                                                setPhone(value ?? '');
                                                setClientSubmissionError(null);
                                            }}
                                            numberInputProps={{
                                                autoComplete: 'tel',
                                                'aria-invalid':
                                                    phoneInvalid ||
                                                    Boolean(errors.phone),
                                                'aria-describedby':
                                                    errors.phone || phoneInvalid
                                                        ? 'kora-point-phone-error'
                                                        : undefined,
                                            }}
                                        />
                                        <input
                                            type="hidden"
                                            name="phone"
                                            value={phone}
                                        />
                                        {errors.phone || phoneInvalid ? (
                                            <small
                                                id="kora-point-phone-error"
                                                className="field-error-text"
                                                role="alert"
                                            >
                                                {errors.phone ??
                                                    'Numéro de téléphone invalide.'}
                                            </small>
                                        ) : null}
                                    </label>

                                    {!isEditing && (
                                        <label className="field sm:col-span-2">
                                            <span className="flex items-center gap-1.5">
                                                <Mail className="size-3.5 text-gold" />
                                                E-mail de connexion
                                                <b className="font-black text-destructive">
                                                    *
                                                </b>
                                            </span>
                                            <input
                                                type="email"
                                                name="email"
                                                className="control"
                                                value={email}
                                                onChange={(event) =>
                                                    setEmail(event.target.value)
                                                }
                                                placeholder="agent@exemple.com"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.email,
                                                )}
                                            />
                                            {errors.email ? (
                                                <small className="field-error-text">
                                                    {errors.email}
                                                </small>
                                            ) : (
                                                <small className="text-muted-foreground">
                                                    Obligatoire pour un point
                                                    autonome. L’accès est envoyé
                                                    par e-mail, avec SMS en
                                                    repli si l’e-mail échoue.
                                                </small>
                                            )}
                                        </label>
                                    )}

                                    <div className="field sm:col-span-2">
                                        <span className="flex items-center gap-1.5">
                                            <Building2 className="size-3.5 text-gold" />
                                            Organisation partenaire
                                        </span>
                                        {partnerMode ? (
                                            <>
                                                <strong className="control block">
                                                    {selectedOrganization?.name ??
                                                        'Organisation introuvable'}
                                                </strong>
                                                <input
                                                    type="hidden"
                                                    name="organization_uuid"
                                                    value={
                                                        point?.organization_uuid ??
                                                        selectedOrganizationUuid
                                                    }
                                                />
                                                <small className="text-muted-foreground">
                                                    Le Point reste rattaché à
                                                    votre organisation.
                                                </small>
                                                {errors.organization_uuid && (
                                                    <small
                                                        className="field-error-text"
                                                        role="alert"
                                                    >
                                                        {
                                                            errors.organization_uuid
                                                        }
                                                    </small>
                                                )}
                                            </>
                                        ) : (
                                            <>
                                                <div className="relative">
                                                    <select
                                                        name="organization_uuid"
                                                        className="control w-full appearance-none"
                                                        value={
                                                            selectedOrganizationUuid
                                                        }
                                                        onChange={(event) => {
                                                            const nextUuid =
                                                                event.target
                                                                    .value;
                                                            setSelectedOrganizationUuid(
                                                                nextUuid,
                                                            );
                                                            const nextOrganization =
                                                                organizations.find(
                                                                    (org) =>
                                                                        org.uuid ===
                                                                        nextUuid,
                                                                );

                                                            if (
                                                                nextOrganization
                                                            ) {
                                                                setCountry(
                                                                    nextOrganization.country,
                                                                );
                                                            }
                                                        }}
                                                        aria-invalid={Boolean(
                                                            errors.organization_uuid,
                                                        )}
                                                    >
                                                        <option value="">
                                                            Point autonome
                                                            (aucune
                                                            organisation)
                                                        </option>
                                                        {organizations
                                                            .filter(
                                                                (org) =>
                                                                    org.status ===
                                                                    'active',
                                                            )
                                                            .map((org) => (
                                                                <option
                                                                    key={
                                                                        org.uuid
                                                                    }
                                                                    value={
                                                                        org.uuid
                                                                    }
                                                                >
                                                                    {org.name} (
                                                                    {org.code})
                                                                </option>
                                                            ))}
                                                    </select>
                                                    <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                </div>
                                                {errors.organization_uuid ? (
                                                    <small className="field-error-text">
                                                        {
                                                            errors.organization_uuid
                                                        }
                                                    </small>
                                                ) : (
                                                    <small className="text-muted-foreground">
                                                        Le point hérite du logo
                                                        et des conditions cadres
                                                        de l’organisation
                                                        choisie.
                                                    </small>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Coins className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Conditions commerciales</strong>
                                        <small>
                                            {isAttached
                                                ? 'La commission et le reversement sont définis par l’organisation.'
                                                : 'Conditions commerciales du Point autonome.'}
                                        </small>
                                    </div>
                                </div>

                                {isAttached && selectedOrganization && (
                                    <div className="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                                        Héritées de {selectedOrganization.name}{' '}
                                        :{' '}
                                        {(
                                            selectedOrganization.default_terms
                                                .commission_rate_bps / 100
                                        ).toLocaleString('fr-FR', {
                                            maximumFractionDigits: 2,
                                        })}
                                        % de commission · reversement{' '}
                                        {selectedOrganization
                                            .settlement_calendar?.frequency ??
                                            'défini par l’organisation'}
                                        .
                                    </div>
                                )}

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {!isAttached && (
                                        <label className="field">
                                            <span className="flex items-center gap-1.5">
                                                <Percent className="size-3.5 text-gold" />
                                                Commission (%)
                                            </span>
                                            <input
                                                type="number"
                                                min={0}
                                                max={100}
                                                step="0.01"
                                                className="control"
                                                value={commissionRate}
                                                onChange={(e) =>
                                                    setCommissionRate(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="ex : 3,5"
                                                aria-invalid={Boolean(
                                                    errors.commission_basis_points,
                                                )}
                                            />
                                            <input
                                                type="hidden"
                                                name="commission_basis_points"
                                                value={
                                                    commissionRate === ''
                                                        ? ''
                                                        : String(
                                                              Math.round(
                                                                  Number(
                                                                      commissionRate,
                                                                  ) * 100,
                                                              ),
                                                          )
                                                }
                                            />
                                            {errors.commission_basis_points ? (
                                                <small className="field-error-text">
                                                    {
                                                        errors.commission_basis_points
                                                    }
                                                </small>
                                            ) : (
                                                <small className="text-muted-foreground">
                                                    Pourcentage conservé par le
                                                    point sur chaque vente.
                                                </small>
                                            )}
                                        </label>
                                    )}

                                    <label className="field">
                                        <span className="flex items-center gap-1.5">
                                            <Landmark className="size-3.5 text-gold" />
                                            {partnerMode
                                                ? 'Allocation de plafond du Point (XOF)'
                                                : 'Plafond (FCFA)'}
                                        </span>
                                        <input
                                            type="number"
                                            name="ceiling_amount"
                                            min={isAttached ? 1 : 0}
                                            className="control"
                                            defaultValue={
                                                point?.terms?.ceiling_amount ??
                                                ''
                                            }
                                            placeholder="1 500 000"
                                            aria-invalid={Boolean(
                                                errors.ceiling_amount,
                                            )}
                                        />
                                        {errors.ceiling_amount ? (
                                            <small className="field-error-text">
                                                {errors.ceiling_amount}
                                            </small>
                                        ) : (
                                            <small className="text-muted-foreground">
                                                Montant maximal dû à KORA avant
                                                blocage des ventes.
                                            </small>
                                        )}
                                    </label>
                                </div>

                                {!isAttached && (
                                    <div className="field mt-4">
                                        <span className="flex items-center gap-1.5">
                                            <CalendarClock className="size-3.5 text-gold" />
                                            Reversements
                                        </span>
                                        <div
                                            className="mt-1 flex flex-wrap items-center gap-x-5 gap-y-2"
                                            role="radiogroup"
                                            aria-label="Fréquence de reversement"
                                            aria-invalid={Boolean(
                                                errors.settlement_calendar ||
                                                errors[
                                                    'settlement_calendar.frequency'
                                                ] ||
                                                errors[
                                                    'settlement_calendar.time'
                                                ] ||
                                                errors[
                                                    'settlement_calendar.timezone'
                                                ] ||
                                                errors[
                                                    'settlement_calendar.weekday'
                                                ] ||
                                                errors[
                                                    'settlement_calendar.day'
                                                ],
                                            )}
                                        >
                                            {(
                                                [
                                                    {
                                                        value: '',
                                                        label: 'Valeur par défaut',
                                                    },
                                                    {
                                                        value: 'daily',
                                                        label: 'Quotidien',
                                                    },
                                                    {
                                                        value: 'weekly',
                                                        label: 'Hebdomadaire',
                                                    },
                                                    {
                                                        value: 'monthly',
                                                        label: 'Mensuel',
                                                    },
                                                ] as const
                                            ).map((option) => (
                                                <label
                                                    key={
                                                        option.value ||
                                                        'default'
                                                    }
                                                    className="flex cursor-pointer items-center gap-2 text-xs font-semibold text-foreground"
                                                >
                                                    <input
                                                        type="radio"
                                                        name="settlement_frequency_choice"
                                                        checked={
                                                            frequency ===
                                                            option.value
                                                        }
                                                        onChange={() =>
                                                            setFrequency(
                                                                option.value,
                                                            )
                                                        }
                                                        className="size-3.5 accent-[var(--kora)]"
                                                    />
                                                    {option.label}
                                                </label>
                                            ))}
                                        </div>
                                        {frequency !== '' && (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="settlement_calendar[frequency]"
                                                    value={frequency}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="settlement_calendar[time]"
                                                    value="18:00"
                                                />
                                                <input
                                                    type="hidden"
                                                    name="settlement_calendar[timezone]"
                                                    value="Africa/Porto-Novo"
                                                />
                                                {frequency === 'weekly' && (
                                                    <input
                                                        type="hidden"
                                                        name="settlement_calendar[weekday]"
                                                        value="1"
                                                    />
                                                )}
                                                {frequency === 'monthly' && (
                                                    <input
                                                        type="hidden"
                                                        name="settlement_calendar[day]"
                                                        value="1"
                                                    />
                                                )}
                                            </>
                                        )}
                                        {errors.settlement_calendar ||
                                        errors[
                                            'settlement_calendar.frequency'
                                        ] ||
                                        errors['settlement_calendar.time'] ||
                                        errors[
                                            'settlement_calendar.timezone'
                                        ] ||
                                        errors['settlement_calendar.weekday'] ||
                                        errors['settlement_calendar.day'] ? (
                                            <small
                                                className="field-error-text"
                                                role="alert"
                                            >
                                                {errors.settlement_calendar ??
                                                    errors[
                                                        'settlement_calendar.frequency'
                                                    ] ??
                                                    errors[
                                                        'settlement_calendar.time'
                                                    ] ??
                                                    errors[
                                                        'settlement_calendar.timezone'
                                                    ] ??
                                                    errors[
                                                        'settlement_calendar.weekday'
                                                    ] ??
                                                    errors[
                                                        'settlement_calendar.day'
                                                    ]}
                                            </small>
                                        ) : (
                                            <small className="text-muted-foreground">
                                                Rythme de reversement des
                                                encaissements à KORA.
                                            </small>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {isAttached ? (
                            <p className="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                                Les documents de ce Point se gèrent sur le
                                profil de son organisation.
                            </p>
                        ) : isEditing && point ? (
                            <div className="mt-4 rounded-xl border border-border p-4">
                                <div className="mb-3">
                                    <strong className="flex items-center gap-2 text-sm text-foreground">
                                        <Upload className="size-4 text-gold" />
                                        Documents du Point autonome
                                    </strong>
                                    <small className="text-muted-foreground">
                                        PDF, JPG ou PNG · 10 Mo maximum.
                                    </small>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                    <input
                                        type="text"
                                        value={documentTitle}
                                        onChange={(event) =>
                                            setDocumentTitle(event.target.value)
                                        }
                                        className="control"
                                        placeholder="Titre du document"
                                        aria-label="Titre du document"
                                    />
                                    <input
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png"
                                        onChange={(event) =>
                                            setDocumentFile(
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        className="control"
                                        aria-label="Sélectionner un document"
                                    />
                                    <button
                                        type="button"
                                        className="btn btn-primary btn-sm"
                                        disabled={
                                            !documentFile ||
                                            !documentTitle.trim() ||
                                            documentUploading
                                        }
                                        onClick={() => {
                                            if (
                                                !documentFile ||
                                                !documentTitle.trim()
                                            ) {
                                                return;
                                            }

                                            setDocumentError(null);
                                            setDocumentUploading(true);
                                            router.post(
                                                koraPoints.documents.url({
                                                    point: point.uuid,
                                                }),
                                                {
                                                    file: documentFile,
                                                    kind: 'other',
                                                    title: documentTitle.trim(),
                                                },
                                                {
                                                    forceFormData: true,
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        setDocumentFile(null);
                                                        setDocumentTitle('');
                                                    },
                                                    onError: (uploadErrors) =>
                                                        setDocumentError(
                                                            Object.values(
                                                                uploadErrors,
                                                            )[0] ??
                                                                'Le document n’a pas pu être envoyé.',
                                                        ),
                                                    onFinish: () =>
                                                        setDocumentUploading(
                                                            false,
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        <Upload className="size-3.5" />
                                        {documentUploading
                                            ? 'Envoi…'
                                            : 'Ajouter'}
                                    </button>
                                    {documentError && (
                                        <small className="text-destructive sm:col-span-3">
                                            {documentError}
                                        </small>
                                    )}
                                </div>
                            </div>
                        ) : null}

                        <div className="cash-modal-actions mt-6">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => onOpenChange(false)}
                                disabled={processing}
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={processing}
                            >
                                {isEditing
                                    ? 'Enregistrer les modifications'
                                    : 'Créer le point de vente'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

import { Form } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CalendarClock,
    ChevronDown,
    Coins,
    Image as ImageIcon,
    Landmark,
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
import { cn } from '@/lib/utils';
import koraPoints from '@/routes/kora-points';
import type { AdminOrganization, AdminPoint } from './kora-admin-ui';

type Props = {
    open: boolean;
    point?: AdminPoint | null;
    organizations: AdminOrganization[];
    onOpenChange: (open: boolean) => void;
};

export function KoraPointFormDialog({
    open,
    point,
    organizations,
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
    const logoInputRef = useRef<HTMLInputElement>(null);
    const logoObjectUrl = useRef<string | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(
        point?.logo_url ?? null,
    );
    const [logoRemoved, setLogoRemoved] = useState(false);
    const [previewKey, setPreviewKey] = useState<string | null>(null);
    const dialogKey = open ? `open:${point?.uuid ?? 'new'}` : 'closed';

    if (previewKey !== dialogKey) {
        setPreviewKey(dialogKey);
        setLogoPreview(point?.logo_url ?? null);
        setLogoRemoved(false);
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
        'code',
        'address',
        'logo',
        'responsible_name',
        'phone',
        'organization_uuid',
        'commission_basis_points',
        'ceiling_amount',
        'settlement_calendar',
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
                onSuccess={() => onOpenChange(false)}
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

                        {Object.entries(errors).filter(
                            ([key]) => !inlineErrorKeys.has(key),
                        ).length > 0 && (
                            <div className="mt-4 flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3">
                                <AlertCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                                <ul className="space-y-0.5 text-xs font-semibold text-destructive">
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
                                            <Tag className="size-3.5 text-gold" />
                                            Code agence
                                        </span>
                                        <input
                                            type="text"
                                            name="code"
                                            className="control"
                                            defaultValue={point?.code ?? ''}
                                            placeholder="Auto : KP-XXX-0000"
                                            aria-invalid={Boolean(errors.code)}
                                        />
                                        {errors.code && (
                                            <small className="field-error-text">
                                                {errors.code}
                                            </small>
                                        )}
                                    </label>

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
                                        <div
                                            role="button"
                                            tabIndex={0}
                                            aria-label="Zone de dépôt du logo"
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
                                                    e.dataTransfer.files?.[0] ??
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
                                                        src={logoPreview}
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
                                                    ou cliquez pour parcourir
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
                                                    disabled={processing}
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
                                                        e.target.files?.[0] ??
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
                                                    progress.percentage ?? 0,
                                                )}
                                                aria-label="Progression de l’envoi du logo"
                                            >
                                                <div className="flex items-center justify-between text-[0.6875rem] font-semibold text-muted-foreground">
                                                    <span>
                                                        Envoi du fichier…
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
                                        {logoClientError || errors.logo ? (
                                            <small className="text-destructive">
                                                {logoClientError ?? errors.logo}
                                            </small>
                                        ) : (
                                            <small className="text-muted-foreground">
                                                PNG, JPEG ou WebP (2 Mo max).
                                                {point?.organization_uuid
                                                    ? ' Le logo de l’organisation s’affiche sur ce point rattaché.'
                                                    : ' Affiché si le point reste autonome.'}
                                            </small>
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
                                                point?.responsible_name ?? ''
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

                                    <label className="field">
                                        <span className="flex items-center gap-1.5">
                                            <Phone className="size-3.5 text-gold" />
                                            Téléphone{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="tel"
                                            name="phone"
                                            className="control"
                                            defaultValue={point?.phone ?? ''}
                                            placeholder="+229 97 00 00 00"
                                            required
                                            aria-invalid={Boolean(errors.phone)}
                                        />
                                        {errors.phone && (
                                            <small className="field-error-text">
                                                {errors.phone}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field sm:col-span-2">
                                        <span className="flex items-center gap-1.5">
                                            <Building2 className="size-3.5 text-gold" />
                                            Organisation partenaire
                                        </span>
                                        <div className="relative">
                                            <select
                                                name="organization_uuid"
                                                className="control w-full appearance-none"
                                                defaultValue={
                                                    point?.organization_uuid ??
                                                    ''
                                                }
                                                aria-invalid={Boolean(
                                                    errors.organization_uuid,
                                                )}
                                            >
                                                <option value="">
                                                    Point autonome (aucune
                                                    organisation)
                                                </option>
                                                {organizations.map((org) => (
                                                    <option
                                                        key={org.uuid}
                                                        value={org.uuid}
                                                    >
                                                        {org.name} ({org.code})
                                                    </option>
                                                ))}
                                            </select>
                                            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        </div>
                                        {errors.organization_uuid ? (
                                            <small className="field-error-text">
                                                {errors.organization_uuid}
                                            </small>
                                        ) : (
                                            <small className="text-muted-foreground">
                                                Le point hérite du logo et des
                                                conditions cadres de
                                                l’organisation choisie.
                                            </small>
                                        )}
                                    </label>
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
                                            Laisser vide pour hériter des
                                            conditions cadres
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                                {errors.commission_basis_points}
                                            </small>
                                        ) : (
                                            <small className="text-muted-foreground">
                                                Pourcentage conservé par le
                                                point sur chaque vente.
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span className="flex items-center gap-1.5">
                                            <Landmark className="size-3.5 text-gold" />
                                            Plafond (FCFA)
                                        </span>
                                        <input
                                            type="number"
                                            name="ceiling_amount"
                                            min={0}
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

                                <div className="field mt-4">
                                    <span className="flex items-center gap-1.5">
                                        <CalendarClock className="size-3.5 text-gold" />
                                        Reversements
                                    </span>
                                    <div
                                        className="mt-1 flex flex-wrap items-center gap-x-5 gap-y-2"
                                        role="radiogroup"
                                        aria-label="Fréquence de reversement"
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
                                                key={option.value || 'default'}
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
                                    {errors.settlement_calendar ? (
                                        <small className="field-error-text">
                                            {errors.settlement_calendar}
                                        </small>
                                    ) : (
                                        <small className="text-muted-foreground">
                                            Rythme de reversement des
                                            encaissements à KORA.
                                        </small>
                                    )}
                                </div>
                            </div>
                        </div>

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

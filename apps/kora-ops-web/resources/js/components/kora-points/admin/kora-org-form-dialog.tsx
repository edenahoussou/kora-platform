import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CalendarClock,
    Coins,
    Image as ImageIcon,
    Landmark,
    MapPin,
    Percent,
    Phone,
    Tag,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PhoneInput, {
    isValidPhoneNumber,
    parsePhoneNumber,
} from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { cn } from '@/lib/utils';
import organizationRoutes from '@/routes/kora-points/organizations';
import type { AdminOrganization } from './kora-admin-ui';

type Props = {
    open: boolean;
    organization?: AdminOrganization | null;
    onOpenChange: (open: boolean) => void;
};

type OrgFormValues = {
    name: string;
    phone: string;
    address: string;
    logo: File | null;
    remove_logo: boolean;
    commission_rate: string;
    ceiling_amount: string;
    settlement_calendar: {
        frequency: 'daily' | 'weekly' | 'monthly';
        time: string;
        timezone: string;
        weekday: number | null;
        day: number | null;
    } | null;
};

const emptyValues: OrgFormValues = {
    name: '',
    phone: '',
    address: '',
    logo: null,
    remove_logo: false,
    commission_rate: '',
    ceiling_amount: '',
    settlement_calendar: null,
};

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

function valuesFromOrg(org?: AdminOrganization | null): OrgFormValues {
    if (!org) {
        return emptyValues;
    }

    const frequency = org.settlement_calendar?.frequency as
        | 'daily'
        | 'weekly'
        | 'monthly'
        | undefined;
    const settlementCalendar = frequency
        ? {
              frequency,
              time: org.settlement_calendar?.time ?? '18:00',
              timezone: org.settlement_calendar?.timezone ?? 'Africa/Porto-Novo',
              weekday: org.settlement_calendar?.weekday ?? null,
              day: org.settlement_calendar?.day ?? null,
          }
        : null;

    return {
        name: org.name ?? '',
        phone: normalizePhoneValue(org.phone),
        address: org.address ?? '',
        logo: null,
        remove_logo: false,
        commission_rate:
            org.commission_basis_points !== null &&
            org.commission_basis_points !== undefined
                ? String(org.commission_basis_points / 100)
                : '',
        ceiling_amount:
            org.ceiling_amount !== null && org.ceiling_amount !== undefined
                ? String(org.ceiling_amount)
                : '',
        settlement_calendar: settlementCalendar,
    };
}

export function KoraOrgFormDialog({
    open,
    organization,
    onOpenChange,
}: Props) {
    const isEditing = Boolean(organization);
    const form = useForm<OrgFormValues>(valuesFromOrg(organization));
    const logoInputRef = useRef<HTMLInputElement>(null);
    const logoObjectUrl = useRef<string | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(
        organization?.logo_url ?? null,
    );
    const [previewKey, setPreviewKey] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [logoClientError, setLogoClientError] = useState<string | null>(null);
    const dialogKey = open ? `open:${organization?.uuid ?? 'new'}` : 'closed';

    if (previewKey !== dialogKey) {
        setPreviewKey(dialogKey);
        setLogoPreview(organization?.logo_url ?? null);
    }

    useEffect(() => {
        if (open) {
            form.setData(valuesFromOrg(organization));
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, organization?.uuid]);

    useEffect(() => {
        return () => {
            if (logoObjectUrl.current) {
                URL.revokeObjectURL(logoObjectUrl.current);
            }
        };
    }, []);

    if (!open) {
        return null;
    }

    const title = isEditing
        ? 'Modifier l’organisation'
        : 'Nouvelle organisation partenaire';
    const description = isEditing
        ? 'Mettez à jour les informations de l’organisation partenaire.'
        : 'Créez une organisation pour y rattacher des points de vente et piloter leurs conditions cadres.';

    const phoneInvalid =
        Boolean(form.data.phone.trim()) && !isValidPhoneNumber(form.data.phone);
    const phoneError =
        form.errors.phone ??
        (phoneInvalid ? 'Numéro de téléphone invalide.' : undefined);
    const allErrors = form.errors as Record<string, string | undefined>;
    const commissionError = allErrors.commission_basis_points;
    const inlineErrorKeys = new Set([
        'name',
        'phone',
        'address',
        'logo',
        'commission_basis_points',
        'ceiling_amount',
        'settlement_calendar',
    ]);
    const hiddenErrors = Object.entries(allErrors).filter(
        ([key, message]) => Boolean(message) && !inlineErrorKeys.has(key),
    );

    const selectLogo = (file: File | null) => {
        if (logoObjectUrl.current) {
            URL.revokeObjectURL(logoObjectUrl.current);
            logoObjectUrl.current = null;
        }

        if (file) {
            logoObjectUrl.current = URL.createObjectURL(file);
            setLogoPreview(logoObjectUrl.current);
            form.setData('logo', file);
            form.setData('remove_logo', false);
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

        selectLogo(file);
    };

    const clearLogo = () => {
        selectLogo(null);
        setLogoPreview(null);
        setLogoClientError(null);
        form.setData('logo', null);
        form.setData('remove_logo', Boolean(organization?.logo_url));

        if (logoInputRef.current) {
            logoInputRef.current.value = '';
        }
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (phoneInvalid) {
            return;
        }

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => onOpenChange(false),
        };

        form.transform((data) => ({
            name: data.name,
            phone: data.phone,
            address: data.address,
            logo: data.logo,
            remove_logo: data.remove_logo,
            commission_basis_points:
                data.commission_rate === ''
                    ? null
                    : Math.round(Number(data.commission_rate) * 100),
            ceiling_amount:
                data.ceiling_amount === ''
                    ? null
                    : Number(data.ceiling_amount),
            settlement_calendar: data.settlement_calendar,
        }));

        if (isEditing) {
            form.post(
                organizationRoutes.update.form({
                    organization: organization!.uuid,
                }).action,
                options,
            );
        } else {
            form.post(organizationRoutes.store.url(), options);
        }
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <form
                onSubmit={submit}
                className="cash-modal w-[36rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-org-modal-title"
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-gold/15 text-[var(--kora)] ring-1 ring-gold/25">
                            <Building2 className="size-5" />
                        </span>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="section-kicker">
                                    Réseau commercial · Organisations
                                </span>
                                {isEditing && organization?.code && (
                                    <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                        {organization.code}
                                    </span>
                                )}
                            </div>
                            <h3 id="kora-org-modal-title">{title}</h3>
                            <p>{description}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="cash-modal-close"
                        aria-label="Fermer"
                        disabled={form.processing}
                    >
                        <X className="size-4" />
                    </button>
                </div>

                {hiddenErrors.length > 0 && (
                    <div className="mt-4 flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3">
                        <AlertCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                        <ul className="space-y-0.5 text-xs font-semibold text-destructive">
                            {hiddenErrors.map(([key, message]) => (
                                <li key={key}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="mt-5 space-y-4">
                    <label className="field">
                        <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                            <Building2 className="size-3.5 text-gold" />
                            Raison sociale
                            <b className="font-bold text-destructive">*</b>
                        </span>
                        <input
                            type="text"
                            className="control"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="ex : La Poste du Bénin, Coris Bank, MTN..."
                            required
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        {form.errors.name && (
                            <small className="field-error-text">
                                {form.errors.name}
                            </small>
                        )}
                    </label>

                    <label className="field">
                        <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                            <Phone className="size-3.5 text-gold" />
                            Téléphone officiel
                        </span>
                        <PhoneInput
                            className="control phone-field-control"
                            defaultCountry="BJ"
                            international
                            labels={phoneLabels}
                            value={form.data.phone || undefined}
                            onChange={(value) =>
                                form.setData('phone', value ?? '')
                            }
                            numberInputProps={{
                                autoComplete: 'tel',
                                'aria-invalid': Boolean(phoneError),
                            }}
                        />
                        {phoneError && (
                            <small className="field-error-text">
                                {phoneError}
                            </small>
                        )}
                    </label>

                    <label className="field">
                        <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                            <MapPin className="size-3.5 text-gold" />
                            Adresse
                        </span>
                        <input
                            type="text"
                            className="control"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            placeholder="ex : Ganhi, Boulevard de la Marina, Cotonou"
                            aria-invalid={Boolean(form.errors.address)}
                        />
                        {form.errors.address && (
                            <small className="field-error-text">
                                {form.errors.address}
                            </small>
                        )}
                    </label>

                    <div className="field">
                        <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
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
                            onDragLeave={() => setIsDragging(false)}
                            onDrop={(e) => {
                                e.preventDefault();
                                setIsDragging(false);
                                acceptLogo(e.dataTransfer.files?.[0] ?? null);
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    logoInputRef.current?.click();
                                }
                            }}
                            onClick={() => logoInputRef.current?.click()}
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
                                        alt={`Logo de ${form.data.name || 'l’organisation'}`}
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
                                    disabled={form.processing}
                                >
                                    Retirer
                                </button>
                            )}
                            <input
                                ref={logoInputRef}
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                className="hidden"
                                onClick={(e) => e.stopPropagation()}
                                onChange={(e) =>
                                    acceptLogo(e.target.files?.[0] ?? null)
                                }
                            />
                        </div>
                        {form.processing && form.progress && (
                            <div
                                className="mt-2"
                                role="progressbar"
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={Math.round(
                                    form.progress.percentage ?? 0,
                                )}
                                aria-label="Progression de l’envoi du logo"
                            >
                                <div className="flex items-center justify-between text-[0.6875rem] font-semibold text-muted-foreground">
                                    <span>Envoi du fichier…</span>
                                    <span>
                                        {Math.round(
                                            form.progress.percentage ?? 0,
                                        )}
                                        {' %'}
                                    </span>
                                </div>
                                <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-gradient-to-r from-gold to-[var(--kora)] transition-all duration-300 ease-out"
                                        style={{
                                            width: `${Math.round(form.progress.percentage ?? 0)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                        {logoClientError || form.errors.logo ? (
                            <small className="field-error-text">
                                {logoClientError ?? form.errors.logo}
                            </small>
                        ) : (
                            <small className="text-muted-foreground">
                                PNG, JPEG ou WebP (2 Mo max). Affiché sur les
                                points rattachés.
                            </small>
                        )}
                    </div>

                    {isEditing && (
                        <div className="flex items-center justify-between rounded-xl border border-border bg-muted/40 p-3">
                            <div className="flex items-center gap-2.5">
                                <Tag className="size-4 text-muted-foreground" />
                                <div>
                                    <span className="block text-xs font-semibold text-foreground">
                                        Code organisation
                                    </span>
                                    <span className="text-[11px] text-muted-foreground">
                                        Identifiant pérenne généré automatiquement
                                    </span>
                                </div>
                            </div>
                            <span className="rounded-md bg-gold/15 px-2.5 py-1 font-mono text-xs font-bold text-[var(--kora)]">
                                {organization?.code}
                            </span>
                        </div>
                    )}

                    {/* Conditions cadres */}
                    <div className="space-y-3 border-t border-border pt-4">
                        <div className="flex items-center gap-2">
                            <Coins className="size-4 text-gold" />
                            <h4 className="text-xs font-black tracking-wider text-foreground uppercase">
                                Conditions cadres
                            </h4>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label className="field">
                                <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                    <Percent className="size-3.5 text-gold" />
                                    Commission (%)
                                </span>
                                <input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    className="control"
                                    value={form.data.commission_rate}
                                    onChange={(e) =>
                                        form.setData(
                                            'commission_rate',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="ex : 3,5"
                                    aria-invalid={Boolean(commissionError)}
                                />
                                {commissionError ? (
                                    <small className="field-error-text">
                                        {commissionError}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Pourcentage conservé par le partenaire
                                        sur chaque vente.
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                    <Landmark className="size-3.5 text-gold" />
                                    Plafond (FCFA)
                                </span>
                                <input
                                    type="number"
                                    min={0}
                                    className="control"
                                    value={form.data.ceiling_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'ceiling_amount',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="ex : 1 500 000"
                                    aria-invalid={Boolean(
                                        form.errors.ceiling_amount,
                                    )}
                                />
                                {form.errors.ceiling_amount ? (
                                    <small className="field-error-text">
                                        {form.errors.ceiling_amount}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Montant maximal dû à KORA avant blocage
                                        des ventes.
                                    </small>
                                )}
                            </label>
                        </div>

                        <div className="field">
                            <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                <CalendarClock className="size-3.5 text-gold" />
                                Reversements
                            </span>
                            <div
                                className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-2"
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
                                ).map((option) => {
                                    const selected =
                                        (form.data.settlement_calendar
                                            ?.frequency ?? '') === option.value;

                                    return (
                                        <label
                                            key={option.value || 'default'}
                                            className="flex cursor-pointer items-center gap-2 text-xs font-semibold text-foreground"
                                        >
                                            <input
                                                type="radio"
                                                name="settlement_frequency"
                                                checked={selected}
                                                onChange={() =>
                                                    form.setData(
                                                        'settlement_calendar',
                                                        option.value
                                                            ? {
                                                                  frequency:
                                                                      option.value,
                                                                  time: '18:00',
                                                                  timezone:
                                                                      'Africa/Porto-Novo',
                                                                  weekday:
                                                                      option.value ===
                                                                      'weekly'
                                                                          ? 1
                                                                          : null,
                                                                  day:
                                                                      option.value ===
                                                                      'monthly'
                                                                          ? 1
                                                                          : null,
                                                              }
                                                            : null,
                                                    )
                                                }
                                                className="size-3.5 accent-[var(--kora)]"
                                            />
                                            {option.label}
                                        </label>
                                    );
                                })}
                            </div>
                            {form.errors.settlement_calendar ? (
                                <small className="field-error-text">
                                    {form.errors.settlement_calendar}
                                </small>
                            ) : (
                                <small className="text-muted-foreground">
                                    Rythme de reversement des encaissements à
                                    KORA.
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
                        disabled={form.processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={form.processing || Boolean(phoneInvalid)}
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : isEditing
                              ? 'Enregistrer les modifications'
                              : 'Créer l’organisation'}
                    </button>
                </div>
            </form>
        </div>
    );
}

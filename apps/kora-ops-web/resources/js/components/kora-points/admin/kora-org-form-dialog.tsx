import { useForm } from '@inertiajs/react';
import { Building2, MapPin, Phone, Sparkles, Tag, X } from 'lucide-react';
import { useEffect } from 'react';
import PhoneInput, {
    isValidPhoneNumber,
    parsePhoneNumber,
} from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
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
};

const emptyValues: OrgFormValues = {
    name: '',
    phone: '',
    address: '',
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

    return {
        name: org.name ?? '',
        phone: normalizePhoneValue(org.phone),
        address: org.address ?? '',
    };
}

export function KoraOrgFormDialog({
    open,
    organization,
    onOpenChange,
}: Props) {
    const isEditing = Boolean(organization);
    const form = useForm<OrgFormValues>(valuesFromOrg(organization));

    useEffect(() => {
        if (open) {
            form.setData(valuesFromOrg(organization));
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, organization?.uuid]);

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

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (phoneInvalid) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing) {
            form.put(
                organizationRoutes.update.url({
                    organization: organization!.uuid,
                }),
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
                            Siège social & Adresse
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

                    {isEditing ? (
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
                    ) : (
                        <div className="flex items-start gap-3 rounded-xl border border-gold/30 bg-gold/10 p-3.5 text-xs text-foreground">
                            <Sparkles className="mt-0.5 size-4 shrink-0 text-gold" />
                            <div>
                                <strong className="block font-bold text-foreground">
                                    Génération automatique du code
                                </strong>
                                <span className="text-muted-foreground">
                                    Le code organisation (ex : LPDB-01) sera
                                    généré automatiquement à partir de la raison
                                    sociale.
                                </span>
                            </div>
                        </div>
                    )}
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

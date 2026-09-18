import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Check,
    CreditCard,
    Landmark,
    X,
} from 'lucide-react';
import { useEffect } from 'react';
import PhoneInput, {
    isValidPhoneNumber,
    parsePhoneNumber,
} from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { cn } from '@/lib/utils';
import companies from '@/routes/companies';
import type {
    CompanyAccountFormValues,
    CompanyAccountRecord,
} from './company-account-types';
import { formatFcfa } from './company-account-types';

type Props = {
    open: boolean;
    account: CompanyAccountRecord | null;
    onOpenChange: (open: boolean) => void;
};

const emptyValues: CompanyAccountFormValues = {
    name: '',
    type: 'company',
    phone: '',
    email: '',
    address: '',
    city: '',
    country: 'BJ',
    ifu: '',
    rccm: '',
    monthly_credit_limit: '0',
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

function valuesFromAccount(
    account: CompanyAccountRecord | null,
): CompanyAccountFormValues {
    if (!account) {
        return emptyValues;
    }

    return {
        name: account.name,
        type: account.type,
        phone: normalizePhoneValue(account.phone),
        email: account.email ?? '',
        address: account.address ?? '',
        city: account.city ?? '',
        country: account.country ?? 'BJ',
        ifu: account.ifu ?? '',
        rccm: account.rccm ?? '',
        monthly_credit_limit: String(account.monthlyCreditLimit),
    };
}

export function CompanyAccountFormModal({
    open,
    account,
    onOpenChange,
}: Props) {
    const isEditing = account !== null;
    const form = useForm<CompanyAccountFormValues>(valuesFromAccount(account));

    useEffect(() => {
        if (open) {
            form.setData(valuesFromAccount(account));
            form.clearErrors();
        }
        // The modal intentionally resets only when opened for a different account.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, account?.uuid]);

    if (!open) {
        return null;
    }

    const close = () => {
        if (
            form.isDirty &&
            !window.confirm('Fermer sans enregistrer les modifications ?')
        ) {
            return;
        }

        onOpenChange(false);
    };

    const phoneInvalid =
        form.data.phone.trim() !== '' && !isValidPhoneNumber(form.data.phone);
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
                companies.update.url({ companyAccount: account.uuid }),
                options,
            );
        } else {
            form.post(companies.store.url(), options);
        }
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && close()
            }
        >
            <form
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="company-account-modal-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">
                            Référentiel organisations
                        </span>
                        <h3 id="company-account-modal-title">
                            {isEditing
                                ? 'Modifier l’organisation'
                                : 'Ajouter une organisation'}
                        </h3>
                        <p>
                            Centralisez les coordonnées et le crédit mensuel
                            utilisés pour les achats Bus à compte entreprise.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={close}
                        className="cash-modal-close"
                        aria-label="Fermer"
                        disabled={form.processing}
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="cash-opening-list">
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Landmark className="size-4" />
                            </span>
                            <div>
                                <strong>Identité de l’organisation</strong>
                                <small>
                                    Ces informations apparaîtront dans les
                                    ventes et le suivi du crédit.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Type d’organisation <b>*</b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.type}
                                    onChange={(event) =>
                                        form.setData(
                                            'type',
                                            event.target
                                                .value as CompanyAccountFormValues['type'],
                                        )
                                    }
                                    aria-invalid={!!form.errors.type}
                                >
                                    <option value="company">Entreprise</option>
                                    <option value="ngo">ONG</option>
                                    <option value="embassy">Ambassade</option>
                                </select>
                                {form.errors.type && (
                                    <small className="field-error-text">
                                        {form.errors.type}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>
                                    Nom de l’organisation <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder="Société Bénin Transport"
                                    aria-invalid={!!form.errors.name}
                                />
                                {form.errors.name && (
                                    <small className="field-error-text">
                                        {form.errors.name}
                                    </small>
                                )}
                            </label>
                        </div>
                        {isEditing ? (
                            <label className="field">
                                <span>Référence KORA</span>
                                <input
                                    className="control"
                                    value={account.reference}
                                    readOnly
                                />
                                <small>
                                    Cette référence est générée par KORA et
                                    reste stable pour les ventes et les
                                    rapports.
                                </small>
                            </label>
                        ) : (
                            <div className="flex items-start gap-3 rounded-xl bg-gold/10 p-3 text-xs leading-5 text-foreground">
                                <Check className="mt-0.5 size-4 shrink-0 text-gold" />
                                <span>
                                    Une référence métier unique sera générée
                                    automatiquement à la création.
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Building2 className="size-4" />
                            </span>
                            <div>
                                <strong>Coordonnées et dossier légal</strong>
                                <small>
                                    Les champs légaux restent facultatifs si le
                                    dossier est en cours de constitution.
                                </small>
                            </div>
                        </div>
                        <div className="companies-fields-grid">
                            <label className="field">
                                <span>
                                    Téléphone <b>*</b>
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
                                        required: true,
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
                                <span>Email</span>
                                <input
                                    className="control"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="contact@organisation.bj"
                                    aria-invalid={!!form.errors.email}
                                />
                                {form.errors.email && (
                                    <small className="field-error-text">
                                        {form.errors.email}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Adresse</span>
                                <input
                                    className="control"
                                    value={form.data.address}
                                    onChange={(event) =>
                                        form.setData(
                                            'address',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Boulevard de la Marina"
                                    aria-invalid={!!form.errors.address}
                                />
                                {form.errors.address && (
                                    <small className="field-error-text">
                                        {form.errors.address}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Ville</span>
                                <input
                                    className="control"
                                    value={form.data.city}
                                    onChange={(event) =>
                                        form.setData('city', event.target.value)
                                    }
                                    placeholder="Cotonou"
                                    aria-invalid={!!form.errors.city}
                                />
                                {form.errors.city && (
                                    <small className="field-error-text">
                                        {form.errors.city}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Pays</span>
                                <input
                                    className="control uppercase"
                                    value={form.data.country}
                                    maxLength={2}
                                    onChange={(event) =>
                                        form.setData(
                                            'country',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="BJ"
                                    aria-invalid={!!form.errors.country}
                                />
                                {form.errors.country && (
                                    <small className="field-error-text">
                                        {form.errors.country}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Identifiant fiscal</span>
                                <input
                                    className="control"
                                    value={form.data.ifu}
                                    onChange={(event) =>
                                        form.setData('ifu', event.target.value)
                                    }
                                    aria-invalid={!!form.errors.ifu}
                                />
                                {form.errors.ifu && (
                                    <small className="field-error-text">
                                        {form.errors.ifu}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>RCCM</span>
                                <input
                                    className="control"
                                    value={form.data.rccm}
                                    onChange={(event) =>
                                        form.setData('rccm', event.target.value)
                                    }
                                    placeholder="RB/COT/…"
                                    aria-invalid={!!form.errors.rccm}
                                />
                                {form.errors.rccm && (
                                    <small className="field-error-text">
                                        {form.errors.rccm}
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>

                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <CreditCard className="size-4" />
                            </span>
                            <div>
                                <strong>Paramètres de crédit</strong>
                                <small>
                                    Le crédit est calculé par mois civil et ne
                                    se reporte pas au mois suivant.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Plafond mensuel (FCFA) <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    type="number"
                                    min="0"
                                    step="1"
                                    inputMode="numeric"
                                    value={form.data.monthly_credit_limit}
                                    onChange={(event) =>
                                        form.setData(
                                            'monthly_credit_limit',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="500000"
                                    aria-invalid={
                                        !!form.errors.monthly_credit_limit
                                    }
                                />
                                {form.errors.monthly_credit_limit && (
                                    <small className="field-error-text">
                                        {form.errors.monthly_credit_limit}
                                    </small>
                                )}
                            </label>
                        </div>
                        {isEditing && (
                            <div className="companies-fields-grid companies-metrics-grid">
                                <Metric
                                    label="Consommé"
                                    value={account.consumedCredit}
                                />
                                <Metric
                                    label="Réservé"
                                    value={account.reservedCredit}
                                />
                                <Metric
                                    label="Disponible"
                                    value={account.availableCredit}
                                />
                            </div>
                        )}
                        <div className="flex items-start gap-3 rounded-xl bg-digital/10 p-3 text-xs leading-5 text-muted-foreground">
                            <AlertCircle className="mt-0.5 size-4 shrink-0 text-digital" />
                            <span>
                                Les achats à crédit sont refusés dès que le
                                montant disponible est insuffisant. Le plafond
                                ne peut pas être abaissé sous les débits
                                confirmés du mois.
                            </span>
                        </div>
                    </div>
                </div>

                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={close}
                        disabled={form.processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className={cn(
                            'btn btn-primary',
                            form.processing && 'is-disabled',
                        )}
                        disabled={form.processing}
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

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <span className="block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                {label}
            </span>
            <strong className="mt-1 block text-xs font-black text-foreground">
                {formatFcfa(value)}
            </strong>
        </div>
    );
}

import { useForm } from '@inertiajs/react';
import { AlertCircle, IdCard, Syringe, User, X } from 'lucide-react';
import { useEffect } from 'react';
import PhoneInput, {
    isValidPhoneNumber,
    parsePhoneNumber,
} from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { cn } from '@/lib/utils';
import companyPortal from '@/routes/company-portal';
import type {
    CompanyTravelerFormValues,
    CompanyTravelerRecord,
} from './company-traveler-types';
import { emptyTravelerValues } from './company-traveler-types';

type Props = {
    open: boolean;
    traveler: CompanyTravelerRecord | null;
    documentTypes: Record<string, string>;
    onOpenChange: (open: boolean) => void;
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

function valuesFromTraveler(
    traveler: CompanyTravelerRecord | null,
): CompanyTravelerFormValues {
    if (!traveler) {
        return emptyTravelerValues;
    }

    return {
        first_name: traveler.firstName,
        last_name: traveler.lastName,
        phone: normalizePhoneValue(traveler.phone),
        email: traveler.email ?? '',
        document_type: traveler.documentType ?? '',
        document_number: traveler.documentNumber ?? '',
        nationality: traveler.nationality ?? '',
        birth_date: traveler.birthDate ?? '',
        yellow_fever_vaccinated: traveler.yellowFeverVaccinated ?? false,
    };
}

export function CompanyTravelerFormModal({
    open,
    traveler,
    documentTypes,
    onOpenChange,
}: Props) {
    const isEditing = traveler !== null;
    const form = useForm<CompanyTravelerFormValues>(
        valuesFromTraveler(traveler),
    );

    useEffect(() => {
        if (open) {
            form.setData(valuesFromTraveler(traveler));
            form.clearErrors();
        }
        // The modal intentionally resets only when opened for a different traveler.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, traveler?.uuid]);

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
                companyPortal.travelers.update.url({ traveler: traveler.uuid }),
                options,
            );
        } else {
            form.post(companyPortal.travelers.store.url(), options);
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
                aria-labelledby="company-traveler-modal-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">
                            Membres voyageurs
                        </span>
                        <h3 id="company-traveler-modal-title">
                            {isEditing
                                ? 'Modifier le voyageur'
                                : 'Déclarer un voyageur'}
                        </h3>
                        <p>
                            Seuls les voyageurs actifs peuvent être sélectionnés
                            lors d’un achat de billets.
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
                                <User className="size-4" />
                            </span>
                            <div>
                                <strong>Identité du voyageur</strong>
                                <small>
                                    Reprenez les informations telles qu’elles
                                    figurent sur la pièce du voyageur.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Prénom <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.first_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'first_name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Aïcha"
                                    aria-invalid={!!form.errors.first_name}
                                />
                                {form.errors.first_name && (
                                    <small className="field-error-text">
                                        {form.errors.first_name}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>
                                    Nom <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.last_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'last_name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Mensah"
                                    aria-invalid={!!form.errors.last_name}
                                />
                                {form.errors.last_name && (
                                    <small className="field-error-text">
                                        {form.errors.last_name}
                                    </small>
                                )}
                            </label>
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
                                    placeholder="voyageur@exemple.bj"
                                    aria-invalid={!!form.errors.email}
                                />
                                {form.errors.email && (
                                    <small className="field-error-text">
                                        {form.errors.email}
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>

                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <IdCard className="size-4" />
                            </span>
                            <div>
                                <strong>Pièce et nationalité</strong>
                                <small>
                                    Facultatif sur la fiche — exigé à
                                    l’embarquement selon la destination.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>Type de pièce</span>
                                <select
                                    className="control"
                                    value={form.data.document_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'document_type',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.document_type}
                                >
                                    <option value="">Aucun document</option>
                                    {Object.entries(documentTypes).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                {form.errors.document_type && (
                                    <small className="field-error-text">
                                        {form.errors.document_type}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Numéro de la pièce</span>
                                <input
                                    className="control"
                                    value={form.data.document_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'document_number',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.document_number}
                                />
                                {form.errors.document_number && (
                                    <small className="field-error-text">
                                        {form.errors.document_number}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Nationalité (code ISO)</span>
                                <input
                                    className="control uppercase"
                                    value={form.data.nationality}
                                    maxLength={2}
                                    onChange={(event) =>
                                        form.setData(
                                            'nationality',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="BJ"
                                    aria-invalid={!!form.errors.nationality}
                                />
                                {form.errors.nationality && (
                                    <small className="field-error-text">
                                        {form.errors.nationality}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Date de naissance</span>
                                <input
                                    className="control"
                                    type="date"
                                    value={form.data.birth_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'birth_date',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.birth_date}
                                />
                                {form.errors.birth_date && (
                                    <small className="field-error-text">
                                        {form.errors.birth_date}
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>

                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Syringe className="size-4" />
                            </span>
                            <div>
                                <strong>Fièvre jaune</strong>
                                <small>
                                    Le certificat peut être exigé sur les
                                    corridors internationaux.
                                </small>
                            </div>
                        </div>
                        <label className="field flex-row items-center gap-3">
                            <input
                                type="checkbox"
                                className="size-4 accent-gold"
                                checked={form.data.yellow_fever_vaccinated}
                                onChange={(event) =>
                                    form.setData(
                                        'yellow_fever_vaccinated',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>Vacciné contre la fièvre jaune</span>
                        </label>
                        <div className="flex items-start gap-3 rounded-xl bg-digital/10 p-3 text-xs leading-5 text-muted-foreground">
                            <AlertCircle className="mt-0.5 size-4 shrink-0 text-digital" />
                            <span>
                                Le téléphone sert de clé de dédoublonnage parmi
                                vos voyageurs : deux fiches ne peuvent pas
                                partager le même numéro.
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
                              : 'Déclarer le voyageur'}
                    </button>
                </div>
            </form>
        </div>
    );
}

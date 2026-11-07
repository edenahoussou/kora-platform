import { useForm } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import {
    ChevronDown,
    Mail,
    Phone,
    ShieldCheck,
    UserPlus,
    UserRound,
    X,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import PhoneInput from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import 'react-phone-number-input/style.css';
import organizationRoutes from '@/routes/kora-points/organizations';

type MemberForm = {
    name: string;
    email: string;
    phone: string;
    role: 'kora_point_supervisor' | 'kora_point_treasurer' | '';
};

type Props = {
    open: boolean;
    organizationUuid: string;
    organizationName: string;
    country: 'BJ' | 'TG' | 'CI';
    onOpenChange: (open: boolean) => void;
};

export function KoraOrgMemberDialog({
    open,
    organizationUuid,
    organizationName,
    country,
    onOpenChange,
}: Props) {
    const nameInput = useRef<HTMLInputElement>(null);
    const form = useForm<MemberForm>({
        name: '',
        email: '',
        phone: '',
        role: '',
    });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // The form helpers are stable; reset only when the dialog opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const close = (nextOpen: boolean) => {
        if (!form.processing) {
            onOpenChange(nextOpen);
        }
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(
            organizationRoutes.members.store.url({
                organization: organizationUuid,
            }),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    form.reset();
                    onOpenChange(false);
                },
            },
        );
    };

    return (
        <DialogPrimitive.Root open={open} onOpenChange={close}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="cash-modal-backdrop" />
                <DialogPrimitive.Content
                    aria-labelledby="kora-org-member-title"
                    aria-describedby="kora-org-member-description"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        window.requestAnimationFrame(() =>
                            nameInput.current?.focus(),
                        );
                    }}
                    className="cash-modal cash-opening-modal fixed top-1/2 left-1/2 z-[101] w-[40rem]! max-w-[calc(100vw-2rem)]! -translate-x-1/2 -translate-y-1/2 outline-none"
                >
                    <form onSubmit={submit} noValidate>
                        <div className="cash-modal-head">
                            <div className="flex min-w-0 items-start gap-3">
                                <span className="cash-opening-icon">
                                    <UserPlus className="size-5" />
                                </span>
                                <div className="min-w-0">
                                    <span className="section-kicker">
                                        Réseau commercial · Organisation
                                    </span>
                                    <h2 id="kora-org-member-title">
                                        Nouveau membre
                                    </h2>
                                    <p id="kora-org-member-description">
                                        Créez un accès Superviseur ou Trésorier
                                        pour {organizationName}.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                className="cash-modal-close"
                                onClick={() => close(false)}
                                aria-label="Fermer la fenêtre"
                                disabled={form.processing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="cash-opening-list space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="field">
                                    <span className="inline-flex items-center gap-1.5">
                                        <UserRound
                                            className="size-3.5 text-gold"
                                            aria-hidden="true"
                                        />
                                        Nom complet
                                        <span
                                            className="required-asterisk"
                                            aria-hidden="true"
                                        >
                                            *
                                        </span>
                                    </span>
                                    <input
                                        ref={nameInput}
                                        className="control"
                                        autoComplete="name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(form.errors.name)}
                                        aria-describedby={
                                            form.errors.name
                                                ? 'kora-org-member-name-error'
                                                : undefined
                                        }
                                        required
                                    />
                                    {form.errors.name ? (
                                        <small
                                            id="kora-org-member-name-error"
                                            className="field-error-text"
                                        >
                                            {form.errors.name}
                                        </small>
                                    ) : null}
                                </label>

                                <label className="field">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Mail
                                            className="size-3.5 text-gold"
                                            aria-hidden="true"
                                        />
                                        Adresse e-mail
                                        <span
                                            className="required-asterisk"
                                            aria-hidden="true"
                                        >
                                            *
                                        </span>
                                    </span>
                                    <input
                                        className="control"
                                        type="email"
                                        autoComplete="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            form.errors.email,
                                        )}
                                        aria-describedby={
                                            form.errors.email
                                                ? 'kora-org-member-email-error'
                                                : undefined
                                        }
                                        required
                                    />
                                    {form.errors.email ? (
                                        <small
                                            id="kora-org-member-email-error"
                                            className="field-error-text"
                                        >
                                            {form.errors.email}
                                        </small>
                                    ) : null}
                                </label>
                            </div>

                            <label className="field">
                                <span className="inline-flex items-center gap-1.5">
                                    <Phone
                                        className="size-3.5 text-gold"
                                        aria-hidden="true"
                                    />
                                    Téléphone (facultatif)
                                </span>
                                <PhoneInput
                                    className="control phone-field-control"
                                    defaultCountry={country}
                                    international
                                    labels={phoneLabels}
                                    value={form.data.phone || undefined}
                                    onChange={(value) =>
                                        form.setData('phone', value ?? '')
                                    }
                                    placeholder="+229 97 00 00 00"
                                    numberInputProps={{
                                        autoComplete: 'tel',
                                        'aria-invalid': Boolean(
                                            form.errors.phone,
                                        ),
                                        'aria-describedby': form.errors.phone
                                            ? 'kora-org-member-phone-error'
                                            : undefined,
                                    }}
                                />
                                {form.errors.phone ? (
                                    <small
                                        id="kora-org-member-phone-error"
                                        className="field-error-text"
                                    >
                                        {form.errors.phone}
                                    </small>
                                ) : null}
                            </label>

                            <label className="field">
                                <span className="inline-flex items-center gap-1.5">
                                    <ShieldCheck
                                        className="size-3.5 text-gold"
                                        aria-hidden="true"
                                    />
                                    Rôle
                                    <span
                                        className="required-asterisk"
                                        aria-hidden="true"
                                    >
                                        *
                                    </span>
                                </span>
                                <span className="relative block">
                                    <select
                                        className="control w-full appearance-none pr-9"
                                        value={form.data.role}
                                        onChange={(event) =>
                                            form.setData(
                                                'role',
                                                event.target
                                                    .value as MemberForm['role'],
                                            )
                                        }
                                        aria-invalid={Boolean(form.errors.role)}
                                        aria-describedby={
                                            form.errors.role
                                                ? 'kora-org-member-role-error'
                                                : undefined
                                        }
                                        required
                                    >
                                        <option value="" disabled>
                                            Choisir un rôle
                                        </option>
                                        <option value="kora_point_supervisor">
                                            Superviseur
                                        </option>
                                        <option value="kora_point_treasurer">
                                            Trésorier
                                        </option>
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                </span>
                                {form.errors.role ? (
                                    <small
                                        id="kora-org-member-role-error"
                                        className="field-error-text"
                                    >
                                        {form.errors.role}
                                    </small>
                                ) : null}
                            </label>
                        </div>

                        <div className="cash-modal-actions">
                            <button
                                type="button"
                                className="btn btn-navy"
                                onClick={() => close(false)}
                                disabled={form.processing}
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={form.processing}
                            >
                                <UserPlus className="size-4" />
                                {form.processing
                                    ? 'Création…'
                                    : 'Créer et inviter'}
                            </button>
                        </div>
                    </form>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

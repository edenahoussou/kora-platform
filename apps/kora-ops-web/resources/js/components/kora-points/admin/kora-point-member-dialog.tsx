import { useForm } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Mail, Phone, UserPlus, UserRound, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import PhoneInput from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import 'react-phone-number-input/style.css';
import koraPoints from '@/routes/kora-points';

type MemberForm = {
    name: string;
    email: string;
    phone: string;
};

type Props = {
    open: boolean;
    pointUuid: string;
    pointName: string;
    pointCountry: 'BJ' | 'TG' | 'CI';
    onOpenChange: (open: boolean) => void;
    onCreated: () => void;
};

export function KoraPointMemberDialog({
    open,
    pointUuid,
    pointName,
    pointCountry,
    onOpenChange,
    onCreated,
}: Props) {
    const nameInput = useRef<HTMLInputElement>(null);
    const form = useForm<MemberForm>({ name: '', email: '', phone: '' });

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
        form.post(koraPoints.members.store.url({ point: pointUuid }), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.reset();
                onCreated();
                onOpenChange(false);
            },
        });
    };

    return (
        <DialogPrimitive.Root open={open} onOpenChange={close}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="cash-modal-backdrop" />
                <DialogPrimitive.Content
                    aria-labelledby="kora-point-member-title"
                    aria-describedby="kora-point-member-description"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        window.requestAnimationFrame(() =>
                            nameInput.current?.focus(),
                        );
                    }}
                    className="cash-modal cash-opening-modal fixed top-1/2 left-1/2 z-[101] w-[34rem]! max-w-[calc(100vw-2rem)]! -translate-x-1/2 -translate-y-1/2 outline-none"
                >
                    <form onSubmit={submit} noValidate>
                        <div className="cash-modal-head">
                            <div className="flex min-w-0 items-start gap-3">
                                <span className="cash-opening-icon">
                                    <UserPlus className="size-5" />
                                </span>
                                <div className="min-w-0">
                                    <span className="section-kicker">
                                        Réseau commercial · Membres
                                    </span>
                                    <DialogPrimitive.Title
                                        id="kora-point-member-title"
                                        className="m-0 mt-1 text-[21px] leading-tight font-bold tracking-tight"
                                    >
                                        Nouveau membre
                                    </DialogPrimitive.Title>
                                    <DialogPrimitive.Description
                                        id="kora-point-member-description"
                                        className="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                                    >
                                        Créez un compte agent pour {pointName}.
                                        Une invitation de première connexion
                                        sera déclenchée après la création.
                                    </DialogPrimitive.Description>
                                </div>
                            </div>
                            <DialogPrimitive.Close asChild>
                                <button
                                    type="button"
                                    className="cash-modal-close"
                                    aria-label="Fermer la fenêtre"
                                    disabled={form.processing}
                                >
                                    <X className="size-4" />
                                </button>
                            </DialogPrimitive.Close>
                        </div>

                        <div className="cash-opening-list">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label className="field">
                                    <span className="inline-flex items-center gap-1.5">
                                        <UserRound
                                            className="size-3.5 text-gold"
                                            aria-hidden="true"
                                        />
                                        Nom complet{' '}
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
                                                ? 'kora-point-member-name-error'
                                                : undefined
                                        }
                                        required
                                    />
                                    {form.errors.name ? (
                                        <small
                                            id="kora-point-member-name-error"
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
                                        Adresse e-mail{' '}
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
                                                ? 'kora-point-member-email-error'
                                                : undefined
                                        }
                                        required
                                    />
                                    {form.errors.email ? (
                                        <small
                                            id="kora-point-member-email-error"
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
                                    defaultCountry={pointCountry}
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
                                            ? 'kora-point-member-phone-error'
                                            : undefined,
                                    }}
                                />
                                {form.errors.phone ? (
                                    <small
                                        id="kora-point-member-phone-error"
                                        className="field-error-text"
                                    >
                                        {form.errors.phone}
                                    </small>
                                ) : null}
                            </label>
                        </div>

                        <div className="cash-modal-actions">
                            <DialogPrimitive.Close asChild>
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    disabled={form.processing}
                                >
                                    Annuler
                                </button>
                            </DialogPrimitive.Close>
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

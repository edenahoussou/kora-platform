import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import type {
    PartnerOrganization,
    PartnerPoint,
} from '@/types/kora-point-partner';
import { inputClass, labelClass, Money } from './partner-ui';

type PointFormData = {
    organization_uuid: string;
    name: string;
    city: string;
    department: string;
    country: string;
    responsible_name: string;
    email: string;
    phone: string;
    address: string;
    ceiling_amount: string;
};

export function PointFormDialog({
    open,
    point,
    organization,
    onClose,
}: {
    open: boolean;
    point: PartnerPoint | null;
    organization: PartnerOrganization;
    onClose: () => void;
}) {
    const form = useForm<PointFormData>({
        organization_uuid: organization.uuid,
        name: point?.name ?? '',
        city: point?.city ?? '',
        department: point?.department ?? '',
        country: organization.country,
        responsible_name: point?.responsible_name ?? '',
        email: '',
        phone: point?.phone ?? '',
        address: point?.address ?? '',
        ceiling_amount: String(
            point?.credit_limit ??
                Math.max(1, organization.available_allocation),
        ),
    });

    if (!open) {
        return null;
    }

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        };

        if (point) {
            form.put(`/kora-points/${point.uuid}`, options);
        } else {
            form.post('/kora-points', options);
        }
    };

    const maximumAllocation =
        organization.available_allocation + (point?.credit_limit ?? 0);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="presentation"
        >
            <section
                aria-labelledby="partner-point-dialog-title"
                aria-modal="true"
                className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-border bg-background shadow-xl"
                role="dialog"
            >
                <header className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-background px-6 py-4">
                    <div>
                        <h2
                            id="partner-point-dialog-title"
                            className="text-lg font-semibold text-foreground"
                        >
                            {point
                                ? 'Modifier le Point de vente'
                                : 'Créer un Point de vente'}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Le pays et les conditions commerciales sont hérités
                            de {organization.name}.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted"
                    >
                        Fermer
                    </button>
                </header>

                <form onSubmit={submit} className="space-y-5 px-6 py-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className={labelClass}>
                            Nom du Point{' '}
                            <span className="text-destructive">*</span>
                            <input
                                className={inputClass}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                            {form.errors.name ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.name}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Ville <span className="text-destructive">*</span>
                            <input
                                className={inputClass}
                                value={form.data.city}
                                onChange={(event) =>
                                    form.setData('city', event.target.value)
                                }
                                required
                            />
                            {form.errors.city ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.city}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Département / région
                            <input
                                className={inputClass}
                                value={form.data.department}
                                onChange={(event) =>
                                    form.setData(
                                        'department',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.department ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.department}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Téléphone du Point{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                (facultatif)
                            </span>
                            <input
                                className={inputClass}
                                type="tel"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                            />
                            {form.errors.phone ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.phone}
                                </span>
                            ) : null}
                        </label>
                        <label className={`${labelClass} sm:col-span-2`}>
                            Adresse
                            <input
                                className={inputClass}
                                value={form.data.address}
                                onChange={(event) =>
                                    form.setData('address', event.target.value)
                                }
                            />
                            {form.errors.address ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.address}
                                </span>
                            ) : null}
                        </label>
                    </div>

                    {point ? (
                        <div className="rounded-lg border border-border bg-muted/40 p-4 text-sm">
                            <p className="font-medium text-foreground">
                                Responsable du Point
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                {point.responsible_name || 'Non renseigné'} —
                                les changements de compte agent sont gérés par
                                KORA OPS.
                            </p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-border p-4">
                            <h3 className="text-sm font-semibold text-foreground">
                                Compte agent du Point
                            </h3>
                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                <label className={labelClass}>
                                    Nom du responsable{' '}
                                    <span className="text-destructive">*</span>
                                    <input
                                        className={inputClass}
                                        value={form.data.responsible_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'responsible_name',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {form.errors.responsible_name ? (
                                        <span className="mt-1 block text-xs text-destructive">
                                            {form.errors.responsible_name}
                                        </span>
                                    ) : null}
                                </label>
                                <label className={labelClass}>
                                    E-mail de connexion{' '}
                                    <span className="text-destructive">*</span>
                                    <input
                                        className={inputClass}
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {form.errors.email ? (
                                        <span className="mt-1 block text-xs text-destructive">
                                            {form.errors.email}
                                        </span>
                                    ) : null}
                                </label>
                            </div>
                            <p className="mt-3 text-xs leading-5 text-muted-foreground">
                                Un accès web initial sera envoyé à cette
                                adresse. Tout renvoi ou changement d’agent se
                                fait dans Utilisateurs par KORA OPS.
                            </p>
                        </div>
                    )}

                    <label className={labelClass}>
                        Allocation de plafond du Point (XOF){' '}
                        <span className="text-destructive">*</span>
                        <input
                            className={inputClass}
                            type="number"
                            min="1"
                            max={maximumAllocation}
                            step="1"
                            value={form.data.ceiling_amount}
                            onChange={(event) =>
                                form.setData(
                                    'ceiling_amount',
                                    event.target.value,
                                )
                            }
                            required
                        />
                        <span className="mt-1 block text-xs font-normal text-muted-foreground">
                            Disponible à répartir :{' '}
                            <Money amount={maximumAllocation} />.
                        </span>
                        {form.errors.ceiling_amount ? (
                            <span className="mt-1 block text-xs text-destructive">
                                {form.errors.ceiling_amount}
                            </span>
                        ) : null}
                    </label>

                    {form.errors.organization_uuid ? (
                        <p className="text-sm text-destructive">
                            {form.errors.organization_uuid}
                        </p>
                    ) : null}

                    <footer className="flex justify-end gap-2 border-t border-border pt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90 disabled:opacity-60"
                        >
                            {form.processing
                                ? 'Enregistrement…'
                                : point
                                  ? 'Enregistrer'
                                  : 'Créer le Point'}
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    );
}

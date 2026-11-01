import { Form } from '@inertiajs/react';
import { Building2, Store, X } from 'lucide-react';
import { useState } from 'react';
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
    const isEditing = Boolean(point);
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
                {({ processing, errors }) => (
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
                                        <span>
                                            Nom du point{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            name="name"
                                            defaultValue={point?.name ?? ''}
                                            placeholder="Guichet Akpakpa Centre"
                                            required
                                        />
                                        {errors.name && (
                                            <small className="text-destructive">
                                                {errors.name}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>
                                            Ville{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            name="city"
                                            defaultValue={point?.city ?? ''}
                                            placeholder="Cotonou"
                                            required
                                        />
                                        {errors.city && (
                                            <small className="text-destructive">
                                                {errors.city}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>Code agence</span>
                                        <input
                                            type="text"
                                            name="code"
                                            defaultValue={point?.code ?? ''}
                                            placeholder="Auto : KP-XXX-0000"
                                        />
                                        {errors.code && (
                                            <small className="text-destructive">
                                                {errors.code}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field sm:col-span-2">
                                        <span>Adresse</span>
                                        <input
                                            type="text"
                                            name="address"
                                            defaultValue={point?.address ?? ''}
                                            placeholder="Quartier, rue, repère"
                                        />
                                        {errors.address && (
                                            <small className="text-destructive">
                                                {errors.address}
                                            </small>
                                        )}
                                    </label>
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
                                        <span>
                                            Nom du responsable{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            name="responsible_name"
                                            defaultValue={
                                                point?.responsible_name ?? ''
                                            }
                                            placeholder="Jean Dossou"
                                            required
                                        />
                                        {errors.responsible_name && (
                                            <small className="text-destructive">
                                                {errors.responsible_name}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>
                                            Téléphone{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="tel"
                                            name="phone"
                                            defaultValue={point?.phone ?? ''}
                                            placeholder="+229 97 00 00 00"
                                            required
                                        />
                                        {errors.phone && (
                                            <small className="text-destructive">
                                                {errors.phone}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field sm:col-span-2">
                                        <span>Organisation partenaire</span>
                                        <select
                                            name="organization_uuid"
                                            defaultValue={
                                                point?.organization_uuid ?? ''
                                            }
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
                                        {errors.organization_uuid && (
                                            <small className="text-destructive">
                                                {errors.organization_uuid}
                                            </small>
                                        )}
                                    </label>
                                </div>
                            </div>

                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Store className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Conditions commerciales</strong>
                                        <small>
                                            Laisser vide pour hériter des
                                            conditions cadres
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <label className="field">
                                        <span>Commission (points de base)</span>
                                        <input
                                            type="number"
                                            name="commission_basis_points"
                                            min={0}
                                            max={10000}
                                            defaultValue={
                                                point?.terms
                                                    ?.commission_basis_points ??
                                                ''
                                            }
                                            placeholder="300 = 3 %"
                                        />
                                        {errors.commission_basis_points && (
                                            <small className="text-destructive">
                                                {errors.commission_basis_points}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>Plafond d'encours (FCFA)</span>
                                        <input
                                            type="number"
                                            name="ceiling_amount"
                                            min={0}
                                            defaultValue={
                                                point?.terms?.ceiling_amount ??
                                                ''
                                            }
                                            placeholder="1 500 000"
                                        />
                                        {errors.ceiling_amount && (
                                            <small className="text-destructive">
                                                {errors.ceiling_amount}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>Reversements</span>
                                        <select
                                            value={frequency}
                                            onChange={(e) =>
                                                setFrequency(e.target.value)
                                            }
                                        >
                                            <option value="">
                                                Hérité (hebdomadaire)
                                            </option>
                                            <option value="daily">
                                                Quotidien
                                            </option>
                                            <option value="weekly">
                                                Hebdomadaire
                                            </option>
                                            <option value="monthly">
                                                Mensuel
                                            </option>
                                        </select>
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
                                        {errors.settlement_calendar && (
                                            <small className="text-destructive">
                                                {errors.settlement_calendar}
                                            </small>
                                        )}
                                    </label>
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

import { Form } from '@inertiajs/react';
import { Building2, X } from 'lucide-react';
import organizationRoutes from '@/routes/kora-points/organizations';
import type { AdminOrganization } from './kora-admin-ui';

type Props = {
    open: boolean;
    organization?: AdminOrganization | null;
    onOpenChange: (open: boolean) => void;
};

export function KoraOrgFormDialog({ open, organization, onOpenChange }: Props) {
    const isEditing = Boolean(organization);
    const title = isEditing
        ? 'Modifier l’organisation'
        : 'Nouvelle organisation partenaire';
    const description = isEditing
        ? 'Mettez à jour les informations de l’organisation partenaire.'
        : 'Créez une organisation pour y rattacher des points de vente et piloter leurs conditions cadres.';
    const form = isEditing
        ? organizationRoutes.update.form({
              organization: organization!.uuid,
          })
        : organizationRoutes.store.form();

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
                className="cash-modal cash-opening-modal w-[38rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-org-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
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
                                disabled={processing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="cash-opening-list">
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Building2 className="size-4" />
                                    </span>
                                    <div>
                                        <strong>
                                            Identité de l’organisation
                                        </strong>
                                        <small>
                                            Dénomination officielle et code de
                                            référence
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span>
                                            Code{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            name="code"
                                            defaultValue={
                                                organization?.code ?? ''
                                            }
                                            placeholder="LPB-01"
                                            required
                                        />
                                        {errors.code && (
                                            <small className="text-destructive">
                                                {errors.code}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>
                                            Raison sociale{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            name="name"
                                            defaultValue={
                                                organization?.name ?? ''
                                            }
                                            placeholder="La Poste du Bénin"
                                            required
                                        />
                                        {errors.name && (
                                            <small className="text-destructive">
                                                {errors.name}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>Téléphone</span>
                                        <input
                                            type="tel"
                                            name="phone"
                                            defaultValue={
                                                organization?.phone ?? ''
                                            }
                                            placeholder="+229 21 00 00 00"
                                        />
                                        {errors.phone && (
                                            <small className="text-destructive">
                                                {errors.phone}
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>Siège / Adresse</span>
                                        <input
                                            type="text"
                                            name="address"
                                            defaultValue={
                                                organization?.address ?? ''
                                            }
                                            placeholder="Cotonou, Ganhi"
                                        />
                                        {errors.address && (
                                            <small className="text-destructive">
                                                {errors.address}
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
                                    : 'Créer l’organisation'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

import { router } from '@inertiajs/react';
import { Link2, X } from 'lucide-react';
import { useState } from 'react';
import koraPoints from '@/routes/kora-points';
import type { AdminOrganization, AdminPoint } from './kora-admin-ui';

type Props = {
    open: boolean;
    point: AdminPoint | null;
    organizations: AdminOrganization[];
    onOpenChange: (open: boolean) => void;
};

export function KoraPointAttachDialog({
    open,
    point,
    organizations,
    onOpenChange,
}: Props) {
    const [organizationUuid, setOrganizationUuid] = useState('');
    const [processing, setProcessing] = useState(false);

    if (!open || !point) {
        return null;
    }

    const attach = () => {
        if (!organizationUuid) {
            return;
        }

        setProcessing(true);
        router.put(
            koraPoints.update.url({ point: point.uuid }),
            {
                code: point.code,
                name: point.name,
                city: point.city,
                country: point.country || 'BJ',
                responsible_name: point.responsible_name,
                phone: point.phone,
                address: point.address,
                latitude: point.latitude,
                longitude: point.longitude,
                organization_uuid: organizationUuid,
                commission_basis_points: point.terms?.commission_basis_points,
                ceiling_amount: point.terms?.ceiling_amount,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <div
                className="cash-modal cash-opening-modal w-[30rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-attach-modal-title"
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Link2 className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Réseau commercial · Rattachement
                            </span>
                            <h3 id="kora-attach-modal-title">
                                Rattacher le point
                            </h3>
                            <p>
                                Attribuez « {point.name} » à une organisation
                                partenaire. Il héritera de ses conditions
                                cadres.
                            </p>
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
                    <label className="field">
                        <span>
                            Organisation partenaire{' '}
                            <b className="font-black text-destructive">*</b>
                        </span>
                        <select
                            value={organizationUuid}
                            onChange={(e) =>
                                setOrganizationUuid(e.target.value)
                            }
                        >
                            <option value="">Choisir une organisation…</option>
                            {organizations.map((org) => (
                                <option key={org.uuid} value={org.uuid}>
                                    {org.name} ({org.code})
                                </option>
                            ))}
                        </select>
                    </label>
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
                        type="button"
                        className="btn btn-primary"
                        onClick={attach}
                        disabled={processing || !organizationUuid}
                    >
                        Rattacher le point
                    </button>
                </div>
            </div>
        </div>
    );
}

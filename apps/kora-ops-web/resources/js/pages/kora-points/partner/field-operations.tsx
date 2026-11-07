import { Head, router } from '@inertiajs/react';
import { ClipboardList, Megaphone, Store } from 'lucide-react';
import { useState } from 'react';
import { FieldOperationReadOnlyTable } from '@/components/kora-points/admin/field-operations-panels';
import type {
    Paginated,
    PlvOrder,
    PointVisit,
} from '@/components/kora-points/admin/field-operations-panels';
import { PartnerPage } from '@/components/kora-points/partner/partner-ui';
import koraPartnerRoutes from '@/routes/kora-partner';

type Props = {
    kind: 'visits' | 'plv';
    organization: { name: string; uuid: string };
    visits?: Paginated<PointVisit>;
    plv_orders?: Paginated<PlvOrder>;
    point_filter_options: { uuid: string; name: string; code: string }[];
    selected_point_uuid: string | null;
};

export default function PartnerFieldOperationsPage({
    kind,
    organization,
    visits,
    plv_orders,
    point_filter_options,
    selected_point_uuid,
}: Props) {
    const [selectedPoint, setSelectedPoint] = useState(
        selected_point_uuid ?? '',
    );
    const isVisits = kind === 'visits';
    const title = isVisits ? 'Visites terrain' : 'PLV';
    const Icon = isVisits ? ClipboardList : Megaphone;

    const applyFilter = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            query: selectedPoint ? { point_uuid: selectedPoint } : {},
        };
        router.get(
            isVisits
                ? koraPartnerRoutes.visits.url(options)
                : koraPartnerRoutes.plv.url(options),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title={`${title} — ${organization.name}`} />
            <PartnerPage
                title={title}
                description={
                    isVisits
                        ? 'Consultez les visites, leurs comptes rendus, les photos et le suivi des actions sur les Points rattachés.'
                        : 'Consultez les commandes de supports de publicité sur le lieu de vente sur les Points rattachés.'
                }
                organizationName={organization.name}
            >
                <form
                    onSubmit={applyFilter}
                    className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div className="w-full max-w-md">
                        <label
                            htmlFor="field-operations-point-filter"
                            className="mb-1.5 flex items-center gap-2 text-xs font-semibold text-foreground"
                        >
                            <Store className="size-3.5 text-muted-foreground" />
                            Filtrer par Point
                        </label>
                        <select
                            id="field-operations-point-filter"
                            value={selectedPoint}
                            onChange={(event) =>
                                setSelectedPoint(event.target.value)
                            }
                            className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <option value="">Tous les Points</option>
                            {point_filter_options.map((point) => (
                                <option key={point.uuid} value={point.uuid}>
                                    {point.name} · {point.code}
                                </option>
                            ))}
                        </select>
                    </div>
                    <button type="submit" className="btn btn-secondary">
                        Appliquer le filtre
                    </button>
                </form>

                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Icon className="size-4 text-[var(--kora)]" />
                        <h2 className="text-sm font-bold">
                            {isVisits
                                ? 'Historique des visites'
                                : 'Historique des commandes'}
                        </h2>
                    </div>
                    {isVisits && visits ? (
                        <FieldOperationReadOnlyTable
                            kind="visits"
                            visits={visits}
                            page={visits}
                        />
                    ) : null}
                    {!isVisits && plv_orders ? (
                        <FieldOperationReadOnlyTable
                            kind="plv"
                            plvOrders={plv_orders}
                            page={plv_orders}
                        />
                    ) : null}
                </section>
            </PartnerPage>
        </>
    );
}

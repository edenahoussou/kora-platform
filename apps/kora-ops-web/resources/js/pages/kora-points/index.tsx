import { Head, router } from '@inertiajs/react';
import { KoraAdminNetworkView } from '@/components/kora-points/admin/kora-admin-network-view';
import type {
    AdminNetworkPoint,
    AdminOrganization,
} from '@/components/kora-points/mock/kora-point-mock-data';

type Props = {
    points: { data: AdminNetworkPoint[] } | AdminNetworkPoint[];
    organizations?: AdminOrganization[];
    defaults?: Record<string, unknown> | null;
};

export default function KoraPointsIndexPage({
    points,
    organizations = [],
}: Props) {
    const pointsList: AdminNetworkPoint[] = Array.isArray(points)
        ? points
        : (points?.data ?? []);

    const handleCreatePoint = (data: {
        name: string;
        city: string;
        department?: string;
        managerName?: string;
        manager_name?: string;
        phone: string;
        organizationUuid?: string;
        organization_uuid?: string;
        commissionRateBps?: number;
        commission_basis_points?: number;
        creditLimit?: number;
        ceiling_amount?: number;
        settlementFrequencyDays?: number;
        settlement_frequency_days?: number;
    }) => {
        router.post(
            '/kora-points',
            {
                name: data.name,
                city: data.city,
                department: data.department,
                responsible_name: data.managerName || data.manager_name,
                phone: data.phone,
                organization_uuid: data.organizationUuid || data.organization_uuid,
                commission_basis_points: data.commissionRateBps || data.commission_basis_points,
                ceiling_amount: data.creditLimit || data.ceiling_amount,
                settlement_frequency_days: data.settlementFrequencyDays || data.settlement_frequency_days,
                country: 'BJ',
            },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Points de vente KORA POINT - Réseau commercial" />
            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                <KoraAdminNetworkView
                    points={pointsList}
                    organizations={organizations}
                    onCreatePoint={handleCreatePoint}
                />
            </div>
        </>
    );
}

import { Head, router } from '@inertiajs/react';
import { KoraAdminOrganizationsView } from '@/components/kora-points/admin/kora-admin-organizations-view';
import type {
    AdminOrganization,
    OrganizationSubPoint,
} from '@/components/kora-points/mock/kora-point-mock-data';

type Props = {
    organizations: { data: AdminOrganization[] } | AdminOrganization[];
    subpoints?: OrganizationSubPoint[];
};

export default function KoraPointOrganizationsIndexPage({
    organizations,
    subpoints = [],
}: Props) {
    const orgsList: AdminOrganization[] = Array.isArray(organizations)
        ? organizations
        : (organizations?.data ?? []);

    const handleToggleUserStatus = (userUuid: string) => {
        router.patch(`/users/${userUuid}/status`, {}, { preserveScroll: true });
    };

    const handleUpdateTermsOverride = (
        pointUuid: string,
        terms: { commission_basis_points?: number | null; ceiling_amount?: number | null },
    ) => {
        router.patch(`/kora-points/${pointUuid}/terms`, terms, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Organisations partenaires KORA POINT - Réseau commercial" />
            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                <KoraAdminOrganizationsView
                    organizations={orgsList}
                    subpoints={subpoints}
                    onToggleUserStatus={handleToggleUserStatus}
                    onUpdateTermsOverride={handleUpdateTermsOverride}
                />
            </div>
        </>
    );
}

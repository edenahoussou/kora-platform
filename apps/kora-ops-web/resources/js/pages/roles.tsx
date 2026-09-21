import { Head } from '@inertiajs/react';
import type {
    AdministrationPermission,
    AdministrationRole,
} from '@/types/administration';

type PermissionGroup = {
    domain: string;
    label: string;
    items: AdministrationPermission[];
};

type RolesPageProps = {
    roles?: AdministrationRole[];
    categories?: Array<{ value: AdministrationRole['category']; label: string }>;
    permissions?: PermissionGroup[];
    canManage?: boolean;
};

export default function RolesPage({ canManage }: RolesPageProps) {
    return (
        <>
            <Head title="Rôles et permissions" />
            <div className="space-y-6 p-6 lg:p-8">
                <div>
                    <p className="text-xs font-black tracking-[0.16em] text-gold uppercase">
                        Administration
                    </p>
                    <h1 className="mt-2 text-2xl font-black tracking-tight text-foreground">
                        Rôles et permissions
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Consultez les profils d’accès et la matrice de permissions
                        de KORA OPS.
                    </p>
                </div>

                <section className="rounded-2xl border border-border bg-card p-8 shadow-sm">
                    <div className="flex min-h-40 items-center justify-center text-center">
                        <div className="max-w-md">
                            <h2 className="text-base font-bold text-foreground">
                                Catalogue des rôles
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                La matrice des permissions sera administrable dans
                                la prochaine itération.
                            </p>
                            {canManage && (
                                <p className="mt-4 text-xs font-semibold tracking-wide text-gold uppercase">
                                    Accès d’administration activé
                                </p>
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

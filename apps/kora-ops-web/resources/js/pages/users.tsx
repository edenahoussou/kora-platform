import { Head } from '@inertiajs/react';
import type {
    AdministrationAgency,
    AdministrationRole,
    PaginatedAdministrationUsers,
} from '@/types/administration';

type UsersPageProps = {
    users?: PaginatedAdministrationUsers;
    filters?: {
        q?: string;
        role?: string;
        agency?: string;
        status?: string;
        archived?: string;
    };
    statuses?: string[];
    roles?: AdministrationRole[];
    agencies?: AdministrationAgency[];
    permissions?: {
        canManage?: boolean;
    };
};

export default function UsersPage({ permissions }: UsersPageProps) {
    return (
        <>
            <Head title="Utilisateurs" />
            <div className="space-y-6 p-6 lg:p-8">
                <div>
                    <p className="text-xs font-black tracking-[0.16em] text-gold uppercase">
                        Administration
                    </p>
                    <h1 className="mt-2 text-2xl font-black tracking-tight text-foreground">
                        Utilisateurs
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Gérez les comptes OPS, leurs périmètres, rôles et accès.
                    </p>
                </div>

                <section className="rounded-2xl border border-border bg-card p-8 shadow-sm">
                    <div className="flex min-h-40 items-center justify-center text-center">
                        <div className="max-w-md">
                            <h2 className="text-base font-bold text-foreground">
                                Gestion des utilisateurs
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                La liste et les actions d’administration seront
                                ajoutées dans la prochaine itération.
                            </p>
                            {permissions?.canManage && (
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

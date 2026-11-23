import { usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Bus,
    ClipboardList,
    FileText,
    LayoutDashboard,
    Megaphone,
    Package,
    ReceiptText,
    RotateCcw,
    ShieldCheck,
    Store,
    Ticket,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard, profitability, revenue } from '@/routes';
import { settings as administrationSettings } from '@/routes/administration';
import companies from '@/routes/companies';
import companyPortalRoutes from '@/routes/company-portal';
import programming from '@/routes/programming';

type NavPermission = string | string[] | null;
type WalletNavCapability =
    | 'walletAny'
    | 'walletOpsView'
    | 'walletRulesView'
    | 'walletSettingsView'
    | 'walletRestitutionQueueView'
    | 'walletRestitutionAccess'
    | 'walletRestitutionOwnView'
    | 'walletRestitutionRequest';

type NavChild = {
    title: string;
    href: string;
    permission: NavPermission;
    role?: string;
    requiresCompanyBackOffice?: boolean;
    exact?: boolean;
    capability?: WalletNavCapability;
};

export type OpsNavEntry = {
    title: string;
    href: string;
    icon: typeof LayoutDashboard;
    permission: NavPermission;
    exact?: boolean;
    capability?: WalletNavCapability;
    children?: NavChild[];
    requiresCompanyReferent?: boolean;
};

const companyPortalHref = companyPortalRoutes.index.url();

const koraPointAgentNavItems: OpsNavEntry[] = [
    {
        title: 'Tableau de bord',
        href: '/kora-point',
        icon: LayoutDashboard,
        permission: null,
        exact: true,
    },
    {
        title: 'Vente de billets',
        href: '/kora-point/sale',
        icon: Ticket,
        permission: null,
    },
    {
        title: 'Session caisse',
        href: '/kora-point/cash',
        icon: Wallet,
        permission: null,
    },
    {
        title: 'Billets émis',
        href: '/kora-point/tickets',
        icon: FileText,
        permission: null,
    },
    {
        title: 'Mon Point',
        href: '/kora-point/profile',
        icon: Store,
        permission: null,
    },
    {
        title: 'Commissions',
        href: '/kora-point/commissions',
        icon: TrendingUp,
        permission: null,
    },
    {
        title: 'Versements',
        href: '/kora-point/settlements',
        icon: ReceiptText,
        permission: null,
    },
    {
        title: 'Remboursements',
        href: '/kora-point/refunds',
        icon: RotateCcw,
        permission: null,
    },
    {
        title: 'Actions de visite',
        href: '/kora-point/visit-actions',
        icon: ClipboardList,
        permission: 'kora-points.visits.actions.update',
    },
    {
        title: 'Restitutions Wallet',
        href: '/kora-point/wallet-restitutions',
        icon: Wallet,
        permission: null,
        capability: 'walletRestitutionRequest',
    },
];

const koraPointOrganizationNavItems: OpsNavEntry[] = [
    {
        title: 'Tableau de bord',
        href: '/kora-partner',
        icon: LayoutDashboard,
        permission: null,
        exact: true,
    },
    {
        title: 'Points de vente',
        href: '/kora-partner/points',
        icon: Store,
        permission: null,
    },
    {
        title: 'Ventes',
        href: '/kora-partner/sales',
        icon: Ticket,
        permission: null,
    },
    {
        title: 'Commissions',
        href: '/kora-partner/commissions',
        icon: TrendingUp,
        permission: null,
    },
    {
        title: 'Remboursements',
        href: '/kora-partner/refunds',
        icon: RotateCcw,
        permission: null,
    },
    {
        title: 'Visites terrain',
        href: '/kora-partner/visits',
        icon: ClipboardList,
        permission: null,
    },
    {
        title: 'PLV',
        href: '/kora-partner/plv',
        icon: Megaphone,
        permission: null,
    },
    {
        title: 'Documents',
        href: '/kora-partner/documents',
        icon: FileText,
        permission: null,
    },
    {
        title: 'Restitutions Wallet',
        href: '/kora-partner/wallet-restitutions',
        icon: Wallet,
        permission: null,
        capability: 'walletRestitutionRequest',
    },
    {
        title: 'Mon organisation',
        href: '/kora-partner/organization',
        icon: Building2,
        permission: null,
    },
];

const navItems: OpsNavEntry[] = [
    {
        title: 'Accueil',
        href: dashboard().url,
        icon: LayoutDashboard,
        permission: null,
    },
    {
        title: 'KORA POINT',
        href: '/kora-point',
        icon: Store,
        permission: null,
        children: [
            {
                title: 'Tableau de bord',
                href: '/kora-point',
                permission: null,
                role: 'kora_point_agent',
                exact: true,
            },
            {
                title: 'Vente de billets',
                href: '/kora-point/sale',
                permission: null,
                role: 'kora_point_agent',
            },
            {
                title: 'Session caisse',
                href: '/kora-point/cash',
                permission: null,
                role: 'kora_point_agent',
            },
            {
                title: 'Billets émis',
                href: '/kora-point/tickets',
                permission: null,
                role: 'kora_point_agent',
            },
            {
                title: 'Mon Point',
                href: '/kora-point/profile',
                permission: null,
                role: 'kora_point_agent',
            },
            {
                title: 'Commissions',
                href: '/kora-point/commissions',
                permission: null,
                role: 'kora_point_agent',
            },
            {
                title: 'Versements',
                href: '/kora-point/settlements',
                permission: null,
                role: 'kora_point_agent',
            },
            {
                title: 'Remboursements',
                href: '/kora-point/refunds',
                permission: null,
                role: 'kora_point_agent',
            },
        ],
    },
    {
        title: 'Espace entreprise',
        href: companyPortalHref,
        requiresCompanyReferent: true,
        icon: Building2,
        permission: null,
        children: [
            {
                title: 'Tableau de bord',
                href: companyPortalRoutes.index.url(),
                permission: null,
                exact: true,
            },
            {
                title: 'Acheter des billets',
                href: companyPortalRoutes.sale.index.url(),
                permission: null,
            },
            {
                title: 'Membres voyageurs',
                href: companyPortalRoutes.travelers.index.url(),
                permission: null,
            },
            {
                title: 'Historique des achats',
                href: companyPortalRoutes.reservations.index.url(),
                permission: null,
            },
            {
                title: 'Relevés de facturation',
                href: companyPortalRoutes.statements.index.url(),
                permission: null,
            },
        ],
    },
    {
        title: 'Vente',
        href: '/sale',
        icon: Ticket,
        permission: [
            'counter-sales.create',
            'assisted-bookings.view-agency',
            'assisted-bookings.view-network',
        ],
        children: [
            {
                title: 'Vente au guichet',
                href: '/sale',
                permission: 'counter-sales.create',
            },
            {
                title: 'Réservations en attente',
                href: '/assisted-bookings',
                permission: [
                    'assisted-bookings.view-agency',
                    'assisted-bookings.view-network',
                ],
            },
        ],
    },
    {
        title: 'Caisse',
        href: '/cash',
        icon: Wallet,
        permission: 'cash.view',
    },
    {
        title: 'Wallet & fidélité',
        href: '/wallet',
        icon: Wallet,
        permission: null,
        capability: 'walletAny',
        children: [
            {
                title: 'Pilotage',
                href: '/wallet/dashboard',
                permission: null,
                capability: 'walletOpsView',
                exact: true,
            },
            {
                title: 'Comptes',
                href: '/wallet/accounts',
                permission: null,
                capability: 'walletOpsView',
            },
            {
                title: 'Mouvements',
                href: '/wallet/transactions',
                permission: null,
                capability: 'walletOpsView',
            },
            {
                title: 'Règles de fidélité',
                href: '/wallet/rules',
                permission: null,
                capability: 'walletRulesView',
            },
            {
                title: 'Paramètres',
                href: '/administration/settings/wallet',
                permission: null,
                capability: 'walletSettingsView',
            },
            {
                title: 'Restitutions',
                href: '/administration/wallet-restitutions',
                permission: null,
                capability: 'walletRestitutionAccess',
            },
        ],
    },
    {
        title: 'Finance',
        href: '/fiscal-invoices',
        icon: ReceiptText,
        permission: [
            'fiscalization.view',
            'expenses.view-own',
            'expenses.view-agency',
            'expenses.view-network',
            'revenue.view',
        ],
        children: [
            {
                title: 'Recettes',
                href: revenue().url,
                permission: 'revenue.view',
            },
            {
                title: 'Facturation',
                href: '/fiscal-invoices',
                permission: 'fiscalization.view',
            },
            {
                title: 'Campagne d’autodéclaration DGI',
                href: '/fiscalization/test-invoices',
                permission: null,
                role: 'system_admin',
            },
            {
                title: 'Dépenses',
                href: '/expenses',
                permission: [
                    'expenses.view-own',
                    'expenses.view-agency',
                    'expenses.view-network',
                ],
            },
        ],
    },
    {
        title: 'Rentabilité',
        href: profitability().url,
        icon: TrendingUp,
        permission: 'profitability.view',
    },
    {
        title: 'Exploitation',
        href: '/operations',
        icon: Bus,
        permission: null,
        children: [
            {
                title: 'Programmation',
                href: programming.index.url(),
                permission: 'departures.manage',
            },
            {
                title: 'Départs',
                href: '/departures',
                permission: 'departures.view',
            },
            {
                title: 'Suivi de flotte',
                href: '/operations/fleet-tracking',
                permission: ['tracking.view-agency', 'tracking.view-network'],
            },
            {
                title: 'Lignes commerciales',
                href: '/network',
                permission: 'network.view',
            },
            {
                title: 'Points d’arrêt',
                href: '/stops',
                permission: 'network.view',
            },
            { title: 'Tarifs', href: '/fares', permission: 'pricing.view' },
            {
                title: 'Taux fiscaux',
                href: '/tax-rules',
                permission: 'pricing.view',
            },
        ],
    },
    {
        title: 'Colis',
        href: '/parcels',
        icon: Package,
        permission: 'parcels.view',
    },
    {
        title: 'Réseau commercial',
        href: '/dynamic-pricing',
        icon: Store,
        permission: [
            'pricing.view',
            'pricing.manage',
            'company-accounts.manage',
            'company-credit.view',
            'kora-points.view',
            'kora-points.finance',
        ],
        children: [
            {
                title: 'Tarification dynamique',
                href: '/dynamic-pricing',
                permission: ['pricing.view', 'pricing.manage'],
            },
            {
                title: 'Promotions',
                href: '/promotions',
                permission: ['pricing.view', 'pricing.manage'],
            },
            {
                title: 'Comptes entreprises',
                href: companies.index.url(),
                permission: 'company-accounts.view',
                requiresCompanyBackOffice: true,
            },
            {
                title: 'Points de vente',
                href: '/kora-points',
                permission: 'kora-points.view',
                exact: true,
            },
            {
                title: 'Organisations partenaires',
                href: '/kora-points/organizations',
                permission: 'kora-points.view',
            },
            {
                title: 'Rapprochement & Finance',
                href: '/kora-points/finance',
                permission: 'kora-points.finance',
            },
        ],
    },
    {
        title: 'Passagers',
        href: '/passengers',
        icon: Users,
        permission: 'tickets.view',
    },
    {
        title: 'Billets',
        href: '/tickets',
        icon: FileText,
        permission: 'tickets.view',
    },
    {
        title: 'Ressources',
        href: '/resources',
        icon: Store,
        permission: null,
        children: [
            {
                title: 'Agences',
                href: '/agencies',
                permission: 'agencies.view',
            },
            { title: 'Flotte', href: '/fleet', permission: 'fleet.view' },
            {
                title: 'Gabarits',
                href: '/layouts',
                permission: 'fleet.view',
            },
            {
                title: 'Modèles',
                href: '/models',
                permission: 'fleet.view',
            },
            {
                title: 'Commodités',
                href: '/amenities',
                permission: 'fleet.view',
            },
        ],
    },
    {
        title: 'Administration',
        href: '/users',
        icon: ShieldCheck,
        permission: [
            'users.view',
            'roles.view',
            'terminals.view',
            'app-releases.view',
            'settings.general.view',
            'settings.operations.view',
            'settings.integrations.view',
            'monitoring.view',
        ],
        children: [
            {
                title: 'Utilisateurs',
                href: '/users',
                permission: 'users.view',
            },
            {
                title: 'Rôles et permissions',
                href: '/roles',
                permission: 'roles.view',
            },
            {
                title: 'Terminaux P501',
                href: '/terminals',
                permission: 'terminals.view',
            },
            {
                title: 'Versions de l’application',
                href: '/administration/releases',
                permission: 'app-releases.view',
            },
            {
                title: 'Monitoring',
                href: '/administration/monitoring',
                permission: 'monitoring.view',
            },
            {
                title: 'Paramètres',
                href: administrationSettings.url(),
                permission: [
                    'settings.general.view',
                    'settings.operations.view',
                    'settings.integrations.view',
                ],
            },
        ],
    },
    {
        title: 'Alertes',
        href: '/alerts',
        icon: AlertTriangle,
        permission: null,
    },
];

const koraPointPartnerNavItems: OpsNavEntry[] = [
    ...koraPointOrganizationNavItems.slice(0, 5),
    {
        title: 'Reversements',
        href: '/kora-points/remittances',
        icon: ReceiptText,
        permission: null,
    },
    ...koraPointOrganizationNavItems.slice(5),
];

export function useOpsNavigation() {
    const { auth, companyPortal, portalOnly } = usePage().props;
    const { can, canAny, hasRole } = usePermissions();

    const canAccess = (permission: NavPermission): boolean => {
        if (Array.isArray(permission)) {
            return canAny(permission);
        }

        return can(permission);
    };

    const canAccessWalletCapability = (
        capability?: WalletNavCapability,
    ): boolean => {
        if (!capability) {
            return true;
        }

        const capabilities = auth?.user?.walletCapabilities;

        if (capability === 'walletAny') {
            return Object.values(capabilities ?? {}).some(Boolean);
        }

        if (capability === 'walletRestitutionAccess') {
            return Boolean(
                capabilities?.walletRestitutionQueueView ||
                capabilities?.walletRestitutionOwnView ||
                capabilities?.walletRestitutionRequest,
            );
        }

        return Boolean(capabilities?.[capability]);
    };

    const isAgent = hasRole('kora_point_agent');
    const isPartner =
        hasRole('kora_point_supervisor') || hasRole('kora_point_treasurer');
    const workspaceLabel = isAgent
        ? 'Espace KORA POINT'
        : isPartner
          ? 'Espace partenaire'
          : 'Espace de travail';

    const entries = isAgent
        ? koraPointAgentNavItems
        : isPartner
          ? koraPointPartnerNavItems
          : portalOnly
            ? navItems.filter((item) => item.href === companyPortalHref)
            : navItems.filter(
                  (item) =>
                      item.title !== 'KORA POINT' || hasRole('system_admin'),
              );

    const navigation = entries.flatMap((item) => {
        if (
            !canAccess(item.permission) ||
            !canAccessWalletCapability(item.capability) ||
            (item.href === '/cash' && !auth?.user?.canViewCash) ||
            (item.href === '/tickets' && !auth?.user?.canViewTickets) ||
            (item.requiresCompanyReferent && !companyPortal)
        ) {
            return [];
        }

        if (!item.children) {
            return [item];
        }

        const children = item.children.filter(
            (child) =>
                canAccess(child.permission) &&
                canAccessWalletCapability(child.capability) &&
                (!child.role || hasRole(child.role)) &&
                (!child.requiresCompanyBackOffice ||
                    canAny(['company-accounts.manage', 'company-credit.view'])),
        );

        return children.length > 0 ? [{ ...item, children }] : [];
    });

    return { navigation, workspaceLabel };
}

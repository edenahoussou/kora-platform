export type AdministrationRole = {
    code: string;
    label: string;
    description?: string | null;
    category: 'central' | 'agency' | 'terrain';
    isSystem: boolean;
    isActive: boolean;
    permissions?: AdministrationPermission[];
};

export type AdministrationPermission = {
    code: string;
    label: string;
    domain: string;
};

export type AdministrationAgency = {
    uuid: string;
    code: string;
    name: string;
};

export type AdministrationUser = {
    uuid: string;
    name: string;
    email: string;
    phone: string | null;
    country_code: string | null;
    birth_date: string | null;
    status: 'active' | 'suspended' | 'locked';
    deleted_at: string | null;
    agency: AdministrationAgency | null;
    role: Pick<AdministrationRole, 'code' | 'label' | 'category'> | null;
};

export type PaginatedAdministrationUsers = {
    data: AdministrationUser[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

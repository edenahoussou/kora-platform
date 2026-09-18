export type CompanyAccountMember = {
    uuid: string;
    userUuid: string;
    name: string;
    email: string | null;
    role: 'referent' | 'member';
};

export type CompanyAccountRecord = {
    uuid: string;
    name: string;
    type: 'company' | 'ngo' | 'embassy';
    reference: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    ifu: string | null;
    rccm: string | null;
    status: 'active' | 'inactive';
    monthlyCreditLimit: number;
    consumedCredit: number;
    reservedCredit: number;
    availableCredit: number;
    referent: { uuid: string; name: string; email: string | null } | null;
    members: CompanyAccountMember[];
    creditActivity: Array<{
        uuid: string;
        type: 'debit' | 'refund';
        amount: number;
        reference: string;
        periodKey: string;
        createdAt: string | null;
    }>;
};

export type CompanyUserCandidate = {
    uuid: string;
    name: string;
    email: string | null;
};

export type CompanyAccountFormValues = {
    name: string;
    type: CompanyAccountRecord['type'];
    phone: string;
    email: string;
    address: string;
    city: string;
    country: string;
    ifu: string;
    rccm: string;
    monthly_credit_limit: string;
};

export function formatFcfa(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

export type CompanyTravelerRecord = {
    uuid: string;
    firstName: string;
    lastName: string;
    phone: string;
    email: string | null;
    documentType: string | null;
    documentTypeLabel: string | null;
    documentNumber: string | null;
    nationality: string | null;
    birthDate: string | null;
    yellowFeverVaccinated: boolean | null;
    status: 'active' | 'inactive';
};

export type CompanyTravelerFormValues = {
    first_name: string;
    last_name: string;
    phone: string;
    email: string;
    document_type: string;
    document_number: string;
    nationality: string;
    birth_date: string;
    yellow_fever_vaccinated: boolean;
};

export type CompanyTravelerImportReport = {
    imported: number;
    updated: number;
    errors: { row: number; message: string }[];
};

export const emptyTravelerValues: CompanyTravelerFormValues = {
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    document_type: '',
    document_number: '',
    nationality: 'BJ',
    birth_date: '',
    yellow_fever_vaccinated: false,
};

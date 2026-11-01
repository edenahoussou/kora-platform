export type PointStatus =
    | 'ready'
    | 'warning_threshold'
    | 'limit_reached'
    | 'overdue'
    | 'suspended'
    | 'closed';

export type SettlementStatus = 'open' | 'in_settlement' | 'settled';

export type DemoScenarioId =
    | 'autonomous_healthy'
    | 'autonomous_warning_80'
    | 'autonomous_limit_reached'
    | 'autonomous_overdue'
    | 'org_attached_healthy';

export type KoraPointTerms = {
    commission_rate_bps: number; // e.g. 300 = 3.0%
    credit_limit_amount: number; // e.g. 1 500 000 FCFA
    settlement_schedule: {
        frequency: 'daily' | 'weekly' | 'monthly';
        day_of_week?: number;
        day_of_month?: number;
        time: string;
        timezone: string;
    };
    grace_period_days: number;
};

export type KoraPointProfile = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    address: string;
    manager_name: string;
    manager_phone: string;
    manager_email: string;
    status: PointStatus;
    organization: {
        uuid: string;
        code: string;
        name: string;
    } | null;
    terms: KoraPointTerms;
};

export type FinancialHealth = {
    credit_limit: number;
    current_exposure: number; // Total net encours
    available_balance: number; // Plafond - Encours
    usage_percentage: number;
    next_due_date: string;
    next_due_label: string;
    is_overdue: boolean;
    sales_allowed: boolean;
    block_reason: string | null;
};

export type DailyActivity = {
    date: string;
    day_short: string;
    sales_amount: number;
    commission_amount: number;
    tickets_count: number;
    is_peak?: boolean;
};

export type DailyMetrics = {
    today_sales_amount: number;
    today_tickets_count: number;
    today_commission_amount: number;
    total_due_amount: number; // Encours total à reverser
    activity_7d: DailyActivity[];
};

export type PointPriorityAlert = {
    id: string;
    level: 'info' | 'warning' | 'critical' | 'success';
    title: string;
    description: string;
    time_label: string;
    action_label?: string;
    action_target?: string;
};

export type PointSaleRecord = {
    uuid: string;
    ticket_number: string;
    short_code: string;
    created_at: string;
    date_label: string;
    seller_name: string;
    departure_ref: string;
    service_class: string;
    departure_time: string;
    route_label: string;
    origin_city: string;
    destination_city: string;
    seat_number: string;
    passenger_name: string;
    passenger_phone: string;
    payment_method: 'CASH' | 'MOBILE_MONEY';
    amount_paid: number;
    commission_rate_bps: number;
    commission_amount: number;
    settlement_status: SettlementStatus;
    settlement_ref?: string;
    refundable: boolean;
};

export type SaleDepartureMock = {
    uuid: string;
    ref: string;
    service_class: 'Standard' | 'Confort VIP' | 'Express';
    departure_time: string;
    arrival_time: string;
    duration: string;
    available_seats: number;
    total_seats: number;
    unit_price: number;
    origin: string;
    destination: string;
    stops: string[];
};

export const MOCK_POINT_AUTONOMOUS: KoraPointProfile = {
    uuid: 'pnt-8a4e120f-7b61-4c6c-829d-47819ad09101',
    code: 'KP-AKP-01',
    name: 'KORA POINT Akpakpa',
    city: 'Cotonou',
    country: 'Bénin',
    address: 'Carrefour PK3, face station Bénin Pétro, Akpakpa',
    manager_name: 'Jean Dossou',
    manager_phone: '+229 97 00 11 22',
    manager_email: 'jean.dossou@korapoint.bj',
    status: 'ready',
    organization: null,
    terms: {
        commission_rate_bps: 300,
        credit_limit_amount: 1500000,
        settlement_schedule: {
            frequency: 'weekly',
            day_of_week: 1, // Lundi
            time: '18:00',
            timezone: 'Africa/Porto-Novo',
        },
        grace_period_days: 2,
    },
};

export const MOCK_POINT_ORGANIZATION: KoraPointProfile = {
    uuid: 'pnt-3f5b780a-9d21-419b-a08b-63219ee09944',
    code: 'KP-LP-CTN01',
    name: 'KORA POINT Agence Centrale Poste',
    city: 'Cotonou',
    country: 'Bénin',
    address: 'Boulevard Saint-Michel, Bureau de Poste Central',
    manager_name: 'Awa Kiki',
    manager_phone: '+229 95 44 22 11',
    manager_email: 'awa.kiki@laposte.bj',
    status: 'ready',
    organization: {
        uuid: 'org-11223344-5566-7788-99aa-bbccddeeff00',
        code: 'ORG-LAPOSTE-BJ',
        name: 'La Poste du Togo / Bénin Réseau',
    },
    terms: {
        commission_rate_bps: 350,
        credit_limit_amount: 5000000,
        settlement_schedule: {
            frequency: 'monthly',
            day_of_month: 28,
            time: '17:00',
            timezone: 'Africa/Porto-Novo',
        },
        grace_period_days: 3,
    },
};

export const MOCK_DAILY_METRICS: DailyMetrics = {
    today_sales_amount: 168500,
    today_tickets_count: 18,
    today_commission_amount: 5055,
    total_due_amount: 960000,
    activity_7d: [
        {
            date: '2026-07-26',
            day_short: 'Dim',
            sales_amount: 220000,
            commission_amount: 6600,
            tickets_count: 24,
        },
        {
            date: '2026-07-27',
            day_short: 'Lun',
            sales_amount: 145000,
            commission_amount: 4350,
            tickets_count: 16,
        },
        {
            date: '2026-07-28',
            day_short: 'Mar',
            sales_amount: 195000,
            commission_amount: 5850,
            tickets_count: 22,
        },
        {
            date: '2026-07-29',
            day_short: 'Mer',
            sales_amount: 168500,
            commission_amount: 5055,
            tickets_count: 18,
        },
        {
            date: '2026-07-30',
            day_short: 'Jeu',
            sales_amount: 280000,
            commission_amount: 8400,
            tickets_count: 32,
        },
        {
            date: '2026-07-31',
            day_short: 'Ven',
            sales_amount: 395000,
            commission_amount: 11850,
            tickets_count: 45,
            is_peak: true,
        },
        {
            date: '2026-08-01',
            day_short: 'Sam',
            sales_amount: 310000,
            commission_amount: 9300,
            tickets_count: 35,
        },
    ],
};

export const MOCK_PRIORITIES: PointPriorityAlert[] = [
    {
        id: 'p-1',
        level: 'warning',
        title: 'Échéance de reversement hebdomadaire',
        description: 'Prochaine échéance le lundi 3 août à 18:00 (dans 2 jours).',
        time_label: 'Il y a 25 min',
        action_label: 'Préparer',
        action_target: 'settlements',
    },
    {
        id: 'p-2',
        level: 'info',
        title: 'Seuil d’encours sous surveillance',
        description: 'Vous êtes à 64 % de votre plafond autorisé (960 000 / 1 500 000 FCFA).',
        time_label: 'Aujourd’hui 08:30',
    },
    {
        id: 'p-3',
        level: 'success',
        title: 'Bordereau REV-2026-0042 validé par KORA Finance',
        description: 'Versement de 840 000 FCFA rapproché sans écart.',
        time_label: 'Hier 16:45',
        action_label: 'Voir reçu',
        action_target: 'settlements',
    },
];

export const MOCK_DEPARTURES: SaleDepartureMock[] = [
    {
        uuid: 'dep-101',
        ref: 'KE 118',
        service_class: 'Standard',
        departure_time: '10:30',
        arrival_time: '16:45',
        duration: '6h 15m',
        available_seats: 14,
        total_seats: 42,
        unit_price: 9000,
        origin: 'Cotonou',
        destination: 'Parakou',
        stops: ['Allada', 'Bohicon', 'Dassa-Zoumè', 'Parakou'],
    },
    {
        uuid: 'dep-102',
        ref: 'KE 124 VIP',
        service_class: 'Confort VIP',
        departure_time: '13:15',
        arrival_time: '19:15',
        duration: '6h 00m',
        available_seats: 6,
        total_seats: 36,
        unit_price: 12000,
        origin: 'Cotonou',
        destination: 'Parakou',
        stops: ['Bohicon', 'Parakou'],
    },
    {
        uuid: 'dep-103',
        ref: 'KE 140 Nuit',
        service_class: 'Express',
        departure_time: '21:30',
        arrival_time: '03:45',
        duration: '6h 15m',
        available_seats: 22,
        total_seats: 56,
        unit_price: 9000,
        origin: 'Cotonou',
        destination: 'Parakou',
        stops: ['Bohicon', 'Parakou'],
    },
];

export const MOCK_POINT_SALES: PointSaleRecord[] = [
    {
        uuid: 'sale-901',
        ticket_number: 'TK-AKP-260801-01',
        short_code: '4382',
        created_at: '2026-08-01 09:14',
        date_label: 'Aujourd’hui à 09:14',
        seller_name: 'Jean Dossou',
        departure_ref: 'KE 118',
        service_class: 'Standard',
        departure_time: '10:30',
        route_label: 'Cotonou → Parakou',
        origin_city: 'Cotonou',
        destination_city: 'Parakou',
        seat_number: '12',
        passenger_name: 'Bio Kora Seidou',
        passenger_phone: '+229 97 12 34 56',
        payment_method: 'CASH',
        amount_paid: 9000,
        commission_rate_bps: 300,
        commission_amount: 270,
        settlement_status: 'open',
        refundable: true,
    },
    {
        uuid: 'sale-902',
        ticket_number: 'TK-AKP-260801-02',
        short_code: '4383',
        created_at: '2026-08-01 09:22',
        date_label: 'Aujourd’hui à 09:22',
        seller_name: 'Jean Dossou',
        departure_ref: 'KE 118',
        service_class: 'Standard',
        departure_time: '10:30',
        route_label: 'Cotonou → Parakou',
        origin_city: 'Cotonou',
        destination_city: 'Parakou',
        seat_number: '14',
        passenger_name: 'Mariam Bio',
        passenger_phone: '+229 95 88 77 66',
        payment_method: 'MOBILE_MONEY',
        amount_paid: 9000,
        commission_rate_bps: 300,
        commission_amount: 270,
        settlement_status: 'open',
        refundable: true,
    },
    {
        uuid: 'sale-903',
        ticket_number: 'TK-AKP-260801-03',
        short_code: '4384',
        created_at: '2026-08-01 10:05',
        date_label: 'Aujourd’hui à 10:05',
        seller_name: 'Jean Dossou',
        departure_ref: 'KE 124 VIP',
        service_class: 'Confort VIP',
        departure_time: '13:15',
        route_label: 'Cotonou → Parakou',
        origin_city: 'Cotonou',
        destination_city: 'Parakou',
        seat_number: '04',
        passenger_name: 'David Hounsa',
        passenger_phone: '+229 96 33 22 11',
        payment_method: 'CASH',
        amount_paid: 12000,
        commission_rate_bps: 300,
        commission_amount: 360,
        settlement_status: 'open',
        refundable: true,
    },
    {
        uuid: 'sale-904',
        ticket_number: 'TK-AKP-260731-15',
        short_code: '4102',
        created_at: '2026-07-31 16:40',
        date_label: '31 juil. 16:40',
        seller_name: 'Jean Dossou',
        departure_ref: 'KE 140 Nuit',
        service_class: 'Express',
        departure_time: '21:30',
        route_label: 'Cotonou → Parakou',
        origin_city: 'Cotonou',
        destination_city: 'Parakou',
        seat_number: '28',
        passenger_name: 'Inès Agossa',
        passenger_phone: '+229 94 55 66 77',
        payment_method: 'CASH',
        amount_paid: 9000,
        commission_rate_bps: 300,
        commission_amount: 270,
        settlement_status: 'in_settlement',
        settlement_ref: 'REV-2026-0043 (En cours)',
        refundable: false,
    },
    {
        uuid: 'sale-905',
        ticket_number: 'TK-AKP-260731-16',
        short_code: '4103',
        created_at: '2026-07-31 17:10',
        date_label: '31 juil. 17:10',
        seller_name: 'Jean Dossou',
        departure_ref: 'KE 140 Nuit',
        service_class: 'Express',
        departure_time: '21:30',
        route_label: 'Cotonou → Parakou',
        origin_city: 'Cotonou',
        destination_city: 'Parakou',
        seat_number: '29',
        passenger_name: 'Marc Agossa',
        passenger_phone: '+229 94 55 66 78',
        payment_method: 'CASH',
        amount_paid: 9000,
        commission_rate_bps: 300,
        commission_amount: 270,
        settlement_status: 'in_settlement',
        settlement_ref: 'REV-2026-0043 (En cours)',
        refundable: false,
    },
    {
        uuid: 'sale-906',
        ticket_number: 'TK-AKP-260730-01',
        short_code: '3990',
        created_at: '2026-07-30 08:30',
        date_label: '30 juil. 08:30',
        seller_name: 'Jean Dossou',
        departure_ref: 'KE 118',
        service_class: 'Standard',
        departure_time: '10:30',
        route_label: 'Cotonou → Parakou',
        origin_city: 'Cotonou',
        destination_city: 'Parakou',
        seat_number: '08',
        passenger_name: 'Chantal Houessou',
        passenger_phone: '+229 97 44 11 00',
        payment_method: 'CASH',
        amount_paid: 9000,
        commission_rate_bps: 300,
        commission_amount: 270,
        settlement_status: 'settled',
        settlement_ref: 'REV-2026-0042',
        refundable: false,
    },
    {
        uuid: 'sale-907',
        ticket_number: 'TK-AKP-260730-02',
        short_code: '3991',
        created_at: '2026-07-30 09:15',
        date_label: '30 juil. 09:15',
        seller_name: 'Jean Dossou',
        departure_ref: 'KE 124 VIP',
        service_class: 'Confort VIP',
        departure_time: '13:15',
        route_label: 'Cotonou → Parakou',
        origin_city: 'Cotonou',
        destination_city: 'Parakou',
        seat_number: '11',
        passenger_name: 'Gaston Gnanhoui',
        passenger_phone: '+229 90 22 33 44',
        payment_method: 'MOBILE_MONEY',
        amount_paid: 12000,
        commission_rate_bps: 300,
        commission_amount: 360,
        settlement_status: 'settled',
        settlement_ref: 'REV-2026-0042',
        refundable: false,
    },
];

export function getScenarioData(scenarioId: DemoScenarioId): {
    point: KoraPointProfile;
    health: FinancialHealth;
    metrics: DailyMetrics;
    priorities: PointPriorityAlert[];
    sales: PointSaleRecord[];
} {
    if (scenarioId === 'org_attached_healthy') {
        const point = { ...MOCK_POINT_ORGANIZATION };
        const health: FinancialHealth = {
            credit_limit: point.terms.credit_limit_amount,
            current_exposure: 1850000,
            available_balance: 3150000,
            usage_percentage: 37,
            next_due_date: '2026-08-28',
            next_due_label: 'Vendredi 28 août à 17:00 (Fin de mois)',
            is_overdue: false,
            sales_allowed: true,
            block_reason: null,
        };
        const metrics: DailyMetrics = {
            ...MOCK_DAILY_METRICS,
            total_due_amount: 1850000,
        };
        return {
            point,
            health,
            metrics,
            priorities: MOCK_PRIORITIES,
            sales: MOCK_POINT_SALES,
        };
    }

    if (scenarioId === 'autonomous_warning_80') {
        const point = { ...MOCK_POINT_AUTONOMOUS, status: 'warning_threshold' as PointStatus };
        const health: FinancialHealth = {
            credit_limit: point.terms.credit_limit_amount,
            current_exposure: 1290000,
            available_balance: 210000,
            usage_percentage: 86,
            next_due_date: '2026-08-03',
            next_due_label: 'Lundi 3 août à 18:00',
            is_overdue: false,
            sales_allowed: true,
            block_reason: 'Attention : le seuil d’alerte de 80 % est atteint. Pensez à verser les recettes.',
        };
        const metrics: DailyMetrics = {
            ...MOCK_DAILY_METRICS,
            total_due_amount: 1290000,
        };
        return {
            point,
            health,
            metrics,
            priorities: [
                {
                    id: 'w-1',
                    level: 'warning',
                    title: 'Seuil d’alerte de 80 % dépassé',
                    description: 'Encours à 86 % du plafond. Les ventes seront bloquées si l’encours atteint 1 500 000 FCFA.',
                    time_label: 'Il y a 5 min',
                    action_label: 'Préparer un reversement',
                    action_target: 'settlements',
                },
                ...MOCK_PRIORITIES,
            ],
            sales: MOCK_POINT_SALES,
        };
    }

    if (scenarioId === 'autonomous_limit_reached') {
        const point = { ...MOCK_POINT_AUTONOMOUS, status: 'limit_reached' as PointStatus };
        const health: FinancialHealth = {
            credit_limit: point.terms.credit_limit_amount,
            current_exposure: 1500000,
            available_balance: 0,
            usage_percentage: 100,
            next_due_date: '2026-08-03',
            next_due_label: 'Lundi 3 août à 18:00',
            is_overdue: false,
            sales_allowed: false,
            block_reason: 'Ventes bloquées : le plafond de crédit (1 500 000 FCFA) est atteint. Un reversement validé est nécessaire pour débloquer les ventes.',
        };
        const metrics: DailyMetrics = {
            ...MOCK_DAILY_METRICS,
            total_due_amount: 1500000,
        };
        return {
            point,
            health,
            metrics,
            priorities: [
                {
                    id: 'b-1',
                    level: 'critical',
                    title: 'Ventes temporairement bloquées',
                    description: 'Plafond atteint (1 500 000 FCFA). Effectuez un reversement pour libérer du disponible.',
                    time_label: 'Actif',
                    action_label: 'Déclarer un reversement',
                    action_target: 'settlements',
                },
                ...MOCK_PRIORITIES,
            ],
            sales: MOCK_POINT_SALES,
        };
    }

    if (scenarioId === 'autonomous_overdue') {
        const point = { ...MOCK_POINT_AUTONOMOUS, status: 'overdue' as PointStatus };
        const health: FinancialHealth = {
            credit_limit: point.terms.credit_limit_amount,
            current_exposure: 820000,
            available_balance: 680000,
            usage_percentage: 54,
            next_due_date: '2026-07-27',
            next_due_label: 'Lundi 27 juillet (Échue depuis 5 jours)',
            is_overdue: true,
            sales_allowed: false,
            block_reason: 'Ventes suspendues : retard de reversement au-delà du délai de grâce (échéance du 27 juillet non réglée).',
        };
        const metrics: DailyMetrics = {
            ...MOCK_DAILY_METRICS,
            total_due_amount: 820000,
        };
        return {
            point,
            health,
            metrics,
            priorities: [
                {
                    id: 'o-1',
                    level: 'critical',
                    title: 'Délai de grâce dépassé — Ventes suspendues',
                    description: 'Reversement hebdomadaire en retard depuis plus de 48 heures.',
                    time_label: 'Retard de 5 jours',
                    action_label: 'Régulariser immédiatement',
                    action_target: 'settlements',
                },
                ...MOCK_PRIORITIES,
            ],
            sales: MOCK_POINT_SALES,
        };
    }

    // Default healthy
    const point = { ...MOCK_POINT_AUTONOMOUS };
    const health: FinancialHealth = {
        credit_limit: point.terms.credit_limit_amount,
        current_exposure: 960000,
        available_balance: 540000,
        usage_percentage: 64,
        next_due_date: '2026-08-03',
        next_due_label: 'Lundi 3 août à 18:00',
        is_overdue: false,
        sales_allowed: true,
        block_reason: null,
    };
    return {
        point,
        health,
        metrics: MOCK_DAILY_METRICS,
        priorities: MOCK_PRIORITIES,
        sales: MOCK_POINT_SALES,
    };
}

// -------------------------------------------------------------
// LOT 2 DATA EXTENSIONS : CAISSE, MON POINT, COMMISSIONS
// -------------------------------------------------------------

export type PointCashMovement = {
    id: string;
    time_label: string;
    label: string;
    amount: number;
    type: 'OPENING' | 'SALE_CASH' | 'SALE_MOBILE' | 'EXPENSE' | 'DEPOSIT';
    method: 'CASH' | 'MOBILE_MONEY';
    balance_after: number;
};

export type PointCashSession = {
    status: 'OPEN' | 'CLOSED';
    opened_at: string;
    closed_at?: string;
    operator_name: string;
    opening_amount: number;
    cash_collected: number;
    mobile_money_collected: number;
    expected_closing_cash: number;
    actual_closing_cash?: number;
    variance_amount?: number;
    closing_note?: string;
    movements: PointCashMovement[];
};

export const MOCK_CASH_SESSION_OPEN: PointCashSession = {
    status: 'OPEN',
    opened_at: 'Aujourd’hui à 08:00',
    operator_name: 'Jean Dossou',
    opening_amount: 50000,
    cash_collected: 118500,
    mobile_money_collected: 50000,
    expected_closing_cash: 168500, // 50 000 fond + 118 500 espèces
    movements: [
        {
            id: 'm-1',
            time_label: '08:00',
            label: 'Ouverture de session — Fond de caisse initial',
            amount: 50000,
            type: 'OPENING',
            method: 'CASH',
            balance_after: 50000,
        },
        {
            id: 'm-2',
            time_label: '09:14',
            label: 'Vente billet TK-AKP-260801-01 (Bio Kora Seidou)',
            amount: 9000,
            type: 'SALE_CASH',
            method: 'CASH',
            balance_after: 59000,
        },
        {
            id: 'm-3',
            time_label: '09:22',
            label: 'Vente billet TK-AKP-260801-02 (Mariam Bio)',
            amount: 9000,
            type: 'SALE_MOBILE',
            method: 'MOBILE_MONEY',
            balance_after: 59000,
        },
        {
            id: 'm-4',
            time_label: '10:05',
            label: 'Vente billet VIP TK-AKP-260801-03 (David Hounsa)',
            amount: 12000,
            type: 'SALE_CASH',
            method: 'CASH',
            balance_after: 71000,
        },
        {
            id: 'm-5',
            time_label: '11:30',
            label: 'Vente multiple 4 billets (Famille Dossou)',
            amount: 36000,
            type: 'SALE_CASH',
            method: 'CASH',
            balance_after: 107000,
        },
        {
            id: 'm-6',
            time_label: '14:15',
            label: 'Vente Mobile Money 3 billets (Groupe étudiant)',
            amount: 27000,
            type: 'SALE_MOBILE',
            method: 'MOBILE_MONEY',
            balance_after: 107000,
        },
        {
            id: 'm-7',
            time_label: '16:00',
            label: 'Vente 2 billets Express Parakou',
            amount: 18000,
            type: 'SALE_CASH',
            method: 'CASH',
            balance_after: 125000,
        },
    ],
};

export type PointConvention = {
    ref: string;
    title: string;
    signed_at: string;
    expires_at: string;
    status: 'ACTIVE' | 'EXPIRED' | 'PENDING';
    file_name: string;
    file_size: string;
    commission_rate_bps: number;
    credit_limit_amount: number;
};

export type PointDocument = {
    uuid: string;
    title: string;
    category: 'IDENTITE' | 'REGISTRE' | 'FISCAL' | 'LOCALISATION' | 'AUTRE';
    file_name: string;
    file_size: string;
    uploaded_at: string;
    status: 'VALID' | 'PENDING' | 'EXPIRED';
};

export type PointAssignedUser = {
    uuid: string;
    name: string;
    email: string;
    phone: string;
    role_label: string;
    assigned_at: string;
    is_primary: boolean;
};

export const MOCK_CONVENTION: PointConvention = {
    ref: 'CONV-KP-2026-AKP01',
    title: 'Convention de partenariat commercial — Distribution Billetterie Bus',
    signed_at: '15 janvier 2026',
    expires_at: '14 janvier 2027',
    status: 'ACTIVE',
    file_name: 'Convention_Partenariat_KoraPoint_Akpakpa.pdf',
    file_size: '2.4 Mo',
    commission_rate_bps: 300,
    credit_limit_amount: 1500000,
};

export const MOCK_DOCUMENTS: PointDocument[] = [
    {
        uuid: 'doc-01',
        title: 'Pièce d’identité du Gérant (CNI)',
        category: 'IDENTITE',
        file_name: 'CNI_Jean_Dossou_2026.pdf',
        file_size: '1.2 Mo',
        uploaded_at: '10 janv. 2026',
        status: 'VALID',
    },
    {
        uuid: 'doc-02',
        title: 'Registre du Commerce (RCCM)',
        category: 'REGISTRE',
        file_name: 'RCCM_Ets_Dossou_Distribution.pdf',
        file_size: '3.1 Mo',
        uploaded_at: '12 janv. 2026',
        status: 'VALID',
    },
    {
        uuid: 'doc-03',
        title: 'Attestation Fiscale (IFU)',
        category: 'FISCAL',
        file_name: 'Attestation_IFU_0201948572.pdf',
        file_size: '850 Ko',
        uploaded_at: '12 janv. 2026',
        status: 'VALID',
    },
    {
        uuid: 'doc-04',
        title: 'Bail commercial / Plan de situation local',
        category: 'LOCALISATION',
        file_name: 'Plan_Local_Akpakpa_PK3.pdf',
        file_size: '4.5 Mo',
        uploaded_at: '14 janv. 2026',
        status: 'VALID',
    },
];

export const MOCK_POINT_USERS: PointAssignedUser[] = [
    {
        uuid: 'usr-01',
        name: 'Jean Dossou',
        email: 'jean.dossou@korapoint.bj',
        phone: '+229 97 00 11 22',
        role_label: 'Gérant Point & Caissier principal',
        assigned_at: '15 janv. 2026',
        is_primary: true,
    },
    {
        uuid: 'usr-02',
        name: 'Awa Bio',
        email: 'awa.bio@korapoint.bj',
        phone: '+229 96 11 22 33',
        role_label: 'Agent guichetier remplaçant',
        assigned_at: '01 mars 2026',
        is_primary: false,
    },
];

export type PointCommissionRecord = {
    uuid: string;
    sale_uuid: string;
    ticket_number: string;
    date_label: string;
    departure_ref: string;
    route_label: string;
    seller_name: string;
    amount_paid: number;
    commission_rate_bps: number;
    initial_commission: number;
    correction_amount: number; // e.g. -270 if ticket refunded
    net_commission: number;
    settlement_status: SettlementStatus;
    has_refund_correction: boolean;
    refund_ref?: string;
    refund_reason?: string;
};

export const MOCK_COMMISSION_RECORDS: PointCommissionRecord[] = [
    {
        uuid: 'com-01',
        sale_uuid: 'sale-901',
        ticket_number: 'TK-AKP-260801-01',
        date_label: 'Aujourd’hui 09:14',
        departure_ref: 'KE 118',
        route_label: 'Cotonou → Parakou',
        seller_name: 'Jean Dossou',
        amount_paid: 9000,
        commission_rate_bps: 300,
        initial_commission: 270,
        correction_amount: 0,
        net_commission: 270,
        settlement_status: 'open',
        has_refund_correction: false,
    },
    {
        uuid: 'com-02',
        sale_uuid: 'sale-902',
        ticket_number: 'TK-AKP-260801-02',
        date_label: 'Aujourd’hui 09:22',
        departure_ref: 'KE 118',
        route_label: 'Cotonou → Parakou',
        seller_name: 'Jean Dossou',
        amount_paid: 9000,
        commission_rate_bps: 300,
        initial_commission: 270,
        correction_amount: 0,
        net_commission: 270,
        settlement_status: 'open',
        has_refund_correction: false,
    },
    {
        uuid: 'com-03',
        sale_uuid: 'sale-903',
        ticket_number: 'TK-AKP-260801-03',
        date_label: 'Aujourd’hui 10:05',
        departure_ref: 'KE 124 VIP',
        route_label: 'Cotonou → Parakou',
        seller_name: 'Jean Dossou',
        amount_paid: 12000,
        commission_rate_bps: 300,
        initial_commission: 360,
        correction_amount: 0,
        net_commission: 360,
        settlement_status: 'open',
        has_refund_correction: false,
    },
    {
        uuid: 'com-04',
        sale_uuid: 'sale-890',
        ticket_number: 'TK-AKP-260731-09',
        date_label: '31 juil. 14:10',
        departure_ref: 'KE 118',
        route_label: 'Cotonou → Parakou',
        seller_name: 'Jean Dossou',
        amount_paid: 9000,
        commission_rate_bps: 300,
        initial_commission: 270,
        correction_amount: -270,
        net_commission: 0,
        settlement_status: 'open',
        has_refund_correction: true,
        refund_ref: 'REM-2026-0004',
        refund_reason: 'Annulation voyageur autorisée par KORA Finance. Reprise de commission effectuée.',
    },
    {
        uuid: 'com-05',
        sale_uuid: 'sale-904',
        ticket_number: 'TK-AKP-260731-15',
        date_label: '31 juil. 16:40',
        departure_ref: 'KE 140 Nuit',
        route_label: 'Cotonou → Parakou',
        seller_name: 'Jean Dossou',
        amount_paid: 9000,
        commission_rate_bps: 300,
        initial_commission: 270,
        correction_amount: 0,
        net_commission: 270,
        settlement_status: 'in_settlement',
        has_refund_correction: false,
    },
    {
        uuid: 'com-06',
        sale_uuid: 'sale-906',
        ticket_number: 'TK-AKP-260730-01',
        date_label: '30 juil. 08:30',
        departure_ref: 'KE 118',
        route_label: 'Cotonou → Parakou',
        seller_name: 'Jean Dossou',
        amount_paid: 9000,
        commission_rate_bps: 300,
        initial_commission: 270,
        correction_amount: 0,
        net_commission: 270,
        settlement_status: 'settled',
        has_refund_correction: false,
    },
    {
        uuid: 'com-07',
        sale_uuid: 'sale-907',
        ticket_number: 'TK-AKP-260730-02',
        date_label: '30 juil. 09:15',
        departure_ref: 'KE 124 VIP',
        route_label: 'Cotonou → Parakou',
        seller_name: 'Jean Dossou',
        amount_paid: 12000,
        commission_rate_bps: 300,
        initial_commission: 360,
        correction_amount: 0,
        net_commission: 360,
        settlement_status: 'settled',
        has_refund_correction: false,
    },
];

export type PointSettlementStatus =
    | 'DRAFT'
    | 'DECLARED'
    | 'VALIDATED'
    | 'REJECTED'
    | 'PARTIALLY_VALIDATED';

export type PointSettlementMethod = 'BANK_TRANSFER' | 'BANK_DEPOSIT' | 'MOBILE_MONEY';

export type PointSettlementRecord = {
    uuid: string;
    reference: string;
    point_uuid: string;
    point_name: string;
    created_by_name: string;
    period_start: string;
    period_end: string;
    created_at: string;
    declared_at?: string;
    validated_at?: string;
    amount_expected: number;
    amount_declared: number;
    amount_validated?: number;
    payment_method: PointSettlementMethod;
    payment_reference: string;
    proof_file_name?: string;
    proof_file_size?: string;
    status: PointSettlementStatus;
    sales_count: number;
    tickets_covered: string[];
    notes?: string;
    rejection_reason?: string;
    timeline: {
        step: string;
        date_label: string;
        actor: string;
        comment?: string;
    }[];
};

export const MOCK_POINT_SETTLEMENTS: PointSettlementRecord[] = [
    {
        uuid: 'set-01',
        reference: 'REV-2026-0043',
        point_uuid: 'pt-akpakpa',
        point_name: 'Point Service Akpakpa',
        created_by_name: 'Jean Dossou',
        period_start: '2026-07-28',
        period_end: '2026-07-31',
        created_at: '2026-07-31 18:20',
        declared_at: '2026-07-31 18:25',
        amount_expected: 120000,
        amount_declared: 120000,
        amount_validated: undefined,
        payment_method: 'BANK_DEPOSIT',
        payment_reference: 'DEP-ECO-2026-8812',
        proof_file_name: 'bordereau_versement_ecobank_31072026.pdf',
        proof_file_size: '412 Ko',
        status: 'DECLARED',
        sales_count: 14,
        tickets_covered: [
            'TK-AKP-260731-15',
            'TK-AKP-260731-16',
            'TK-AKP-260731-10',
            'TK-AKP-260731-11',
            'TK-AKP-260731-12',
        ],
        notes: 'Versement espèces effectué à l’agence Ecobank Saint-Michel.',
        timeline: [
            {
                step: 'Création du bordereau',
                date_label: '31 juil. 18:20',
                actor: 'Jean Dossou (Agent)',
                comment: 'Bordereau préparé pour 14 billets.',
            },
            {
                step: 'Preuve soumise & Déclaration',
                date_label: '31 juil. 18:25',
                actor: 'Jean Dossou (Agent)',
                comment: 'Réf DEP-ECO-2026-8812 téléversée.',
            },
            {
                step: 'En attente de vérification KORA',
                date_label: 'En cours',
                actor: 'Pôle Trésorerie KORA',
                comment: 'Rapprochement bancaire sous 24-48h ouvrées.',
            },
        ],
    },
    {
        uuid: 'set-02',
        reference: 'REV-2026-0042',
        point_uuid: 'pt-akpakpa',
        point_name: 'Point Service Akpakpa',
        created_by_name: 'Jean Dossou',
        period_start: '2026-07-21',
        period_end: '2026-07-27',
        created_at: '2026-07-28 10:00',
        declared_at: '2026-07-28 10:15',
        validated_at: '2026-07-29 09:30',
        amount_expected: 185000,
        amount_declared: 185000,
        amount_validated: 185000,
        payment_method: 'BANK_TRANSFER',
        payment_reference: 'VIR-BOA-992384',
        proof_file_name: 'avis_operation_boa_28072026.pdf',
        proof_file_size: '285 Ko',
        status: 'VALIDATED',
        sales_count: 22,
        tickets_covered: ['TK-AKP-260730-01', 'TK-AKP-260730-02', 'TK-AKP-260727-08'],
        notes: 'Virement bancaire compte principal BOA KORA Exploitation.',
        timeline: [
            {
                step: 'Bordereau créé',
                date_label: '28 juil. 10:00',
                actor: 'Jean Dossou (Agent)',
            },
            {
                step: 'Déclaration transmise',
                date_label: '28 juil. 10:15',
                actor: 'Jean Dossou (Agent)',
            },
            {
                step: 'Fonds crédités et rapprochés',
                date_label: '29 juil. 09:30',
                actor: 'Clarisse Mensah (Finance KORA)',
                comment: 'Reçu bancaire conforme, encours apuré de 185 000 FCFA.',
            },
        ],
    },
    {
        uuid: 'set-03',
        reference: 'REV-2026-0039',
        point_uuid: 'pt-akpakpa',
        point_name: 'Point Service Akpakpa',
        created_by_name: 'Jean Dossou',
        period_start: '2026-07-14',
        period_end: '2026-07-20',
        created_at: '2026-07-22 15:30',
        declared_at: '2026-07-22 15:45',
        amount_expected: 95000,
        amount_declared: 95000,
        payment_method: 'BANK_DEPOSIT',
        payment_reference: 'DEP-BGFI-00219',
        proof_file_name: 'photo_recu_floue.jpg',
        proof_file_size: '1.2 Mo',
        status: 'REJECTED',
        sales_count: 11,
        tickets_covered: ['TK-AKP-260720-03', 'TK-AKP-260720-04'],
        rejection_reason:
            'Bordereau illisible : date et tampon bancaire non reconnaissables. Merci de resoumettre une photo nette ou le duplicata bancaire.',
        timeline: [
            {
                step: 'Déclaration transmise',
                date_label: '22 juil. 15:45',
                actor: 'Jean Dossou (Agent)',
            },
            {
                step: 'Contrôle Finance KORA',
                date_label: '23 juil. 11:20',
                actor: 'Clarisse Mensah (Finance KORA)',
                comment: 'Rejet : pièce non conforme.',
            },
        ],
    },
    {
        uuid: 'set-04',
        reference: 'REV-2026-0035',
        point_uuid: 'pt-akpakpa',
        point_name: 'Point Service Akpakpa',
        created_by_name: 'Jean Dossou',
        period_start: '2026-07-07',
        period_end: '2026-07-13',
        created_at: '2026-07-14 09:10',
        declared_at: '2026-07-14 09:20',
        validated_at: '2026-07-14 11:00',
        amount_expected: 240000,
        amount_declared: 240000,
        amount_validated: 240000,
        payment_method: 'MOBILE_MONEY',
        payment_reference: 'MTN-MOMO-8837190',
        proof_file_name: 'capture_momo_b2b.png',
        proof_file_size: '520 Ko',
        status: 'VALIDATED',
        sales_count: 28,
        tickets_covered: ['TK-AKP-260713-01', 'TK-AKP-260713-02'],
        timeline: [
            {
                step: 'Déclaration MoMo B2B',
                date_label: '14 juil. 09:20',
                actor: 'Jean Dossou (Agent)',
            },
            {
                step: 'Validation automatique API MoMo',
                date_label: '14 juil. 11:00',
                actor: 'Système KORA Trésorerie',
                comment: 'Transaction vérifiée par webhook opérateur.',
            },
        ],
    },
];

export type PointRefundStatus =
    | 'REQUESTED'
    | 'APPROVED_BY_FINANCE'
    | 'FUNDS_RESTITUTED'
    | 'REJECTED';

export type PointRefundReason =
    | 'TRIP_CANCELLED'
    | 'PASSENGER_SICKNESS'
    | 'DUPLICATE_PURCHASE'
    | 'SCHEDULE_CHANGE';

export type PointRefundRecord = {
    uuid: string;
    reference: string;
    ticket_number: string;
    sale_uuid: string;
    passenger_name: string;
    passenger_phone: string;
    departure_ref: string;
    route_label: string;
    original_amount: number;
    retained_fee: number;
    refund_amount: number;
    commission_clawback: number;
    reason: PointRefundReason;
    reason_label: string;
    status: PointRefundStatus;
    requested_at: string;
    requested_by: string;
    approved_at?: string;
    approved_by?: string;
    restituted_at?: string;
    restituted_by?: string;
    receipt_signed?: boolean;
    rejection_reason?: string;
    proof_document?: string;
    timeline: {
        step: string;
        date_label: string;
        actor: string;
        detail?: string;
    }[];
};

export const MOCK_POINT_REFUNDS: PointRefundRecord[] = [
    {
        uuid: 'ref-01',
        reference: 'REM-2026-0004',
        ticket_number: 'TK-AKP-260731-09',
        sale_uuid: 'sale-890',
        passenger_name: 'Marc Agossa',
        passenger_phone: '+229 94 55 66 78',
        departure_ref: 'KE 118',
        route_label: 'Cotonou → Parakou',
        original_amount: 9000,
        retained_fee: 0,
        refund_amount: 9000,
        commission_clawback: -270,
        reason: 'TRIP_CANCELLED',
        reason_label: 'Départ annulé par la compagnie',
        status: 'FUNDS_RESTITUTED',
        requested_at: '2026-07-31 14:10',
        requested_by: 'Jean Dossou (Agent Akpakpa)',
        approved_at: '2026-07-31 14:40',
        approved_by: 'Clarisse Mensah (Finance KORA)',
        restituted_at: '2026-07-31 16:30',
        restituted_by: 'Jean Dossou (Agent Akpakpa)',
        receipt_signed: true,
        timeline: [
            {
                step: 'Demande enregistrée au guichet',
                date_label: '31 juil. 14:10',
                actor: 'Jean Dossou (Agent)',
                detail: 'Annulation autocar KE 118 pour incident technique.',
            },
            {
                step: 'Validation Finance KORA',
                date_label: '31 juil. 14:40',
                actor: 'Clarisse Mensah (Finance)',
                detail: 'Remboursement intégral autorisé (frais 0%). Reprise com -270 FCFA.',
            },
            {
                step: 'Espèces remises au voyageur',
                date_label: '31 juil. 16:30',
                actor: 'Jean Dossou (Agent)',
                detail: '9 000 FCFA remis en main propre. Quittance émargée signée.',
            },
        ],
    },
    {
        uuid: 'ref-02',
        reference: 'REM-2026-0007',
        ticket_number: 'TK-AKP-260801-01',
        sale_uuid: 'sale-901',
        passenger_name: 'Amina Bello',
        passenger_phone: '+229 97 12 34 56',
        departure_ref: 'KE 118 Express',
        route_label: 'Cotonou → Parakou',
        original_amount: 9000,
        retained_fee: 0,
        refund_amount: 9000,
        commission_clawback: -270,
        reason: 'PASSENGER_SICKNESS',
        reason_label: 'Incapacité médicale subite (justificatif fourni)',
        status: 'APPROVED_BY_FINANCE',
        requested_at: '2026-08-01 07:30',
        requested_by: 'Bio Kora Agent',
        approved_at: '2026-08-01 08:15',
        approved_by: 'Clarisse Mensah (Finance KORA)',
        proof_document: 'certificat_medical_chu_0108.pdf',
        timeline: [
            {
                step: 'Demande soumise avec certificat médical',
                date_label: '01 août 07:30',
                actor: 'Bio Kora Agent',
                detail: 'Passagère hospitalisée avant le départ.',
            },
            {
                step: 'Accord préalable accordé par Finance KORA',
                date_label: '01 août 08:15',
                actor: 'Clarisse Mensah (Finance)',
                detail: 'Dossier approuvé. Le Point peut remettre les 9 000 FCFA à la présentation de la pièce d’identité.',
            },
        ],
    },
    {
        uuid: 'ref-03',
        reference: 'REM-2026-0009',
        ticket_number: 'TK-AKP-260801-02',
        sale_uuid: 'sale-902',
        passenger_name: 'Dossou Marcel',
        passenger_phone: '+229 95 67 89 01',
        departure_ref: 'KE 120 Confort',
        route_label: 'Cotonou → Bohicon',
        original_amount: 9000,
        retained_fee: 900,
        refund_amount: 8100,
        commission_clawback: -270,
        reason: 'SCHEDULE_CHANGE',
        reason_label: 'Changement de date demandé par le voyageur (retenue 10%)',
        status: 'REQUESTED',
        requested_at: '2026-08-01 09:40',
        requested_by: 'Bio Kora Agent',
        timeline: [
            {
                step: 'Demande enregistrée au guichet',
                date_label: '01 août 09:40',
                actor: 'Bio Kora Agent',
                detail: 'Billet acheté ce matin, voyageur souhaite annuler suite à report de réunion.',
            },
            {
                step: 'En attente d’instruction Finance KORA',
                date_label: 'En attente',
                actor: 'Service Contrôle Billetterie',
                detail: 'Vérification des conditions tarifaires (retenue standard 10%).',
            },
        ],
    },
    {
        uuid: 'ref-04',
        reference: 'REM-2026-0002',
        ticket_number: 'TK-AKP-260725-04',
        sale_uuid: 'sale-880',
        passenger_name: 'Raoul Kpodan',
        passenger_phone: '+229 90 11 22 33',
        departure_ref: 'KE 124 VIP',
        route_label: 'Cotonou → Parakou',
        original_amount: 12000,
        retained_fee: 12000,
        refund_amount: 0,
        commission_clawback: 0,
        reason: 'TRIP_CANCELLED',
        reason_label: 'No-show voyageur (non présenté au départ)',
        status: 'REJECTED',
        requested_at: '2026-07-26 10:00',
        requested_by: 'Jean Dossou',
        rejection_reason:
            'Non présentation sans avis préalable. Conformément aux conditions générales KORA Bus, les départs manqués ne sont pas remboursables.',
        timeline: [
            {
                step: 'Demande soumise',
                date_label: '26 juil. 10:00',
                actor: 'Jean Dossou',
            },
            {
                step: 'Dossier rejeté par la Finance',
                date_label: '26 juil. 14:00',
                actor: 'Clarisse Mensah (Finance)',
                detail: 'Règle tarifaire appliquée : no-show non éligible.',
            },
        ],
    },
];



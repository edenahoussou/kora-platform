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

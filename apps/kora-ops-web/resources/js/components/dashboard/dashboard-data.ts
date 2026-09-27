import type { LucideIcon } from 'lucide-react';
import {
    BadgePercent,
    Banknote,
    Bell,
    Building2,
    BusFront,
    ChartLine,
    CircleDollarSign,
    FileBarChart,
    Package,
    Receipt,
    Route,
    Settings,
    ShieldCheck,
    Smartphone,
    Tags,
    Ticket,
    UserPlus,
    Wallet,
    Wrench,
} from 'lucide-react';

export type MetricTone = 'gold' | 'blue' | 'navy' | 'green';
export type MetricKey = 'sales' | 'tickets' | 'cash' | 'departures';

export type DashboardMetric = {
    value: string;
    trend: string;
    spark: number[];
};

export type DashboardMetricEntry = DashboardMetric & {
    key: MetricKey;
    label: string;
    icon: LucideIcon;
    tone: MetricTone;
};

export type ActivityPoint = {
    label: string;
    value: number;
    isBest: boolean;
};

export type DashboardActivity = {
    bucket: 'hour' | 'day';
    points: ActivityPoint[];
};

export type PriorityTone = 'urgent' | 'control' | 'success' | 'parcel';

export type PriorityItem = {
    uuid: string;
    category: string;
    title: string;
    detail: string;
    time: string;
    tone: PriorityTone;
    href: string | null;
};

export type QuickAction = {
    title: string;
    description: string;
    icon: string;
    href: string | null;
    accent: MetricTone;
};

export type NetworkReadings = {
    punctuality: string;
    tracked: string;
    toWatch: string;
};

export type HeroAction = {
    label: string;
    href: string;
};

export type DashboardPeriod = {
    preset: string;
    from: string;
    to: string;
    label: string;
    bucket: string;
    timezone: string;
    comparisonFrom: string;
    comparisonTo: string;
};

/** Libellés, icônes et tons des quatre KPIs — les valeurs arrivent du backend. */
export const metricCards: Record<
    MetricKey,
    { label: string; icon: LucideIcon; tone: MetricTone }
> = {
    sales: { label: 'Ventes', icon: Banknote, tone: 'gold' },
    tickets: { label: 'Billets émis', icon: Ticket, tone: 'blue' },
    cash: { label: 'Caisse attendue', icon: CircleDollarSign, tone: 'navy' },
    departures: { label: 'Départs', icon: BusFront, tone: 'green' },
};

/** Ordre d'affichage des KPIs. */
export const metricOrder: MetricKey[] = ['sales', 'tickets', 'cash', 'departures'];

export const quickActionIcons: Record<string, LucideIcon> = {
    ticket: Ticket,
    cash: CircleDollarSign,
    route: Route,
    'user-plus': UserPlus,
    shield: ShieldCheck,
    settings: Settings,
    terminal: Smartphone,
    package: Package,
    fleet: BusFront,
    wrench: Wrench,
    report: FileBarChart,
    chart: ChartLine,
    alert: Bell,
    invoice: Receipt,
    wallet: Wallet,
    pricing: Tags,
    promo: BadgePercent,
    company: Building2,
};

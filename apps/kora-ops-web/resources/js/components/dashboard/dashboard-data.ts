import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    AlertTriangle,
    Banknote,
    BusFront,
    CircleDollarSign,
    FileCheck2,
    Gauge,
    PackageCheck,
    ReceiptText,
    Route,
    Ticket,
} from 'lucide-react';
import { cash, sale } from '@/routes';
import { index as departures } from '@/routes/departures';

export type MetricTone = 'gold' | 'blue' | 'navy' | 'green';

export type DashboardMetric = {
    label: string;
    value: string;
    trend: string;
    icon: LucideIcon;
    tone: MetricTone;
    spark: number[];
};

export type DashboardActivity = {
    category: string;
    title: string;
    detail: string;
    time: string;
    icon: LucideIcon;
    tone: 'urgent' | 'control' | 'success' | 'parcel';
};

export type QuickAction = {
    title: string;
    description: string;
    icon: LucideIcon;
    href: string;
    accent: MetricTone;
};

export const dashboardMetrics: DashboardMetric[] = [
    {
        label: 'Ventes du jour',
        value: '284 500 FCFA',
        trend: '+ 12,8 %',
        icon: Banknote,
        tone: 'gold',
        spark: [24, 38, 32, 52, 46, 67],
    },
    {
        label: 'Billets émis',
        value: '38',
        trend: '+ 6 aujourd’hui',
        icon: Ticket,
        tone: 'blue',
        spark: [31, 46, 39, 61, 53, 74],
    },
    {
        label: 'Caisse attendue',
        value: '337 000 FCFA',
        trend: 'Conforme',
        icon: CircleDollarSign,
        tone: 'navy',
        spark: [42, 38, 49, 57, 53, 64],
    },
    {
        label: 'Départs à venir',
        value: '7',
        trend: 'Prochain à 10:30',
        icon: BusFront,
        tone: 'green',
        spark: [22, 36, 42, 34, 55, 48],
    },
];

export const weeklyActivity = [58, 73, 62, 84, 77, 91, 68];

export const weeklyLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

export const dashboardActivities: DashboardActivity[] = [
    {
        category: 'Exploitation',
        title: 'Départ CTN–PAR retardé',
        detail: 'Mise à jour il y a 4 min',
        time: '10:30',
        icon: AlertTriangle,
        tone: 'urgent',
    },
    {
        category: 'Finance',
        title: 'Écart de rapprochement',
        detail: 'CassKai · 145 000 FCFA',
        time: '09:54',
        icon: FileCheck2,
        tone: 'control',
    },
    {
        category: 'Validé',
        title: 'Reversement validé',
        detail: 'KORA POINT Dantokpa',
        time: '09:21',
        icon: ReceiptText,
        tone: 'success',
    },
    {
        category: 'Colis',
        title: '6 colis à remettre',
        detail: 'Agence Cotonou Centre',
        time: '08:48',
        icon: PackageCheck,
        tone: 'parcel',
    },
];

export const quickActions: QuickAction[] = [
    {
        title: 'Nouvelle vente',
        description: 'Émettre un billet en quelques étapes',
        icon: Ticket,
        href: sale().url,
        accent: 'gold',
    },
    {
        title: 'Ma caisse',
        description: 'Contrôler les encaissements du jour',
        icon: CircleDollarSign,
        href: cash().url,
        accent: 'blue',
    },
    {
        title: 'Départs',
        description: 'Superviser la cadence du réseau',
        icon: Route,
        href: departures().url,
        accent: 'green',
    },
];

export const networkReadings = [
    { label: 'Ponctualité', value: '91,3 %', icon: Gauge },
    { label: 'Départs suivis', value: '46', icon: Activity },
    { label: 'À suivre', value: '5', icon: Route },
];

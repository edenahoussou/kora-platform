import {
    Building2,
    Bus,
    CalendarClock,
    CalendarRange,
    Layers,
    Route,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export const profitabilityAxisIcons: Record<string, LucideIcon> = {
    bus: Bus,
    line: Route,
    departure: CalendarClock,
    agency: Building2,
    period: CalendarRange,
};

export function profitabilityAxisIcon(axis: string): LucideIcon {
    return profitabilityAxisIcons[axis] ?? Layers;
}

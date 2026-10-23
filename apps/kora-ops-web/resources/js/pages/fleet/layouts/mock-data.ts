import { renumberSeats } from '@/components/seat-map/layout-utils';
import type {
    LayoutCell,
    LayoutGrid,
    SeatTemplate,
} from '@/components/seat-map/types';

const PATTERN_CELLS: Record<string, Pick<LayoutCell, 'kind' | 'seatKind'>> = {
    S: { kind: 'seat', seatKind: 'standard' },
    E: { kind: 'seat', seatKind: 'crew' },
    D: { kind: 'door' },
    W: { kind: 'driver' },
    '-': { kind: 'aisle' },
    '.': { kind: 'empty' },
};

function buildGrid(rows: string[]): LayoutGrid {
    const cells: LayoutCell[] = [];

    rows.forEach((row, y) => {
        [...row].forEach((symbol, x) => {
            const template = PATTERN_CELLS[symbol];

            if (template) {
                cells.push({ ...template, x, y });
            }
        });
    });

    return renumberSeats({
        rows: rows.length,
        cols: Math.max(...rows.map((row) => row.length)),
        cells,
    });
}

export const mockTemplates: SeatTemplate[] = [
    {
        uuid: 'tpl-standard-2plus2',
        name: 'Standard 2+2',
        code: 'STD-51',
        description:
            'Configuration classique des cars interurbains : deux sièges de part et d’autre de l’allée centrale.',
        status: 'active',
        busesCount: 3,
        updatedAt: '2026-10-18T09:24:00Z',
        grid: buildGrid([
            'W...D',
            'EE.SS',
            ...Array(11).fill('SS.SS'),
            'SSSSS',
        ]),
    },
    {
        uuid: 'tpl-premium-2plus1',
        name: 'Premium 2+1',
        code: 'PRM-36',
        description:
            'Trois sièges par rangée avec allée décalée, pour les liaisons longue distance à confort renforcé.',
        status: 'active',
        busesCount: 0,
        updatedAt: '2026-10-15T16:40:00Z',
        grid: buildGrid(['W..D', 'EE..', ...Array(10).fill('SS.S'), 'SSSS']),
    },
    {
        uuid: 'tpl-vip-2plus1',
        name: 'VIP 2+1',
        code: 'VIP-24',
        description:
            'Faible densité, rangées espacées : réservé aux services premium et affrètements.',
        status: 'draft',
        busesCount: 0,
        updatedAt: '2026-10-20T11:05:00Z',
        grid: buildGrid(['W..D', 'EE..', ...Array(6).fill('SS.S'), 'SSSS']),
    },
];

export function findTemplate(uuid: string | null | undefined) {
    return mockTemplates.find((template) => template.uuid === uuid);
}

/** Gabarit mocké le plus proche de la capacité réelle du bus (phase 1). */
export function templateForSeatCount(seatCount: number): SeatTemplate {
    return mockTemplates.reduce((best, template) => {
        const distance = (t: SeatTemplate) =>
            Math.abs(
                t.grid.cells.filter((cell) => cell.kind === 'seat').length -
                    seatCount,
            );

        return distance(template) < distance(best) ? template : best;
    });
}

export const templateStatusLabels: Record<SeatTemplate['status'], string> = {
    active: 'Actif',
    draft: 'Brouillon',
    archived: 'Archivé',
};

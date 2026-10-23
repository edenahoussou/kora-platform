export type LayoutCellKind = 'seat' | 'aisle' | 'empty' | 'door' | 'driver';

export type SeatKind = 'standard' | 'crew';

export type LayoutCell = {
    kind: LayoutCellKind;
    x: number;
    y: number;
    code?: string;
    seatKind?: SeatKind;
    codeLocked?: boolean;
    maintenance?: boolean;
};

export type LayoutGrid = {
    rows: number;
    cols: number;
    cells: LayoutCell[];
};

export type SeatTemplateStatus = 'active' | 'draft' | 'archived';

export type SeatTemplate = {
    uuid: string;
    name: string;
    code: string;
    description: string | null;
    status: SeatTemplateStatus;
    seatCount: number;
    busesCount: number;
    locked: boolean;
    updatedAt: string | null;
    grid: LayoutGrid;
};

export type OperationalSeatStatus = 'active' | 'maintenance' | 'blocked';

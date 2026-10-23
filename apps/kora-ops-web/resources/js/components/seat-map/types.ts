export type LayoutCellKind = 'seat' | 'aisle' | 'empty' | 'door' | 'driver';

export type SeatKind = 'standard' | 'crew';

export type LayoutCell = {
    kind: LayoutCellKind;
    x: number;
    y: number;
    code?: string;
    seatKind?: SeatKind;
    codeLocked?: boolean;
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
    description: string;
    status: SeatTemplateStatus;
    busesCount: number;
    updatedAt: string;
    grid: LayoutGrid;
};

export type OperationalSeatStatus = 'active' | 'maintenance' | 'blocked';

import type { SeatTemplateStatus } from '@/components/seat-map/types';

export const templateStatusLabels: Record<SeatTemplateStatus, string> = {
    active: 'Actif',
    draft: 'Brouillon',
    archived: 'Archivé',
};

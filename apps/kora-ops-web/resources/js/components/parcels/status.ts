export type ParcelStatusTone =
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive' | 'emerald' | 'amber';

export const parcelStatusLabels: Record<string, string> = {
    preregistered: 'Pré-enregistré',
    weighed: 'Pesé · devis',
    paid: 'Payé',
    ready: 'Prêt au départ',
    in_transit: 'En transit',
    arrived: 'Arrivé',
    withdrawn: 'Retiré',
    refused: 'Refusé',
    cancelled: 'Annulé',
    return_pending: 'Retour à charger',
    return_transit: 'Retour en transit',
    return_arrived: 'Retour arrivé',
    returned: 'Retourné',
};

const statusTones: Record<string, ParcelStatusTone> = {
    preregistered: 'amber',
    weighed: 'gold',
    paid: 'blue',
    ready: 'emerald',
    in_transit: 'navy',
    arrived: 'emerald',
    withdrawn: 'muted',
    refused: 'destructive',
    cancelled: 'muted',
    return_pending: 'amber',
    return_transit: 'navy',
    return_arrived: 'gold',
    returned: 'muted',
};

export function parcelStatusLabel(status: string): string {
    return parcelStatusLabels[status] ?? status;
}

export function parcelStatusTone(status: string): ParcelStatusTone {
    return statusTones[status] ?? 'blue';
}

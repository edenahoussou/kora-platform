export type ParcelStatusTone =
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive' | 'emerald' | 'amber';

export const parcelStatusLabels: Record<string, string> = {
    preregistered: 'Pré-enregistré',
    weighed: 'Pesé · devis',
    paid: 'Payé',
    registered: 'Payé',
    ready: 'Prêt au départ',
    assigned: 'Affecté',
    loaded: 'Chargé',
    in_transit: 'En transit',
    arrived: 'Arrivé',
    available: 'Disponible au retrait',
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
    registered: 'blue',
    ready: 'emerald',
    assigned: 'navy',
    loaded: 'gold',
    in_transit: 'navy',
    arrived: 'emerald',
    available: 'emerald',
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

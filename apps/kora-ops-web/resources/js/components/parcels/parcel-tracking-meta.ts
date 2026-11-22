import type { ParcelTrackingRole } from '@/types/parcel-tracking';

export type ParcelTrackingStatusMeta = {
    label: string;
    description: string;
    badge: string;
    dot: string;
};

const STATUS_META: Record<string, ParcelTrackingStatusMeta> = {
    preregistered: {
        label: 'Pré-enregistré',
        description:
            'Le colis est déclaré au guichet. Il attend la pesée et le contrôle de conformité.',
        badge: 'bg-amber-50 text-amber-800 ring-amber-200',
        dot: 'bg-amber-500',
    },
    weighed: {
        label: 'Pesé · devis',
        description:
            'Le poids réel est relevé. Le tarif est calculé avant paiement et enregistrement.',
        badge: 'bg-gold/15 text-kora ring-gold/40',
        dot: 'bg-gold',
    },
    paid: {
        label: 'Payé',
        description:
            'Le paiement est encaissé. Le colis peut être enregistré et préparé pour le départ.',
        badge: 'bg-sky-50 text-sky-800 ring-sky-200',
        dot: 'bg-sky-500',
    },
    registered: {
        label: 'Enregistré',
        description:
            'Le colis est pris en charge par KORA. Il attend son affectation à un départ.',
        badge: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        dot: 'bg-emerald-500',
    },
    ready: {
        label: 'Prêt au départ',
        description:
            'Le colis est pesé, payé et prêt à être affecté à un véhicule.',
        badge: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        dot: 'bg-emerald-500',
    },
    assigned: {
        label: 'Affecté',
        description:
            'Le colis est affecté à un départ. Le véhicule et l’heure de départ sont connus.',
        badge: 'bg-kora/10 text-kora ring-kora/20',
        dot: 'bg-kora',
    },
    loaded: {
        label: 'Chargé à bord',
        description:
            'Le colis est chargé dans le véhicule du départ. Il sera scanné au départ.',
        badge: 'bg-kora/10 text-kora ring-kora/20',
        dot: 'bg-kora',
    },
    in_transit: {
        label: 'En transit',
        description:
            'Le colis circule entre le point de départ et le hub de destination.',
        badge: 'bg-sky-50 text-sky-800 ring-sky-200',
        dot: 'bg-sky-500',
    },
    return_transit: {
        label: 'Retour en transit',
        description:
            'Le colis circule dans le circuit de retour vers son point d’origine.',
        badge: 'bg-sky-50 text-sky-800 ring-sky-200',
        dot: 'bg-sky-500',
    },
    unloaded: {
        label: 'Déchargé',
        description:
            'Le colis a été déchargé à l’arrivée du véhicule et attend la réception physique.',
        badge: 'bg-sky-50 text-sky-800 ring-sky-200',
        dot: 'bg-sky-500',
    },
    arrived: {
        label: 'Arrivé',
        description:
            'Le colis est arrivé au hub. La réception physique reste à confirmer.',
        badge: 'bg-gold/15 text-kora ring-gold/40',
        dot: 'bg-gold',
    },
    available: {
        label: 'Disponible au retrait',
        description:
            'Le colis est disponible au point de retrait. Le destinataire peut venir le retirer.',
        badge: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        dot: 'bg-emerald-500',
    },
    withdrawn: {
        label: 'Retiré',
        description:
            'Le colis a été remis au destinataire. Le dossier est clôturé côté remise.',
        badge: 'bg-slate-100 text-slate-700 ring-slate-200',
        dot: 'bg-slate-500',
    },
    refused: {
        label: 'Refusé au contrôle',
        description:
            'Le colis n’a pas passé le contrôle de conformité. Il n’entrera pas dans le circuit.',
        badge: 'bg-rose-50 text-rose-700 ring-rose-200',
        dot: 'bg-rose-500',
    },
    cancelled: {
        label: 'Annulé',
        description: 'Le parcours du colis a été annulé.',
        badge: 'bg-slate-100 text-slate-700 ring-slate-200',
        dot: 'bg-slate-500',
    },
    expired: {
        label: 'Délai de retrait dépassé',
        description:
            'Le délai de retrait est dépassé. Le colis entre dans le circuit de retour.',
        badge: 'bg-amber-50 text-amber-800 ring-amber-200',
        dot: 'bg-amber-500',
    },
    return_pending: {
        label: 'Retour à charger',
        description:
            'Le colis attend son chargement pour revenir vers le point d’origine.',
        badge: 'bg-amber-50 text-amber-800 ring-amber-200',
        dot: 'bg-amber-500',
    },
    return_arrived: {
        label: 'Retour arrivé',
        description:
            'Le colis est arrivé au point de retour et attend sa remise à l’expéditeur.',
        badge: 'bg-sky-50 text-sky-800 ring-sky-200',
        dot: 'bg-sky-500',
    },
    returned: {
        label: 'Retourné',
        description:
            'Le colis est revenu à l’expéditeur. La remise de retour reste à confirmer si nécessaire.',
        badge: 'bg-slate-100 text-slate-700 ring-slate-200',
        dot: 'bg-slate-500',
    },
};

export function parcelTrackingStatus(status: string): ParcelTrackingStatusMeta {
    return (
        STATUS_META[status] ?? {
            label: status.replaceAll('_', ' '),
            description: 'Le statut du colis a été mis à jour.',
            badge: 'bg-slate-100 text-slate-700 ring-slate-200',
            dot: 'bg-slate-500',
        }
    );
}

const EVENT_META: Record<string, { label: string; help: string }> = {
    preregistered: {
        label: 'Pré-enregistrement',
        help: 'Déclaration du colis au guichet, avant pesée.',
    },
    weighed: {
        label: 'Pesée',
        help: 'Poids réel relevé et tarif calculé.',
    },
    refused: {
        label: 'Refus au contrôle',
        help: 'Le colis n’a pas passé le contrôle de conformité.',
    },
    registered: {
        label: 'Enregistrement',
        help: 'Le colis est officiellement pris en charge par KORA.',
    },
    ready: {
        label: 'Prêt au départ',
        help: 'Le colis est prêt pour l’affectation.',
    },
    assigned: {
        label: 'Affectation',
        help: 'Le colis est affecté à un véhicule et à un départ.',
    },
    assignment_released: {
        label: 'Affectation libérée',
        help: 'Le colis a été retiré de son départ précédent.',
    },
    loaded: {
        label: 'Chargement',
        help: 'Le colis est chargé à bord du véhicule.',
    },
    in_transit: {
        label: 'Départ / transit',
        help: 'Le véhicule est parti et le colis circule vers sa destination.',
    },
    return_transit: {
        label: 'Transit retour',
        help: 'Le colis circule dans le circuit de retour.',
    },
    unloaded: {
        label: 'Déchargement',
        help: 'Le colis est déchargé à l’arrivée du véhicule.',
    },
    arrived: {
        label: 'Arrivée au hub',
        help: 'Le colis est arrivé au hub de destination.',
    },
    available: {
        label: 'Mise à disposition',
        help: 'Le colis est disponible au retrait.',
    },
    withdrawn: {
        label: 'Remise au destinataire',
        help: 'Le colis a été remis au destinataire.',
    },
    expired: {
        label: 'Délai dépassé',
        help: 'Le délai de retrait est dépassé.',
    },
    return_pending: {
        label: 'Retour en attente',
        help: 'Le colis attend la suite de son retour.',
    },
    return_arrived: {
        label: 'Arrivée retour',
        help: 'Le colis est arrivé au point de retour.',
    },
    returned: {
        label: 'Retour à l’expéditeur',
        help: 'Le colis est revenu à son point d’origine.',
    },
    return_handed_over: {
        label: 'Remise retour',
        help: 'Le colis a été remis dans le cadre du retour.',
    },
    cancelled: {
        label: 'Annulation',
        help: 'Le parcours du colis a été annulé.',
    },
    incident_reported: {
        label: 'Incident signalé',
        help: 'Un incident a été signalé sur le parcours.',
    },
    incident_opened: {
        label: 'Incident ouvert',
        help: 'Un incident suivi est ouvert sur le colis.',
    },
    incident_resolved: {
        label: 'Incident résolu',
        help: 'L’incident est clôturé.',
    },
    departure_changed: {
        label: 'Changement de départ',
        help: 'Le colis a été réaffecté à un autre départ.',
    },
    unloaded_at_origin: {
        label: 'Déchargé à l’origine',
        help: 'Le colis a été sorti du départ à son point d’origine.',
    },
    handoff_code_reissued: {
        label: 'Code de retrait renvoyé',
        help: 'Un nouveau code de retrait a été émis.',
    },
    handoff_code_rejected: {
        label: 'Code de retrait refusé',
        help: 'Une tentative de remise a échoué : le code présenté était incorrect.',
    },
    handoff_proof_requested: {
        label: 'Preuve de remise demandée',
        help: 'La preuve de remise a été demandée au guichet.',
    },
    tracking_link_issued: {
        label: 'Lien de suivi émis',
        help: 'Un lien de suivi a été généré pour ce colis.',
    },
    tracking_link_revoked: {
        label: 'Lien de suivi révoqué',
        help: 'Le lien de suivi précédent a été désactivé.',
    },
    print_requested: {
        label: 'Impression demandée',
        help: 'Une impression de document a été demandée.',
    },
    print_result: {
        label: 'Résultat d’impression',
        help: 'Le résultat de l’impression a été enregistré.',
    },
    departure_cancelled: {
        label: 'Départ annulé',
        help: 'Le départ associé au colis a été annulé.',
    },
    loaded_departure_cancelled: {
        label: 'Départ annulé après chargement',
        help: 'Le colis était chargé lorsque son départ a été annulé.',
    },
    duplicate_provider_receipt: {
        label: 'Reçu fournisseur en doublon',
        help: 'Un reçu fournisseur identique a déjà été enregistré.',
    },
    payment_reconciliation_required: {
        label: 'Rapprochement paiement requis',
        help: 'Un paiement doit être rapproché manuellement.',
    },
    refund_authorized: {
        label: 'Remboursement autorisé',
        help: 'Un remboursement a été autorisé.',
    },
    refund_completed: {
        label: 'Remboursement effectué',
        help: 'Le remboursement a été exécuté.',
    },
    return_charge_created: {
        label: 'Frais de retour créés',
        help: 'Les frais de retour ont été calculés pour ce colis.',
    },
    return_fee_paid: {
        label: 'Frais de retour payés',
        help: 'Les frais de retour ont été encaissés.',
    },
};

const VEHICLE_STATUS_LABELS: Record<string, string> = {
    scheduled: 'Planifié',
    boarding: 'Embarquement',
    departed: 'Parti',
    arrived: 'Arrivé',
    cancelled: 'Annulé',
};

export function parcelTrackingVehicleStatus(status: string): string {
    return VEHICLE_STATUS_LABELS[status] ?? status.replaceAll('_', ' ');
}

export function parcelTrackingEventMeta(kind: string): {
    label: string;
    help: string;
} {
    return (
        EVENT_META[kind] ?? {
            label: kind.replaceAll('_', ' '),
            help: 'Étape enregistrée dans le dossier Colis.',
        }
    );
}

export function parcelTrackingPointLabel(role: ParcelTrackingRole): string {
    switch (role) {
        case 'origin':
            return 'Point de départ';
        case 'destination':
            return 'Destination';
        case 'waypoint':
            return 'Arrêt du parcours';
        case 'current':
            return 'Position actuelle du bus';
        default:
            return 'Étape du colis';
    }
}

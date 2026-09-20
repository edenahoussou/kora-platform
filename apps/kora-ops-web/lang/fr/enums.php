<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Traductions des enums du système
    |--------------------------------------------------------------------------
    |
    | Ce fichier contient les libellés traduits pour tous les enums utilisés
    | dans l'application. Les clés sont générées automatiquement par le trait
    | HasEnumUtilities au format {snake_case_enum_name}.{case_value}.
    |
    */

    'system_role' => [
        'passenger' => 'Passager',
        'bus_driver' => 'Chauffeur de bus',
        'mechanic' => 'Mécanicien',
        'conductor' => 'Convoyeur',
        'agency_agent' => "Agent d'agence",
        'agency_manager' => "Chef d'agence",
        'operations_director' => 'Directeur des opérations',
        'commercial_director' => 'Directeur commercial',
        'general_management' => 'Direction générale',
        'system_admin' => 'Administrateur système',
    ],

    'user_status' => [
        'active' => 'Actif',
        'suspended' => 'Suspendu',
        'locked' => 'Bloqué',
    ],

    'agency_status' => [
        'active' => 'Active',
        'draft' => 'Brouillon',
        'suspended' => 'Suspendue',
        'archived' => 'Archivée',
    ],

    'device_platform' => [
        'android' => 'Android',
        'ios' => 'iOS',
        'web' => 'Web',
        'unknown' => 'Inconnu',
    ],

    'terminal_p501_status' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'maintenance' => 'En maintenance',
        'lost' => 'Perdu',
    ],

    'departure_status' => [
        'scheduled' => 'Planifié',
        'boarding' => 'Embarquement',
        'departed' => 'Parti',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
    ],

    'reservation_status' => [
        'held' => 'Pré-réservée',
        'assistance_pending' => 'Assistance en attente',
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'paid' => 'Payée',
        'ticketed' => 'Billetterie émise',
        'boarded' => 'Embarquée',
        'cancelled' => 'Annulée',
        'expired' => 'Expirée',
        'unavailable' => 'Indisponible',
    ],

    'assisted_booking_status' => [
        'pending' => 'En attente',
        'in_progress' => 'En cours',
        'cancelled' => 'Reprise en ligne',
        'refused' => 'Refusée',
        'unavailable' => 'Indisponible',
        'expired' => 'Expirée',
        'finalized' => 'Finalisée',
    ],

    'ticket_issuance_status' => [
        'not_started' => 'Non démarrée',
        'pending' => 'En attente',
        'processing' => 'En cours',
        'issued' => 'Billets émis',
        'failed' => 'Échec d’émission',
    ],

    'reservation_channel' => [
        'mobile_app' => 'Application mobile',
        'counter' => 'Guichet',
        'agency' => 'Agence',
    ],

    'ticket_status' => [
        'issued' => 'Émis',
        'used' => 'Utilisé',
        'cancelled' => 'Annulé',
        'refunded' => 'Remboursé',
    ],

    'passenger_journey_status' => [
        'hold_pending' => 'Réservation en attente',
        'hold_expired' => 'Hold expiré',
        'payment_pending' => 'Paiement en attente',
        'payment_confirmed' => 'Paiement confirmé',
        'ticket_issued' => 'Billet émis',
        'boarded' => 'Embarqué',
        'absent' => 'Absent',
        'cancelled' => 'Annulé',
    ],

    'payment_status' => [
        'pending' => 'En attente',
        'completed' => 'Complété',
        'failed' => 'Échoué',
        'refunded' => 'Remboursé',
    ],

    'cash_session_status' => [
        'open' => 'Ouverte',
        'closed' => 'Clôturée',
        'reconciled' => 'Réconciliée',
    ],

    'bus_status' => [
        'active' => 'Actif',
        'maintenance' => 'En maintenance',
        'retired' => 'Retiré',
    ],

    'line_status' => [
        'active' => 'Active',
        'suspended' => 'Suspendue',
        'planned' => 'Planifiée',
    ],

    'seat_status' => [
        'available' => 'Disponible',
        'occupied' => 'Occupée',
        'reserved' => 'Réservée',
        'blocked' => 'Bloquée',
    ],

    'boarding_event_status' => [
        'boarded' => 'Embarqué',
        'denied' => 'Refusé',
        'reversed' => 'Annulé',
    ],

    'seat_release_event_status' => [
        'released' => 'Libéré',
        'reversed' => 'Annulé',
    ],

    'fare_status' => [
        'active' => 'Actif',
        'draft' => 'Brouillon',
        'expired' => 'Expiré',
        'archived' => 'Archivé',
    ],

    'fiscal_invoice_status' => [
        'pending' => 'En attente',
        'validated' => 'Validée',
        'rejected' => 'Rejetée',
        'cancelled' => 'Annulée',
    ],

    'incident_status' => [
        'open' => 'Ouvert',
        'in_progress' => 'En cours',
        'resolved' => 'Résolu',
        'closed' => 'Clôturé',
    ],

    'incident_type' => [
        'mechanical' => 'Mécanique',
        'electrical' => 'Électrique',
        'accident' => 'Accident',
        'other' => 'Autre',
    ],

    'passenger_document_type' => [
        'national_id' => 'Carte nationale',
        'biometric_card' => 'Carte biométrique',
        'passport' => 'Passeport',
        'consular_card' => 'Carte consulaire',
        'cip' => 'CIP',
    ],

    'promotion_status' => [
        'draft' => 'Brouillon',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archivée',
    ],

    'promotion_display_status' => [
        'scheduled' => 'Planifiée',
        'expired' => 'Expirée',
    ],

    'promotion_scope' => [
        'network' => 'Réseau national',
        'channel' => 'Canal de vente',
        'line' => 'Ligne',
        'segment' => 'Segment O/D',
    ],

    'promotion_benefit_type' => [
        'percentage' => 'Pourcentage',
        'fixed_amount' => 'Montant fixe',
    ],

    'promotion_channel' => [
        'app' => 'Application KORA',
        'counter' => 'Guichet',
    ],
];

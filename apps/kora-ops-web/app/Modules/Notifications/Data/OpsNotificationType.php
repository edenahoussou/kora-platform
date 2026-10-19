<?php

namespace App\Modules\Notifications\Data;

/**
 * Événements opérationnels boarding notifiés au convoyeur et aux opérateurs
 * OPS. Le code (valeur de l'enum) est le contrat renvoyé aux clients.
 */
enum OpsNotificationType: string
{
    case DepartureAssigned = 'departure_assigned';
    case DepartureModified = 'departure_modified';
    case ManifestStale = 'manifest_stale';
    case TerminalRevoked = 'terminal_revoked';
    case ScanConflict = 'scan_conflict';
    case OperationRejected = 'operation_rejected';
    case SyncCompleted = 'sync_completed';
    case SyncFailed = 'sync_failed';
    case NoShowClosed = 'no_show_closed';
    case PrintError = 'print_error';

    /** Le convoyeur a marqué le bus parti (MEZ-98). Information, sans e-mail. */
    case DepartureDeparted = 'departure_departed';

    /** Incident signalé par un convoyeur : panne, retard, autre (MEZ-98). */
    case IncidentReported = 'incident_reported';

    /** Incident grave (accident, sécurité) : alerte critique, non désactivable. */
    case IncidentCritical = 'incident_critical';

    case FiscalizationFailed = 'fiscalization_failed';
    case CashVarianceSubmitted = 'cash_variance_submitted';
    case CashSessionLeftOpen = 'cash_session_left_open';
    case CashSessionForceClosed = 'cash_session_force_closed';
    case AssistedBookingRequested = 'assisted_booking_requested';
    case AssistedBookingReassigned = 'assisted_booking_reassigned';
    case DepartureDelayed = 'departure_delayed';

    public function severity(): OpsNotificationSeverity
    {
        return match ($this) {
            self::TerminalRevoked, self::ScanConflict, self::IncidentCritical, self::FiscalizationFailed => OpsNotificationSeverity::Critical,
            self::ManifestStale, self::OperationRejected, self::SyncFailed, self::PrintError, self::IncidentReported, self::CashSessionLeftOpen, self::CashSessionForceClosed => OpsNotificationSeverity::Warning,
            self::DepartureAssigned, self::DepartureModified, self::SyncCompleted, self::NoShowClosed, self::DepartureDeparted, self::CashVarianceSubmitted, self::AssistedBookingRequested, self::AssistedBookingReassigned, self::DepartureDelayed => OpsNotificationSeverity::Info,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::DepartureAssigned => 'Départ affecté',
            self::DepartureModified => 'Départ modifié',
            self::ManifestStale => 'Manifeste à actualiser',
            self::TerminalRevoked => 'Terminal révoqué',
            self::ScanConflict => 'Conflit de scan',
            self::OperationRejected => 'Opérations rejetées',
            self::SyncCompleted => 'Synchronisation terminée',
            self::SyncFailed => 'Synchronisation échouée',
            self::NoShowClosed => 'No-show clôturés',
            self::PrintError => 'Erreur d’impression',
            self::DepartureDeparted => 'Bus parti',
            self::IncidentReported => 'Incident signalé',
            self::IncidentCritical => 'Incident grave signalé',
            self::FiscalizationFailed => 'Échec de fiscalisation',
            self::CashVarianceSubmitted => 'Écart de caisse à approuver',
            self::CashSessionLeftOpen => 'Caisse non clôturée',
            self::CashSessionForceClosed => 'Caisse clôturée de force',
            self::AssistedBookingRequested => 'Nouvelle demande d’assistance',
            self::AssistedBookingReassigned => 'Demande réaffectée',
            self::DepartureDelayed => 'Départ retardé',
        };
    }

    public function action(): OpsNotificationAction
    {
        return match ($this) {
            self::DepartureAssigned, self::DepartureModified, self::NoShowClosed, self::DepartureDeparted, self::IncidentReported, self::IncidentCritical, self::DepartureDelayed => OpsNotificationAction::OpenDeparture,
            self::ManifestStale => OpsNotificationAction::RefreshManifest,
            self::TerminalRevoked => OpsNotificationAction::Reauthenticate,
            self::ScanConflict => OpsNotificationAction::ReviewScanConflict,
            self::OperationRejected => OpsNotificationAction::ReviewRejectedOperations,
            self::SyncFailed => OpsNotificationAction::RetrySync,
            self::PrintError => OpsNotificationAction::CheckPrinter,
            self::SyncCompleted => OpsNotificationAction::None,
            self::FiscalizationFailed, self::CashVarianceSubmitted, self::CashSessionLeftOpen, self::CashSessionForceClosed, self::AssistedBookingRequested, self::AssistedBookingReassigned => OpsNotificationAction::None,
        };
    }

    /**
     * Alerte : en plus de la notification, un e-mail est envoyé (directeurs
     * des opérations de l'agence du convoyeur en destinataires,
     * super-administrateurs en copie) et les super-administrateurs sont
     * notifiés.
     */
    public function sendsMail(): bool
    {
        return match ($this) {
            self::TerminalRevoked, self::ScanConflict, self::OperationRejected, self::SyncFailed, self::PrintError, self::IncidentReported, self::IncidentCritical, self::FiscalizationFailed, self::CashVarianceSubmitted, self::CashSessionLeftOpen, self::CashSessionForceClosed, self::AssistedBookingRequested, self::AssistedBookingReassigned, self::DepartureDelayed => true,
            default => false,
        };
    }

    public function notifiesSystemAdministrators(): bool
    {
        return match ($this) {
            self::TerminalRevoked, self::ScanConflict, self::OperationRejected, self::SyncFailed, self::PrintError, self::IncidentReported, self::IncidentCritical, self::FiscalizationFailed, self::CashVarianceSubmitted, self::AssistedBookingRequested, self::AssistedBookingReassigned, self::DepartureDelayed => true,
            default => false,
        };
    }

    /** Une alerte critique ne peut pas être désactivée par l'utilisateur. */
    public function isDisableable(): bool
    {
        return $this->severity() !== OpsNotificationSeverity::Critical;
    }

    /** Les directeurs des opérations de l'agence du convoyeur suivent cet événement. */
    public function notifiesOperationsDirectors(): bool
    {
        return match ($this) {
            self::ManifestStale, self::SyncCompleted => false,
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}

<?php

namespace App\Modules\IdentityAccess\Data;

/**
 * Événements sensibles déclenchant une alerte de sécurité par e-mail.
 */
enum SecurityAlertEvent: string
{
    case PhoneChanged = 'phone_changed';
    case PasswordChanged = 'password_changed';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case RecoveryCodesGenerated = 'recovery_codes_generated';
    case WebSessionRevoked = 'web_session_revoked';
    case OtherSessionsRevoked = 'other_sessions_revoked';
    case DeviceRevoked = 'device_revoked';
    case TerminalSessionRevoked = 'terminal_session_revoked';
    case AvatarUpdated = 'avatar_updated';

    public function subject(): string
    {
        return match ($this) {
            self::PhoneChanged => 'Votre numéro de téléphone a été modifié',
            self::PasswordChanged => 'Votre mot de passe a été modifié',
            self::TwoFactorEnabled => 'La double authentification a été activée',
            self::TwoFactorDisabled => 'La double authentification a été désactivée',
            self::RecoveryCodesGenerated => 'Vos codes de récupération ont été régénérés',
            self::WebSessionRevoked => 'Une session a été déconnectée',
            self::OtherSessionsRevoked => 'Toutes vos autres sessions ont été déconnectées',
            self::DeviceRevoked => 'Un appareil de confiance a été retiré',
            self::TerminalSessionRevoked => 'Une session terminal P501 a été révoquée',
            self::AvatarUpdated => 'Votre photo de profil a été modifiée',
        };
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return match ($this) {
            self::PhoneChanged => [
                'Le numéro de téléphone associé à votre compte KORA OPS vient d\'être modifié.',
                'Ce numéro est utilisé pour les alertes opérationnelles et la vérification d\'identité.',
            ],
            self::PasswordChanged => [
                'Le mot de passe de votre compte KORA OPS vient d\'être modifié.',
            ],
            self::TwoFactorEnabled => [
                'La double authentification (TOTP) vient d\'être activée sur votre compte.',
                'Vos prochaines connexions exigeront un code généré par votre application d\'authentification.',
            ],
            self::TwoFactorDisabled => [
                'La double authentification (TOTP) vient d\'être désactivée sur votre compte.',
                'Votre compte est désormais protégé uniquement par votre mot de passe.',
            ],
            self::RecoveryCodesGenerated => [
                'Une nouvelle série de codes de récupération vient d\'être générée pour votre compte.',
                'Les codes précédents ne sont plus valides.',
            ],
            self::WebSessionRevoked => [
                'Une session de navigateur associée à votre compte vient d\'être déconnectée à distance.',
            ],
            self::OtherSessionsRevoked => [
                'Toutes les sessions de navigateur autres que la session courante ont été déconnectées.',
            ],
            self::DeviceRevoked => [
                'Un appareil de confiance associé à votre compte vient d\'être retiré.',
                'Cet appareil ne recevra plus de notifications push et devra être ré-enregistré.',
            ],
            self::TerminalSessionRevoked => [
                'Une session de terminal mobile P501 associée à votre compte vient d\'être révoquée.',
            ],
            self::AvatarUpdated => [
                'La photo de profil de votre compte KORA OPS vient d\'être modifiée.',
            ],
        };
    }
}

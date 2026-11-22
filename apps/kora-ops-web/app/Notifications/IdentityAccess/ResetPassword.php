<?php

namespace App\Notifications\IdentityAccess;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Réinitialisez votre mot de passe')
            ->markdown('notifications::email', ['preview' => 'Utilisez ce lien pour choisir un nouveau mot de passe.'])
            ->greeting('Bonjour,')
            ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte Kora Express.')
            ->action('Réinitialiser mon mot de passe', $url)
            ->line("Ce lien de réinitialisation expirera dans **{$expire} minutes**.")
            ->line('Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer ce message.')
            ->salutation('Cordialement,');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}

<?php

use App\Models\User;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Notifications\IdentityAccess\ResetPassword;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;

it('brands HTML and text mail when Laravel sends it', function (bool $staging, string $brand): void {
    config([
        'app.brand.name' => $brand,
        'app.brand.staging' => $staging,
        'mail.default' => 'array',
        'legal.company_director' => 'Gérant Exemple',
    ]);

    IntegrationsSettings::fake([
        'emecfShopName' => 'Entreprise Exemple',
        'emecfDefaultIfu' => '1234567890123',
        'emecfRccm' => 'RB/EXEMPLE',
        'emecfAddress1' => 'Rue des Acacias',
        'emecfAddress2' => 'Quartier Central',
        'emecfAddress3' => 'Cotonou',
    ]);

    Event::fake([MessageSent::class]);

    $this->travelTo(Date::parse('2026-12-31 23:30:00 UTC'), function (): void {
        $user = new User(['email' => 'recipient@example.test']);
        Notification::sendNow($user, new ResetPassword('example-token'));
    });

    $legalLine = '© 2027 Entreprise Exemple · IFU 1234567890123 · RCCM RB/EXEMPLE · Rue des Acacias, Quartier Central, Cotonou · Gérant Gérant Exemple';
    $subject = ($staging ? '[STAGING] ' : '').'Réinitialisez votre mot de passe';

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($brand, $legalLine, $subject): bool {
        $message = $event->sent->getOriginalMessage();
        expect($message)->toBeInstanceOf(Email::class);
        assert($message instanceof Email);

        expect($message->getSubject())->toBe($subject)
            ->and($message->getFrom()[0]->getName())->toBe($brand)
            ->and($message->getHtmlBody())->toContain($legalLine, 'Utilisez ce lien pour choisir un nouveau mot de passe.', 'example-token')
            ->and($message->getTextBody())->toContain($legalLine, 'example-token')
            ->and($message->getAttachments())->toHaveCount(1)
            ->and($message->getAttachments()[0]->getDisposition())->toBe('inline')
            ->and($message->getAttachments()[0]->getMediaSubtype())->toBe('png')
            ->and($message->getHtmlBody())->toContain('src="cid:'.$message->getAttachments()[0]->getContentId().'"');

        return true;
    });
})->with([
    'production' => [false, 'Kora Express'],
    'staging' => [true, 'Kora Express Staging'],
]);

it('does not duplicate the staging prefix or change the sender email address', function (): void {
    config([
        'app.brand.name' => 'Kora Express Staging',
        'app.brand.staging' => true,
        'mail.default' => 'array',
        'mail.from.address' => 'sender@example.test',
    ]);

    $sent = Mail::raw('Message de contrôle', function ($message): void {
        $message->to('recipient@example.test')->subject('[STAGING] Contrôle');
    });

    $message = $sent?->getSymfonySentMessage()->getOriginalMessage();
    assert($message instanceof Email);

    expect($message->getSubject())->toBe('[STAGING] Contrôle')
        ->and($message->getFrom()[0]->getAddress())->toBe('sender@example.test');
});

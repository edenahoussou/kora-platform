<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

class ApplyTransactionalMailBrand
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;
        $senders = array_map(
            fn (Address $address): Address => new Address($address->getAddress(), config('app.brand.name')),
            $message->getFrom(),
        );

        if ($senders !== []) {
            $message->from(...$senders);
        }

        $subject = $message->getSubject();

        if (config('app.brand.staging') && filled($subject) && ! Str::startsWith($subject, '[STAGING]')) {
            $message->subject('[STAGING] '.$subject);
        }
    }
}

<?php

use Symfony\Component\Process\Process;

/**
 * ItineraryLegQuoteRequest et ItineraryQuoteResult sont instanciés par le
 * PUT bookings/{uuid}/promo-code, qui ne charge jamais
 * CalculateItineraryQuoteAction : déclarés dans ce fichier, ils étaient
 * introuvables par l'autoloader PSR-4 et le endpoint répondait 500 en
 * production (le test HTTP passait car le devis les chargeait avant).
 * ItineraryLegData/ItineraryData avaient le même piège latent.
 */
it('autoloads the itinerary DTOs in a fresh PHP process', function (): void {
    $classes = [
        'App\\Modules\\Booking\\Actions\\ItineraryLegQuoteRequest',
        'App\\Modules\\Booking\\Actions\\ItineraryQuoteResult',
        'App\\Modules\\Booking\\Services\\ItineraryLegData',
        'App\\Modules\\Booking\\Services\\ItineraryData',
    ];

    $script = 'require "vendor/autoload.php";'
        .'var_export('.var_export($classes, true)
        .' === array_filter('.var_export($classes, true).', "class_exists"));';

    $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__, 2));
    $process->run();

    expect($process->isSuccessful())->toBeTrue()
        ->and(trim($process->getOutput()))->toBe('true');
});

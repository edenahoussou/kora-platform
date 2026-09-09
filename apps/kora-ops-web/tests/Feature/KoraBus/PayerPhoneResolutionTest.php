<?php

use App\Models\User;
use App\Modules\IdentityAccess\Services\PhonePassengerIdentityResolver;

it('resolves a user by exact international phone match', function (): void {
    $user = User::factory()->create([
        'phone' => '+2290191003606',
        'country_code' => '+229',
    ]);

    $resolver = app(PhonePassengerIdentityResolver::class);
    $resolved = $resolver->resolveByPhone('+2290191003606');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($user->id);
});

it('returns null when no user matches the phone', function (): void {
    $resolver = app(PhonePassengerIdentityResolver::class);
    $resolved = $resolver->resolveByPhone('+22999999999');

    expect($resolved)->toBeNull();
});

it('does not create a duplicate when the payer phone matches an existing user', function (): void {
    $user = User::factory()->create([
        'name' => 'Kevin Mobile',
        'phone' => '+2290191003606',
        'country_code' => '+229',
    ]);

    $resolver = app(PhonePassengerIdentityResolver::class);
    $resolved = $resolver->resolveOrCreateByPhone('+2290191003606');

    expect($resolved->id)->toBe($user->id)
        ->and(User::query()->where('phone', '+2290191003606')->count())->toBe(1);
});

it('normalizes an international phone to canonical format', function (): void {
    $resolver = app(PhonePassengerIdentityResolver::class);

    expect($resolver->normalizePhone('+2290191003606'))->toBe('+2290191003606')
        ->and($resolver->normalizePhone('  +229 01 91 00 36 06  '))->toBe('+2290191003606')
        ->and($resolver->normalizePhone('002290191003606'))->toBe('+2290191003606');
});

it('normalizes a local phone using the provided country code', function (): void {
    $resolver = app(PhonePassengerIdentityResolver::class);

    expect($resolver->normalizePhone('0191003606', '+229'))->toBe('+2290191003606')
        ->and($resolver->normalizePhone('0712345678', '+225'))->toBe('+2250712345678');
});

it('normalizes a local phone without country code using Benin default', function (): void {
    $resolver = app(PhonePassengerIdentityResolver::class);

    expect($resolver->normalizePhone('0191003606'))->toBe('+2290191003606');
});

it('returns null when normalizing an empty phone', function (): void {
    $resolver = app(PhonePassengerIdentityResolver::class);

    expect($resolver->normalizePhone(null))->toBeNull()
        ->and($resolver->normalizePhone(''))->toBeNull()
        ->and($resolver->normalizePhone('  '))->toBeNull();
});

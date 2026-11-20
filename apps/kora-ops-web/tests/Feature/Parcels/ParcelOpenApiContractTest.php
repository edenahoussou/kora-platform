<?php

use Symfony\Component\Yaml\Yaml;

it('publishes a complete and non-leaking OpenAPI contract for every parcel client operation', function (): void {
    $path = base_path('../../docs/mintlify/api/openapi.yaml');
    expect($path)->toBeFile();

    $spec = Yaml::parseFile($path);
    expect($spec['openapi'])->toBe('3.0.3')
        ->and($spec['info']['title'])->toContain('KORA')
        ->and($spec['paths'])->toBeArray();

    $expected = [
        '/api/v1/parcels',
        '/api/v1/parcels/corridors',
        '/api/v1/parcels/quote',
        '/api/v1/parcels/tracking/{reference}',
        '/api/v1/parcels/shared/{token}',
        '/api/v1/parcels/{uuid}',
        '/api/v1/parcels/{parcel_uuid}/cancel',
        '/api/v1/parcels/{parcel_uuid}/share',
        '/api/v1/parcels/{parcel_uuid}/incidents',
        '/api/v1/parcels/{parcel_uuid}/documents',
        '/api/v1/parcels/{parcel_uuid}/payment-methods',
        '/api/v1/parcels/{parcel_uuid}/payments',
        '/api/v1/parcels/{parcel_uuid}/payments/{uuid}',
        '/api/v1/parcels/{parcel_uuid}/payments/{payment_uuid}/verify',
    ];

    foreach ($expected as $pathItem) {
        expect($spec['paths'])->toHaveKey($pathItem);
    }

    foreach ($expected as $pathItem) {
        preg_match_all('/\{([^}]+)\}/', $pathItem, $variables);
        $pathParameters = $spec['paths'][$pathItem]['parameters'] ?? [];
        foreach ($spec['paths'][$pathItem] as $method => $operation) {
            if (! is_array($operation) || ! in_array($method, ['get', 'post', 'delete', 'put', 'patch'], true)) {
                continue;
            }
            $parameters = array_merge($pathParameters, $operation['parameters'] ?? []);
            $names = array_column(array_filter($parameters, fn (array $parameter): bool => ($parameter['in'] ?? null) === 'path'), 'name');
            foreach ($variables[1] as $variable) {
                expect($names)->toContain($variable);
            }
        }
    }

    $tracking = $spec['paths']['/api/v1/parcels/tracking/{reference}']['get'];
    $example = $tracking['responses'][200]['content']['application/json']['schema']['example']['data'];
    expect(array_keys($example))->toBe([
        'reference', 'status', 'origin', 'destination', 'weight_grams',
        'withdraw_by', 'eta', 'last_event_at', 'timeline',
    ])->and($example)->not->toHaveKey('sender_name')
        ->not->toHaveKey('recipient_name')
        ->not->toHaveKey('sender_phone')
        ->not->toHaveKey('recipient_phone')
        ->not->toHaveKey('contents')
        ->not->toHaveKey('handoff_code')
        ->not->toHaveKey('share_hash');

    foreach ($expected as $pathItem) {
        foreach ($spec['paths'][$pathItem] as $method => $operation) {
            if (! is_array($operation) || ! in_array($method, ['get', 'post', 'delete', 'put', 'patch'], true)) {
                continue;
            }
            foreach ($operation['responses'] ?? [] as $status => $response) {
                if (! in_array((int) $status, [200, 201], true)) {
                    continue;
                }
                $content = $response['content']['application/json'] ?? [];
                expect($content)->not->toBe([]);
                expect($content['schema']['example']['data'] ?? null)->not->toBe([]);
            }
        }
    }

    foreach (['/api/v1/parcels/tracking/{reference}', '/api/v1/parcels/shared/{token}'] as $publicPath) {
        $operation = $spec['paths'][$publicPath]['get'];
        $parameters = array_merge(
            $spec['paths'][$publicPath]['parameters'] ?? [],
            $operation['parameters'] ?? [],
        );
        expect($parameters)->not->toBeEmpty();
    }
});

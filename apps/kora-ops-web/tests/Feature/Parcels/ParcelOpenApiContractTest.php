<?php

use Symfony\Component\Yaml\Yaml;

function parcelOpenApiExample(array $media): mixed
{
    return $media['example'] ?? $media['schema']['example'] ?? null;
}

it('publishes a complete and non-leaking OpenAPI contract for every parcel client operation', function (): void {
    $path = base_path('../../docs/mintlify/api/openapi.yaml');
    expect($path)->toBeFile();

    $spec = Yaml::parseFile($path);
    expect($spec['openapi'])->toBe('3.0.3')
        ->and($spec['info']['title'])->toContain('KORA')
        ->and($spec['paths'])->toBeArray()
        ->and($spec['components']['schemas'])->toHaveKeys(['Parcel', 'Payment', 'Error', 'ValidationError']);

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

    $operations = [];
    foreach ($expected as $pathItem) {
        preg_match_all('/\{([^}]+)\}/', $pathItem, $variables);
        $pathParameters = $spec['paths'][$pathItem]['parameters'] ?? [];
        foreach ($spec['paths'][$pathItem] as $method => $operation) {
            if (! is_array($operation) || ! in_array($method, ['get', 'post', 'delete', 'put', 'patch'], true)) {
                continue;
            }
            $operations[] = [$pathItem, $method, $operation];

            $parameters = array_merge($pathParameters, $operation['parameters'] ?? []);
            $names = array_column(array_filter($parameters, fn (array $parameter): bool => ($parameter['in'] ?? null) === 'path'), 'name');
            foreach ($variables[1] as $variable) {
                expect($names)->toContain($variable);
            }

            $security = $operation['security'] ?? $spec['security'] ?? [];
            if ($security !== []) {
                expect($operation['responses'])->toHaveKey(401)
                    ->and($operation['responses'])->toHaveKey(403);
            }

            if ($method === 'get') {
                expect($operation)->not->toHaveKey('requestBody');
            }

            if (isset($operation['requestBody'])) {
                $media = $operation['requestBody']['content']['application/json'] ?? null;
                expect($media)->not->toBeNull();
                if ($media !== null) {
                    $hasExample = parcelOpenApiExample($media) !== null || ($media['examples'] ?? []) !== [];
                    expect($hasExample)->toBeTrue();
                }
            }
        }
    }

    $tracking = $spec['paths']['/api/v1/parcels/tracking/{reference}']['get'];
    $media = $tracking['responses'][200]['content']['application/json'];
    $example = parcelOpenApiExample($media)['data'];
    expect(array_keys($example))->toBe([
        'reference', 'status', 'origin', 'destination', 'weight_grams',
        'withdraw_by', 'eta', 'last_event_at', 'timeline',
    ])->and($example)->not->toHaveKey('sender_name')
        ->not->toHaveKey('recipient_name')
        ->not->toHaveKey('sender_phone')
        ->not->toHaveKey('recipient_phone')
        ->not->toHaveKey('contents')
        ->not->toHaveKey('handoff_code')
        ->not->toHaveKey('share_hash')
        ->not->toHaveKey('provider_payload');

    foreach ($operations as [$pathItem, $method, $operation]) {
        foreach ($operation['responses'] ?? [] as $status => $response) {
            $code = (int) $status;
            $media = $response['content']['application/json'] ?? null;

            if (in_array($code, [200, 201], true)) {
                expect($media)->not->toBeNull()
                    ->and($media['schema'] ?? [])->not->toBe([])
                    ->and(parcelOpenApiExample($media) !== null || ($media['examples'] ?? []) !== [])->toBeTrue();
                if (($media['examples'] ?? []) !== []) {
                    foreach ($media['examples'] as $entry) {
                        expect($entry['value'] ?? null)->not->toBeEmpty();
                    }
                }
            }

            if ($code === 204) {
                expect($response)->not->toHaveKey('content');
            }

            if (in_array($code, [401, 403, 404, 409, 422, 429], true)) {
                expect($media)->not->toBeNull();
                if ($media === null) {
                    continue;
                }
                $error = parcelOpenApiExample($media);
                expect($error)->toBeArray()->toHaveKey('message')
                    ->and($error['message'])->not->toBe('');
                if ($code === 401) {
                    expect($error['message'])->toBe('Unauthenticated.');
                }
                if ($code === 403) {
                    expect($error['message'])->toBe('This action is unauthorized.');
                }
            }
        }
    }

    $pay = $spec['paths']['/api/v1/parcels/{parcel_uuid}/payments']['post'];
    expect($pay['responses'])->toHaveKeys([200, 201, 409, 422, 429])
        ->and($pay['requestBody']['content']['application/json']['schema']['properties']['method']['enum'])
        ->toBe(['mobile_money', 'card', 'wallet']);

    $verify = $spec['paths']['/api/v1/parcels/{parcel_uuid}/payments/{payment_uuid}/verify']['post'];
    expect($verify['responses'])->toHaveKey(422)
        ->and($verify['responses'])->not->toHaveKey(409);

    $documentPath = $spec['paths']['/api/v1/parcels/{parcel_uuid}/documents'];
    $documents = $documentPath['get'];
    $documentParameters = array_merge($documentPath['parameters'] ?? [], $documents['parameters'] ?? []);
    expect($documents)->not->toHaveKey('requestBody')
        ->and(array_column($documentParameters, 'name'))->toContain('kind')
        ->and($documents['responses'])->toHaveKeys([200, 401, 403, 404, 409]);

    $revoke = $spec['paths']['/api/v1/parcels/{parcel_uuid}/share']['delete'];
    expect($revoke['responses'])->toHaveKey(204)
        ->and($revoke['responses'][204])->not->toHaveKey('content');

    $cancel = $spec['paths']['/api/v1/parcels/{parcel_uuid}/cancel']['post'];
    expect($cancel['responses'][409]['content']['application/json']['schema']['example']['message'])
        ->toBe('Le colis ne peut pas être annulé dans son statut actuel.');

    $incident = $spec['paths']['/api/v1/parcels/{parcel_uuid}/incidents']['post'];
    $incidentSchema = $incident['requestBody']['content']['application/json']['schema'];
    expect($incidentSchema['properties']['kind']['enum'])
        ->toBe(['loss', 'damage', 'custody_discrepancy'])
        ->and($incidentSchema['properties']['description']['maxLength'])->toBe(2000)
        ->and($incident['responses'][409]['content']['application/json']['schema']['example']['message'])
        ->toBe('The parcel has no logistics reference.')
        ->and($incident['responses'][422]['content']['application/json']['schema']['example']['message'])
        ->toBe('La valeur sélectionnée pour kind est invalide.');

    $store = $spec['paths']['/api/v1/parcels']['post'];
    $storeSchema = $store['requestBody']['content']['application/json']['schema'];
    expect($storeSchema['required'])->not->toContain('corridor_uuid')
        ->and($storeSchema['properties'])->toHaveKeys(['corridor_uuid', 'deposit_hub_uuid', 'destination_hub_uuid']);

    foreach (['/api/v1/parcels/tracking/{reference}', '/api/v1/parcels/shared/{token}'] as $publicPath) {
        $operation = $spec['paths'][$publicPath]['get'];
        $parameters = array_merge(
            $spec['paths'][$publicPath]['parameters'] ?? [],
            $operation['parameters'] ?? [],
        );
        expect($parameters)->not->toBeEmpty()
            ->and($operation['security'])->toBe([]);
    }

    foreach (['/api/v1/parcels/tracking/{reference}', '/api/v1/parcels/shared/{token}', '/api/v1/parcels/corridors'] as $publicPath) {
        $operation = $spec['paths'][$publicPath]['get'];
        expect($operation['security'])->toBe([]);
    }
});

<?php

namespace App\Modules\Fiscalization\Services;

use App\Models\User;
use App\Modules\Settings\Settings\IntegrationsSettings;

class FiscalTestCaseCatalog
{
    /** @return list<array{number: int, title: string, description: string, needsClientIfu: bool}> */
    public function cases(): array
    {
        return [
            ['number' => 1, 'title' => 'Vente exonérée', 'description' => 'Un article exonéré, quantité 1.', 'needsClientIfu' => false],
            ['number' => 2, 'title' => 'Vente taxable', 'description' => 'Un article taxable, quantité 1.', 'needsClientIfu' => false],
            ['number' => 3, 'title' => 'Vente exonérée et taxable', 'description' => 'Article exonéré quantité 2 et article taxable quantité 3.', 'needsClientIfu' => false],
            ['number' => 4, 'title' => 'Quantités décimales', 'description' => 'Article exonéré quantité 2,5 et article taxable quantité 3,250.', 'needsClientIfu' => false],
            ['number' => 5, 'title' => 'Client identifié', 'description' => 'Vente exonérée et taxable avec IFU et nom du client.', 'needsClientIfu' => true],
            ['number' => 6, 'title' => 'Facture de remboursement', 'description' => 'Avoir complet de la facture certifiée du test 5.', 'needsClientIfu' => false],
            ['number' => 7, 'title' => 'Taxe spécifique', 'description' => 'Article taxable quantité 3 avec taxe spécifique et client identifié.', 'needsClientIfu' => true],
            ['number' => 8, 'title' => 'AIB à 5 %', 'description' => 'Articles exonéré et taxable avec AIB à 5 %.', 'needsClientIfu' => false],
            ['number' => 9, 'title' => 'AIB à 1 %', 'description' => 'Articles exonéré et taxable avec AIB à 1 % et client identifié.', 'needsClientIfu' => true],
            ['number' => 10, 'title' => 'Taxe spécifique et AIB', 'description' => 'Articles exonéré et taxable avec taxe spécifique et AIB à 5 %.', 'needsClientIfu' => false],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(int $caseNumber, IntegrationsSettings $settings, User $operator, ?string $clientIfu): array
    {
        $items = match ($caseNumber) {
            1 => [$this->item('A', 1)],
            2 => [$this->item('B', 1)],
            3, 5, 6, 8, 9 => [$this->item('A', 2), $this->item('B', 3)],
            4 => [$this->item('A', 2.5), $this->item('B', 3.25)],
            7 => [$this->item('B', 3, 1000)],
            10 => [$this->item('A', 2), $this->item('B', 3, 1000)],
            default => throw new \InvalidArgumentException('Le numéro de test DGI doit être compris entre 1 et 10.'),
        };

        $total = (int) collect($items)->sum(static fn (array $item): float => $item['price'] * $item['quantity']);

        $payload = [
            'ifu' => $settings->emecfDefaultIfu,
            'type' => 'FV',
            'operator' => [
                'id' => $operator->uuid,
                'name' => $operator->name,
            ],
            'items' => $items,
            'payment' => [
                ['name' => 'ESPECES', 'amount' => $total],
            ],
        ];

        if (in_array($caseNumber, [8, 10], true)) {
            $payload['aib'] = 'B';
        } elseif ($caseNumber === 9) {
            $payload['aib'] = 'A';
        }

        if (in_array($caseNumber, [5, 7, 9], true)) {
            $payload['client'] = [
                'ifu' => $clientIfu,
                'name' => 'EDEN AHOUSSOU',
            ];
        }

        return $payload;
    }

    /** @return array{code: string, name: string, price: int, quantity: float, taxGroup: string, taxSpecific?: int} */
    private function item(string $taxGroup, float $quantity, ?int $taxSpecific = null): array
    {
        [$code, $name] = match (true) {
            $taxGroup === 'A' && fmod($quantity, 1.0) !== 0.0 => ['COLIS-STD', 'Envoi de colis Cotonou → Parakou (tarif au kg)'],
            $taxGroup === 'A' => ['BILLET-STD', 'Billet de bus Cotonou → Parakou'],
            fmod($quantity, 1.0) !== 0.0 => ['COLIS-EXP', 'Envoi de colis express Cotonou → Parakou (tarif au kg)'],
            default => ['BILLET-VIP', 'Billet de bus Cotonou → Parakou, classe VIP'],
        };

        return array_filter([
            'code' => $code,
            'name' => $name,
            'price' => 10000,
            'quantity' => $quantity,
            'taxGroup' => $taxGroup,
            'taxSpecific' => $taxSpecific,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

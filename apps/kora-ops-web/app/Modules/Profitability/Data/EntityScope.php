<?php

namespace App\Modules\Profitability\Data;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\IdentityAccess\Models\Agency;

/**
 * Périmètre de rentabilité restreint à une entité : bus, ligne ou agence
 * (canal de vente). Résolu depuis les paramètres entity_type/entity.
 */
final class EntityScope
{
    private function __construct(
        public readonly ProfitabilityAxis $type,
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $label,
    ) {}

    /** @return list<ProfitabilityAxis> */
    public static function types(): array
    {
        return [ProfitabilityAxis::Bus, ProfitabilityAxis::Line, ProfitabilityAxis::Agency];
    }

    public static function resolve(ProfitabilityAxis $type, string $uuid): ?self
    {
        $model = match ($type) {
            ProfitabilityAxis::Bus => Bus::withTrashed()->where('uuid', $uuid)->first(['id', 'uuid', 'registration_number']),
            ProfitabilityAxis::Line => Line::withTrashed()->where('uuid', $uuid)->first(['id', 'uuid', 'code', 'name']),
            ProfitabilityAxis::Agency => Agency::withTrashed()->where('uuid', $uuid)->first(['id', 'uuid', 'name']),
            default => null,
        };

        if ($model === null) {
            return null;
        }

        $label = match (true) {
            $model instanceof Bus => (string) $model->registration_number,
            $model instanceof Line => (string) $model->code,
            default => (string) $model->name,
        };

        return new self($type, (int) $model->getKey(), $uuid, $label);
    }

    /** @return array{type: string, uuid: string, label: string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'uuid' => $this->uuid,
            'label' => $this->label,
        ];
    }
}

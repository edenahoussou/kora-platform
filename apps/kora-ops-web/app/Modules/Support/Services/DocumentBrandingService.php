<?php

namespace App\Modules\Support\Services;

use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Settings\Settings\IntegrationsSettings;

/**
 * Identité KORA mutualisée des documents PDF (manifeste, relevés…) :
 * en-tête société + logo en data-URI pour le rendu Browsershot hors-ligne.
 */
class DocumentBrandingService
{
    public function __construct(
        private readonly GeneralSettings $generalSettings,
        private readonly IntegrationsSettings $integrationsSettings,
    ) {}

    /** @return array<string, mixed> */
    public function company(): array
    {
        $settings = $this->integrationsSettings;

        return [
            'name' => $settings->emecfShopName ?? $this->generalSettings->displayName,
            'tagline' => 'Transport interurbain de voyageurs',
            'addressLines' => array_values(array_filter([
                $settings->emecfAddress1,
                $settings->emecfAddress2,
                $settings->emecfAddress3,
            ])),
            'contacts' => array_values(array_filter([
                $settings->emecfContact1,
                $settings->emecfContact2,
                $settings->emecfContact3,
                $this->generalSettings->supportCallPhone,
            ])),
            'ifu' => $settings->emecfDefaultIfu,
            'nim' => $settings->emecfNim,
        ];
    }

    public function logoDataUri(): string
    {
        return 'data:image/png;base64,'.base64_encode(
            (string) file_get_contents(public_path('kora-logo-sidebar.png')),
        );
    }
}

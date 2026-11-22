<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Database\QueryException;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

class TransactionalMailFooter
{
    public function text(): string
    {
        $director = config('legal.company_director');
        $company = 'Kora Express';
        $ifu = null;
        $rccm = null;
        $address = null;

        try {
            $settings = app(IntegrationsSettings::class);
            $company = filled($settings->emecfShopName) ? $settings->emecfShopName : $company;
            $ifu = $settings->emecfDefaultIfu === '0000000000000' ? null : $settings->emecfDefaultIfu;
            $rccm = $settings->emecfRccm;
            $address = implode(', ', array_filter([
                $settings->emecfAddress1,
                $settings->emecfAddress2,
                $settings->emecfAddress3,
            ], filled(...)));
        } catch (MissingSettings|QueryException) {
            // Keep transactional mail available while fiscal settings are unavailable.
        }

        return implode(' · ', array_filter([
            '© '.now('Africa/Porto-Novo')->year.' '.$company,
            filled($ifu) ? 'IFU '.$ifu : null,
            filled($rccm) ? 'RCCM '.$rccm : null,
            $address,
            filled($director) ? 'Gérant '.$director : null,
        ], filled(...)));
    }
}

<?php

namespace App\Modules\Settings\Contracts;

use App\Modules\Settings\Data\ConnectionProbeResult;

interface IntegrationConnectionProbe
{
    public function provider(): string;

    public function test(): ConnectionProbeResult;
}

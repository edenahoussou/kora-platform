<?php

namespace App\Modules\Fiscalization\Services;

interface FiscalTestInvoiceGateway
{
    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submit(array $payload): array;

    /** @return array<string, mixed> */
    public function finalize(string $uid): array;
}

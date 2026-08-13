<?php

namespace App\Modules\Cash\Models;

use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Support\Models\KoraModel;

class CashSession extends KoraModel
{
    protected $fillable = [
        'uuid',
        'agent_id',
        'agency_id',
        'business_date',
        'opening_float',
        'total_cash',
        'total_mobile_money',
        'expected_balance',
        'actual_balance',
        'variance',
        'status',
        'closing_comment',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'status' => CashSessionStatus::class,
        ];
    }
}

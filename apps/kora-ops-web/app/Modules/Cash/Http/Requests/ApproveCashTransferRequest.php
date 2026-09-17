<?php

namespace App\Modules\Cash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCashTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash.transfer.approve') ?? false;
    }
}

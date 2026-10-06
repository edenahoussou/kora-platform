<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\BusModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeBusModelStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deactivate', $this->route('model')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(BusModelStatus::class)]];
    }

    public function status(): BusModelStatus
    {
        return BusModelStatus::from($this->string('status')->trim()->value());
    }
}

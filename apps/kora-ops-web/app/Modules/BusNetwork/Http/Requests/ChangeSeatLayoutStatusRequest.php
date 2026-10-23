<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\CompositionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSeatLayoutStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deactivate', $this->route('composition')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(CompositionStatus::class)]];
    }

    public function status(): CompositionStatus
    {
        return CompositionStatus::from($this->string('status')->trim()->value());
    }
}

<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileAvatarUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Veuillez sélectionner une image.',
            'avatar.image' => 'Le fichier doit être une image.',
            'avatar.mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
            'avatar.max' => 'L\'image ne peut pas dépasser 5 Mo.',
        ];
    }
}

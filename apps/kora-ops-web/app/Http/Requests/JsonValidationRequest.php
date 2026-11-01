<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base FormRequest for POST-only Inertia sale routes. On validation
 * failure, redirects to the GET sale index route with errors flashed
 * to session. This avoids the 405 Method Not Allowed that occurs when
 * Laravel redirects back to the POST-only route and Inertia follows
 * the redirect with a GET.
 */
abstract class JsonValidationRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()
                ->route($this->validationRoute())
                ->withInput($this->except($this->dontFlash))
                ->withErrors($validator->errors(), $this->errorBag)
        );
    }

    protected function validationRoute(): string
    {
        if ($this->routeIs('kora-point.*') || ($this->user()?->isKoraPointUser() ?? false)) {
            return 'kora-point.sale';
        }

        return 'sale';
    }
}

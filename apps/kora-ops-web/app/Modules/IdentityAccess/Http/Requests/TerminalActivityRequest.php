<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\TerminalActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TerminalActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Seuls scan/print/error passent par ce endpoint générique : handshake
     * et sync ont chacun leur propre endpoint dédié qui, en plus de
     * journaliser, met à jour un champ du terminal (last_handshake_at /
     * last_sync_at). Les accepter ici créerait une entrée d'audit
     * trompeuse sans la mise à jour correspondante.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::enum(TerminalActivityType::class)->only([
                TerminalActivityType::Scan,
                TerminalActivityType::Print,
                TerminalActivityType::Error,
            ])],
            'context' => ['nullable', 'array'],
        ];
    }

    public function type(): TerminalActivityType
    {
        return TerminalActivityType::from($this->string('type')->value());
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return (array) $this->input('context', []);
    }
}

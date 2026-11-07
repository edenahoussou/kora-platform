<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPointVisitAction;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKoraPointVisitActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $action = $this->route('action');
        if (! $actor instanceof User || ! $action instanceof KoraPointVisitAction) {
            return false;
        }
        if ($actor->status !== UserStatus::Active) {
            return false;
        }

        $access = app(KoraPointAccess::class);
        if ($access->isPartner($actor)) {
            return $actor->hasRole('kora_point_agent')
                && $actor->can('kora-points.visits.actions.update')
                && $action->assigned_user_id === $actor->getKey()
                && $action->visit()->whereHas('point', fn ($query) => $query->where('agency_id', $actor->agency_id))->exists();
        }

        return $actor->can('kora-points.manage')
            && $action->assigned_user_id === $actor->getKey();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['realization_note' => ['required', 'string', 'min:3', 'max:2000']];
    }
}

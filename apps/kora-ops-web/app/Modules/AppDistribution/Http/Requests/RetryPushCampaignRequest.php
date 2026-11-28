<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Models\AppReleasePushCampaign;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Décision Ops explicite permettant de relancer une campagne FCM incertaine
 * (#342). Une campagne `uncertain` ne peut pas être relancée sans ce
 * enregistrement d'audit.
 */
class RetryPushCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof AppReleasePushCampaign
            && ($this->user()?->can('create', AppRelease::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        $note = $this->string('note')->trim()->value();

        return $note === '' ? null : $note;
    }
}

<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgencyPaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agency')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Agency $agency */
        $agency = $this->route('agency');

        return [
            'network' => ['required', Rule::enum(MobileMoneyNetwork::class)],
            'label' => ['required', 'string', 'max:120'],
            'account_identifier' => [
                'required',
                'string',
                'max:64',
                Rule::unique('agency_payment_accounts', 'account_identifier')->where(
                    fn ($query) => $query
                        ->where('agency_id', $agency->getKey())
                        ->where('network', $this->string('network')->value())
                ),
            ],
            'replace_account_uuid' => [
                'nullable',
                'uuid',
                Rule::exists((new AgencyPaymentAccount)->getTable(), 'uuid')->where(
                    fn ($query) => $query->where('agency_id', $agency->getKey())
                ),
            ],
        ];
    }

    /**
     * @return array{agency: Agency, network: MobileMoneyNetwork, label: string, account_identifier: string, replace_account_uuid: string|null}
     */
    public function payload(): array
    {
        /** @var Agency $agency */
        $agency = $this->route('agency');

        return [
            'agency' => $agency,
            'network' => MobileMoneyNetwork::from($this->string('network')->value()),
            'label' => $this->string('label')->trim()->value(),
            'account_identifier' => $this->string('account_identifier')->trim()->value(),
            'replace_account_uuid' => $this->filled('replace_account_uuid')
                ? $this->string('replace_account_uuid')->trim()->value()
                : null,
        ];
    }
}

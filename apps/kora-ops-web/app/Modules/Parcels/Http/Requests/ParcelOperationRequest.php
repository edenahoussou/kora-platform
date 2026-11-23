<?php

namespace App\Modules\Parcels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParcelOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $routeName = (string) $this->route()?->getName();

        if (str_starts_with($routeName, 'api.v1.parcels.')) {
            return $user->can('parcels.customer');
        }

        return match ($this->route()?->getActionMethod()) {
            'list', 'documents' => $user->can('parcels.view'),
            'assign' => $user->can('parcels.assign'),
            'scan' => $user->can('parcels.scan'),
            'release' => $user->can('parcels.handoff'),
            'correction', 'resolveIncident' => $user->can('parcels.override'),
            'incident' => $user->can('parcels.intake')
                || $user->can('parcels.assign')
                || $user->can('parcels.handoff'),
            'authorizeRefund', 'completeRefund' => $user->can('parcels.finance'),
            'printResult' => $user->can('parcels.intake')
                || $user->can('parcels.handoff'),
            default => false,
        };
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $method = $this->route()?->getActionMethod();

        if ($method === 'incident') {
            $rules = [
                'kind' => ['required', 'in:loss,damage,custody_discrepancy'],
                'description' => ['required', 'string', 'max:2000'],
                'idempotency_key' => ['nullable', 'uuid'],
            ];

            if ($this->isOpsIncidentRoute()) {
                $rules['blocking'] = ['nullable', 'boolean'];
            }

            return $rules;
        }

        return match ($method) {
            'assign' => [
                'departure_uuid' => ['required', 'uuid'],
            ],
            'scan' => [
                'reference' => ['required', 'regex:/^KC-[0-9]{8}-[0-9]{5}$/'],
                'stop_uuid' => ['required', 'uuid'],
                'direction' => ['required', 'in:load,unload'],
                'operation_uuid' => ['required', 'uuid'],
                'observed_at' => ['required', 'date'],
            ],
            'release' => [
                'code' => ['required_without:identity_type', 'string', 'size:6'],
                'identity_type' => ['required_without:code', 'in:national_id,passport,driving_licence'],
                'reason' => ['required_with:identity_type', 'string', 'max:500'],
            ],
            'correction', 'resolveIncident' => [
                'reason' => ['required', 'string', 'max:500'],
            ],
            'authorizeRefund' => [
                'payment_uuid' => ['required', 'uuid'],
                'idempotency_key' => ['required', 'uuid'],
                'amount' => ['required', 'integer', 'min:1'],
                'reason' => ['required', 'string', 'max:500'],
            ],
            'completeRefund' => [
                'proof' => ['required', 'string', 'max:255'],
                'cash_ledger_uuid' => ['nullable', 'uuid'],
            ],
            'printResult' => [
                'print_job_uuid' => ['required', 'uuid'],
                'outcome' => ['required', 'in:printed,failed'],
            ],
            'documents' => [
                'kind' => ['nullable', 'in:label,receipt,return_receipt,handoff_proof'],
            ],
            'list' => [
                'status' => ['nullable', 'string', 'max:32'],
                'reference' => ['nullable', 'string', 'max:17'],
                'corridor_uuid' => ['nullable', 'uuid'],
                'page' => ['nullable', 'integer', 'min:1'],
            ],
            default => [],
        };
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'kind' => ['description' => 'Nature du signalement : loss, damage ou custody_discrepancy.'],
            'description' => ['description' => 'Description factuelle de l’incident, sans code de retrait.'],
            'idempotency_key' => ['description' => 'Clé d’idempotence pour rejouer la déclaration sans créer un second incident.'],
            'blocking' => ['description' => 'Indique si l’incident bloque la suite du parcours.'],
        ];
    }

    private function isOpsIncidentRoute(): bool
    {
        $name = (string) $this->route()?->getName();

        return str_contains($name, '.ops.') || str_starts_with($name, 'parcels.data.');
    }
}

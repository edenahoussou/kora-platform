<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\CompanyAccounts\Actions\ChangeCompanyTravelerStatusAction;
use App\Modules\CompanyAccounts\Actions\StoreCompanyTravelerAction;
use App\Modules\CompanyAccounts\Actions\UpdateCompanyTravelerAction;
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Http\Requests\ChangeCompanyTravelerStatusRequest;
use App\Modules\CompanyAccounts\Http\Requests\StoreCompanyTravelerRequest;
use App\Modules\CompanyAccounts\Http\Requests\UpdateCompanyTravelerRequest;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPortalTravelerController extends Controller
{
    public function __construct(
        private readonly StoreCompanyTravelerAction $storeTraveler,
        private readonly UpdateCompanyTravelerAction $updateTraveler,
        private readonly ChangeCompanyTravelerStatusAction $changeTravelerStatus,
    ) {}

    public function index(Request $request): Response
    {
        $account = $this->account($request);

        $travelers = $account->travelers()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $travelers->setCollection($travelers->getCollection()->map(
            fn (CompanyTraveler $traveler): array => $this->serialize($traveler),
        )->values());

        return Inertia::render('company-portal/travelers', [
            'account' => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'reference' => $account->reference,
            ],
            'travelers' => $travelers,
            'filters' => ['q' => $request->string('q')->toString()],
            'documentTypes' => collect(PassengerDocumentType::cases())->mapWithKeys(
                fn (PassengerDocumentType $type): array => [$type->value => __("enums.passenger_document_type.{$type->value}")],
            )->all(),
        ]);
    }

    public function store(StoreCompanyTravelerRequest $request): RedirectResponse
    {
        $traveler = $this->storeTraveler->execute($this->account($request), $request->payload());
        activity()->performedOn($traveler)->causedBy($request->user())->log('company_traveler_created');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voyageur ajouté au roster.']);

        return to_route('company-portal.travelers.index');
    }

    public function update(UpdateCompanyTravelerRequest $request, CompanyTraveler $traveler): RedirectResponse
    {
        $this->ensureOwnership($request, $traveler);
        $traveler = $this->updateTraveler->execute($traveler, $request->payload());
        activity()->performedOn($traveler)->causedBy($request->user())->log('company_traveler_updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voyageur mis à jour.']);

        return to_route('company-portal.travelers.index');
    }

    public function changeStatus(ChangeCompanyTravelerStatusRequest $request, CompanyTraveler $traveler): RedirectResponse
    {
        $this->ensureOwnership($request, $traveler);
        $status = CompanyTravelerStatus::from($request->string('status')->toString());
        $traveler = $this->changeTravelerStatus->execute($traveler, $status);
        activity()->performedOn($traveler)->causedBy($request->user())->log('company_traveler_status_changed');
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $status === CompanyTravelerStatus::Active ? 'Voyageur réactivé.' : 'Voyageur retiré de la sélection.',
        ]);

        return to_route('company-portal.travelers.index');
    }

    /**
     * Voyageurs actifs éligibles à la vente — endpoint réutilisé par MEZ-115.
     *
     * @return array{travelers: list<array<string, mixed>>}
     */
    public function eligible(Request $request): array
    {
        $account = $this->account($request);

        return [
            'travelers' => $account->travelers()
                ->where('status', CompanyTravelerStatus::Active->value)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (CompanyTraveler $traveler): array => $this->serialize($traveler))
                ->values()
                ->all(),
        ];
    }

    private function ensureOwnership(Request $request, CompanyTraveler $traveler): void
    {
        abort_unless($traveler->company_account_id === $this->account($request)->getKey(), 404);
    }

    /** @return array<string, mixed> */
    private function serialize(CompanyTraveler $traveler): array
    {
        return [
            'uuid' => $traveler->uuid,
            'firstName' => $traveler->first_name,
            'lastName' => $traveler->last_name,
            'phone' => $traveler->phone,
            'email' => $traveler->email,
            'documentType' => $traveler->document_type?->value,
            'documentTypeLabel' => $traveler->document_type !== null
                ? __("enums.passenger_document_type.{$traveler->document_type->value}")
                : null,
            'documentNumber' => $traveler->document_number,
            'nationality' => $traveler->nationality,
            'birthDate' => $traveler->birth_date?->toDateString(),
            'yellowFeverVaccinated' => $traveler->yellow_fever_vaccinated,
            'status' => $traveler->status->value,
        ];
    }

    private function account(Request $request): CompanyAccount
    {
        /** @var CompanyAccount $account */
        $account = $request->attributes->get('companyAccount');

        return $account;
    }
}

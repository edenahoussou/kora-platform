<?php

namespace App\Modules\FleetLite\Actions;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Data\ExpensePaymentChannel;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Models\ExpenseTripDetails;
use App\Modules\FleetLite\Data\TripExpenseKind;
use App\Modules\FleetLite\Exceptions\TripLogException;
use App\Modules\FleetLite\Services\TripExpenseMailer;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carnet de bord OPS Mobile — enregistre une dépense de voyage (carburant,
 * péage, taxe frontière) saisie par l'équipage. La dépense entre dans le
 * circuit standard du module Dépenses, statut « en attente » : elle n'est
 * comptabilisée (rentabilité) qu'après approbation au bureau. Un e-mail
 * détaillé part aux opérations du bureau, administrateurs en copie (voir
 * TripExpenseMailer).
 *
 * Idempotent par `client_uuid` (file hors ligne du mobile) : rejouer la
 * même saisie renvoie la dépense déjà créée, sans doublon ni nouvel e-mail.
 */
class SubmitTripExpenseAction
{
    public function __construct(private TripExpenseMailer $mailer) {}

    /**
     * @param  array{
     *     client_uuid: string,
     *     kind: TripExpenseKind,
     *     amount: int|null,
     *     liters: float|null,
     *     unit_price: int|null,
     *     station_label: string|null,
     *     checkpoint_label: string|null,
     *     country_code: string|null,
     *     odometer_km: int|null,
     *     payment_channel: ExpensePaymentChannel,
     *     occurred_at: Carbon|null,
     *     note: string|null,
     *     photo: UploadedFile|null,
     * }  $data
     * @return array{0: Expense, 1: bool} Dépense et « créée ».
     *
     * @throws TripLogException
     */
    public function execute(User $user, Departure $departure, ?TerminalP501 $terminal, array $data): array
    {
        $existing = ExpenseTripDetails::query()->with('expense')->where('client_uuid', $data['client_uuid'])->first();
        if ($existing !== null) {
            if ($existing->submitted_by !== $user->getKey() || $existing->expense?->departure_id !== $departure->getKey()) {
                throw TripLogException::uuidConflict();
            }

            return [$existing->expense, false];
        }

        $kind = $data['kind'];
        $amount = $data['amount'];
        if ($kind === TripExpenseKind::Fuel && $amount === null) {
            $amount = (int) round(((float) $data['liters']) * ((int) $data['unit_price']));
        }

        $expense = DB::transaction(function () use ($user, $departure, $terminal, $data, $kind, $amount): Expense {
            $category = ExpenseCategory::query()->firstOrCreate(
                ['code' => $kind->categoryCode()],
                ['uuid' => (string) Str::uuid(), 'label' => $kind->categoryLabel(), 'is_active' => true],
            );

            $departure->loadMissing(['line', 'bus']);

            $expense = Expense::query()->create([
                'agency_id' => $this->resolveAgencyId($user, $departure, $terminal),
                'bus_id' => $departure->bus_id,
                'line_id' => $departure->line_id,
                'departure_id' => $departure->getKey(),
                'expense_category_id' => $category->getKey(),
                'status' => ExpenseStatus::Pending,
                'payment_channel' => $data['payment_channel'],
                'amount' => (int) $amount,
                'currency' => 'XOF',
                'payee_label' => $this->payeeLabel($kind, $data),
                'payee_reference' => null,
                'description' => $this->description($kind, $data, $departure, $user),
                'occurred_at' => $data['occurred_at'] ?? now(),
                'created_by' => $user->getKey(),
            ]);

            $expense->tripDetails()->create([
                'kind' => $kind,
                'liters' => $kind === TripExpenseKind::Fuel ? $data['liters'] : null,
                'unit_price' => $kind === TripExpenseKind::Fuel ? $data['unit_price'] : null,
                'station_label' => $kind === TripExpenseKind::Fuel ? $data['station_label'] : null,
                'checkpoint_label' => $kind === TripExpenseKind::Fuel ? null : $data['checkpoint_label'],
                'country_code' => $data['country_code'] !== null ? Str::upper($data['country_code']) : null,
                'odometer_km' => $data['odometer_km'],
                'source' => 'ops_mobile',
                'client_uuid' => $data['client_uuid'],
                'submitted_by' => $user->getKey(),
                'terminal_p501_id' => $terminal?->getKey(),
            ]);

            if ($data['photo'] instanceof UploadedFile) {
                $expense->addMedia($data['photo'])
                    ->usingName(sprintf('Justificatif %s %s', Str::lower($kind->label()), $expense->reference))
                    ->toMediaCollection('evidence');
            }

            activity('expenses')
                ->performedOn($expense)
                ->causedBy($user)
                ->withProperties([
                    'reference' => $expense->reference,
                    'source' => 'ops_mobile',
                    'kind' => $kind->value,
                    'departure' => $departure->uuid,
                    'terminal' => $terminal?->serial_number,
                ])
                ->log('expense_created');

            return $expense;
        }, attempts: 3);

        $this->mailer->submitted($expense);

        return [$expense, true];
    }

    /**
     * Agence support de la dépense (obligatoire) : celle du déclarant, sinon
     * celle du terminal, sinon celle d'un autre membre de l'équipage, sinon
     * celle du créateur du départ.
     *
     * @throws TripLogException
     */
    private function resolveAgencyId(User $user, Departure $departure, ?TerminalP501 $terminal): int
    {
        $candidates = [$user->agency_id, $terminal?->agency_id];

        $crewIds = array_values(array_filter([
            $departure->conductor_id,
            $departure->driver_id,
            $departure->secondary_driver_id,
            $departure->mechanic_id,
            $departure->created_by,
        ]));

        if ($crewIds !== []) {
            $candidates[] = User::query()
                ->whereIn('id', $crewIds)
                ->whereNotNull('agency_id')
                ->orderByRaw('case when id = ? then 0 else 1 end', [$departure->conductor_id ?? 0])
                ->value('agency_id');
        }

        foreach ($candidates as $agencyId) {
            if ($agencyId !== null && Agency::query()->whereKey($agencyId)->exists()) {
                return (int) $agencyId;
            }
        }

        throw TripLogException::agencyUnresolved();
    }

    /** @param array<string, mixed> $data */
    private function payeeLabel(TripExpenseKind $kind, array $data): string
    {
        $label = $kind === TripExpenseKind::Fuel ? $data['station_label'] : $data['checkpoint_label'];

        return Str::limit(trim((string) $label) !== '' ? (string) $label : $kind->label(), 160, '');
    }

    /** @param array<string, mixed> $data */
    private function description(TripExpenseKind $kind, array $data, Departure $departure, User $user): string
    {
        $parts = [sprintf('%s — saisi depuis OPS Mobile par %s', $kind->label(), $user->name)];
        $parts[] = sprintf(
            'Départ %s du %s',
            $departure->line?->code ?? '—',
            $departure->scheduledDepartureAt()->setTimezone('Africa/Porto-Novo')->format('d/m/Y H:i'),
        );

        if ($kind === TripExpenseKind::Fuel) {
            $parts[] = sprintf(
                '%s L × %s XOF/L — station %s',
                number_format((float) $data['liters'], 2, ',', ' '),
                number_format((int) $data['unit_price'], 0, ',', ' '),
                $data['station_label'],
            );
        } else {
            $parts[] = trim(sprintf('Poste %s %s', $data['checkpoint_label'], $data['country_code'] !== null ? '('.Str::upper($data['country_code']).')' : ''));
        }

        if ($data['odometer_km'] !== null) {
            $parts[] = sprintf('Compteur %s km', number_format((int) $data['odometer_km'], 0, ',', ' '));
        }

        if (is_string($data['note']) && trim($data['note']) !== '') {
            $parts[] = 'Note : '.trim($data['note']);
        }

        return Str::limit(implode(' · ', $parts), 1000, '…');
    }
}

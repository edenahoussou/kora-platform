<?php

namespace App\Modules\FleetLite\Services;

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Modules\FleetLite\Mail\TripExpenseSubmittedMail;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * E-mail « dépense de voyage à valider » envoyé aux opérations du bureau,
 * administrateurs en copie (voir config/ops_trip_logs.php). Jamais
 * bloquant : un échec d'envoi est journalisé, la saisie terrain reste
 * enregistrée.
 */
class TripExpenseMailer
{
    public function submitted(Expense $expense): void
    {
        if (! (bool) config('ops_trip_logs.mail_enabled', true)) {
            return;
        }

        try {
            ['to' => $to, 'cc' => $cc] = $this->recipients($expense);

            if ($to === []) {
                Log::warning('Dépense de voyage sans destinataire e-mail', ['expense' => $expense->uuid]);

                return;
            }

            Mail::to($to)->cc($cc)->queue(new TripExpenseSubmittedMail($expense->getKey()));
        } catch (Throwable $exception) {
            Log::error('Envoi e-mail dépense de voyage impossible', [
                'expense' => $expense->uuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{to: list<string>, cc: list<string>}
     */
    public function recipients(Expense $expense): array
    {
        $directors = $this->activeEmails(
            User::query()
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', SystemRole::OperationsDirector->value))
                ->where('agency_id', $expense->agency_id),
        );
        $admins = $this->activeEmails(
            User::query()->whereHas('roles', fn (Builder $roles) => $roles->where('name', SystemRole::SystemAdmin->value)),
        );

        /** @var list<string> $extraTo */
        $extraTo = (array) config('ops_trip_logs.mail_to', []);
        /** @var list<string> $extraCc */
        $extraCc = (array) config('ops_trip_logs.mail_cc', []);

        $to = $this->normalize([...$directors, ...$extraTo]);
        $cc = $this->normalize([...$admins, ...$extraCc]);

        if ($to === []) {
            return ['to' => $cc, 'cc' => []];
        }

        return ['to' => $to, 'cc' => array_values(array_diff($cc, $to))];
    }

    /**
     * @param  Builder<User>  $query
     * @return list<string>
     */
    private function activeEmails(Builder $query): array
    {
        return $query
            ->where('status', UserStatus::Active->value)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(fn (mixed $email): string => (string) $email)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $emails
     * @return list<string>
     */
    private function normalize(array $emails): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (string $email): string => mb_strtolower(trim($email)), $emails),
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        )));
    }
}

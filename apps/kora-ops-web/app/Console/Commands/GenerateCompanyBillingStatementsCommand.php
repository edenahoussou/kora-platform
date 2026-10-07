<?php

namespace App\Console\Commands;

use App\Modules\CompanyAccounts\Actions\GenerateCompanyBillingStatementsAction;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use Illuminate\Console\Command;

class GenerateCompanyBillingStatementsCommand extends Command
{
    protected $signature = 'companies:billing-statements {--period= : Période au format Y-m (défaut : mois précédent)} {--account= : UUID du compte à traiter}';

    protected $description = 'Génère les relevés de facturation mensuels consolidés des comptes organisationnels actifs';

    public function handle(GenerateCompanyBillingStatementsAction $action): int
    {
        $period = $this->option('period');
        if ($period !== null && preg_match('/^\d{4}-\d{2}$/', (string) $period) !== 1) {
            $this->error('La période doit être au format Y-m (ex. 2026-09).');

            return self::FAILURE;
        }

        $account = null;
        $accountUuid = $this->option('account');
        if ($accountUuid !== null) {
            $account = CompanyAccount::query()->where('uuid', $accountUuid)->first();
            if ($account === null) {
                $this->error('Compte organisationnel introuvable.');

                return self::FAILURE;
            }
        }

        $result = $action->handle($period, $account);

        $this->info(sprintf(
            '%d relevé(s) généré(s) pour la période %s — %d email(s) envoyé(s).',
            $result['statements'],
            $period ?? now()->subMonthNoOverflow()->format('Y-m'),
            $result['emailed'],
        ));

        return self::SUCCESS;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cash_sessions')->orderBy('id')->each(function (stdClass $session): void {
            $ledgerId = DB::table('cash_ledgers')->where([
                'cash_session_id' => $session->id,
                'ledger_key' => 'cash',
            ])->value('id');

            if ($ledgerId === null) {
                $ledgerId = DB::table('cash_ledgers')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'cash_session_id' => $session->id,
                    'ledger_key' => 'cash',
                    'kind' => 'cash',
                    'agency_payment_account_id' => null,
                    'opening_balance' => $session->opening_float,
                    'expected_balance' => $session->expected_balance,
                    'actual_balance' => $session->actual_balance,
                    'variance' => $session->variance,
                    'status' => $this->ledgerStatus($session->status),
                    'opened_by' => $session->agent_id,
                    'opened_at' => $session->created_at,
                    'closed_at' => $session->closed_at,
                    'opening_comment' => 'Sous-journal créé lors de la migration de la caisse historique.',
                    'closing_comment' => $session->closing_comment,
                    'created_at' => $session->created_at,
                    'updated_at' => $session->updated_at,
                ]);
            }

            DB::table('cash_movements')
                ->where('cash_session_id', $session->id)
                ->whereNull('cash_ledger_id')
                ->where(function ($query): void {
                    $query->whereNull('source_type')->orWhere('source_type', '!=', 'App\\Modules\\Payments\\Models\\Payment');
                })
                ->update(['cash_ledger_id' => $ledgerId]);

            DB::table('payments')
                ->where('cash_session_id', $session->id)
                ->orderBy('id')
                ->each(function (stdClass $payment) use ($session, $ledgerId): void {
                    $paymentLedgerId = $ledgerId;

                    if ($payment->agency_payment_account_id !== null) {
                        $account = DB::table('agency_payment_accounts')->where('id', $payment->agency_payment_account_id)->first();
                        if ($account !== null && (int) $account->agency_id === (int) $session->agency_id) {
                            $paymentLedgerId = $this->mobileLedger($session, $account);
                        }
                    }

                    DB::table('payments')->where('id', $payment->id)->update(['cash_ledger_id' => $paymentLedgerId]);
                    DB::table('cash_movements')
                        ->where('source_type', 'App\\Modules\\Payments\\Models\\Payment')
                        ->where('source_id', $payment->id)
                        ->whereNull('cash_ledger_id')
                        ->update(['cash_ledger_id' => $paymentLedgerId]);
                });
        });
    }

    public function down(): void
    {
        DB::table('cash_movements')->update(['cash_ledger_id' => null]);
        DB::table('payments')->update(['cash_ledger_id' => null]);
        DB::table('cash_ledgers')->whereIn('opening_comment', [
            'Sous-journal créé lors de la migration de la caisse historique.',
            'Solde historique non reconstitué lors de la migration.',
        ])->delete();
    }

    private function mobileLedger(stdClass $session, stdClass $account): int
    {
        $existing = DB::table('cash_ledgers')->where([
            'cash_session_id' => $session->id,
            'ledger_key' => $account->uuid,
        ])->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('cash_ledgers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'cash_session_id' => $session->id,
            'ledger_key' => $account->uuid,
            'kind' => 'mobile_money',
            'agency_payment_account_id' => $account->id,
            'opening_balance' => 0,
            'expected_balance' => 0,
            'status' => $this->ledgerStatus($session->status),
            'opened_by' => $session->agent_id,
            'opened_at' => $session->created_at,
            'closed_at' => $session->closed_at,
            'opening_comment' => 'Solde historique non reconstitué lors de la migration.',
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
        ]);
    }

    private function ledgerStatus(string $sessionStatus): string
    {
        return match ($sessionStatus) {
            'closed' => 'closed',
            'pending_approval' => 'pending_approval',
            default => 'open',
        };
    }
};

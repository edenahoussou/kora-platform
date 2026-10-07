<?php

namespace App\Modules\CompanyAccounts\Mail;

use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Relevé de facturation mensuel consolidé envoyé au référent du compte
 * organisationnel. Ne contient que des identifiants publics.
 */
class CompanyBillingStatementMail extends Mailable
{
    public function __construct(
        public readonly CompanyAccount $account,
        public readonly CompanyBillingStatement $statement,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: sprintf('[KORA] Relevé de facturation %s — %s', $this->periodLabel(), $this->account->name));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.company-billing-statement',
            with: [
                'accountName' => $this->account->name,
                'accountReference' => $this->account->reference,
                'statementReference' => $this->statement->reference,
                'periodLabel' => $this->periodLabel(),
                'reservationsCount' => $this->statement->reservations_count,
                'invoicesCount' => $this->statement->invoices_count,
                'debitTotal' => number_format($this->statement->debit_total, 0, ',', ' '),
                'refundTotal' => number_format($this->statement->refund_total, 0, ',', ' '),
                'netTotal' => number_format($this->statement->net_total, 0, ',', ' '),
                'currency' => $this->statement->currency,
                'portalUrl' => route('company-portal.statements.index'),
            ],
        );
    }

    private function periodLabel(): string
    {
        return (new CarbonImmutable($this->statement->period_key.'-01'))
            ->settings(['locale' => 'fr'])
            ->isoFormat('MMMM YYYY');
    }
}

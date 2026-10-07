<?php

namespace App\Notifications\CompanyAccounts;

use App\Modules\CompanyAccounts\Actions\GenerateCompanyStatementPdfAction;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use App\Modules\Fiscalization\Actions\GenerateFiscalInvoicePdfAction;
use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Relevé de facturation mensuel envoyé au référent du compte, avec le PDF
 * brandé KORA et les factures de vente certifiées de la période en pièces
 * jointes. Canal `mail` aujourd'hui — `via()` est extensible.
 */
class MonthlyBillingStatementNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public readonly CompanyAccount $account,
        public readonly CompanyBillingStatement $statement,
        public readonly ?string $recipientName = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $periodLabel = $this->periodLabel();
        $currency = $this->statement->currency;
        $money = static fn (int $amount): string => number_format($amount, 0, ',', ' ').' '.$currency;

        $message = (new MailMessage)
            ->subject(sprintf('[KORA] Relevé de facturation %s — %s', $periodLabel, $this->account->name))
            ->greeting(sprintf('Bonjour %s,', $this->recipientName ?? $this->account->name))
            ->line(sprintf(
                'Veuillez recevoir ci-joint le relevé de facturation consolidé de %s (réf. %s) pour la période de %s, accompagné des factures de vente normalisées (MECeF) de la période.',
                $this->account->name,
                $this->account->reference,
                $periodLabel,
            ))
            ->line(sprintf(
                'Référence : %s · Réservations : %d · Factures normalisées : %d',
                $this->statement->reference,
                $this->statement->reservations_count,
                $this->statement->invoices_count,
            ))
            ->line(sprintf(
                'Total débits : %s · Avoirs : %s · Net à régler : %s',
                $money((int) $this->statement->debit_total),
                $money((int) $this->statement->refund_total),
                $money((int) $this->statement->net_total),
            ))
            ->action('Consulter mes relevés', route('company-portal.statements.index'));

        foreach ($this->documents() as $document) {
            $message->attach(
                Attachment::fromData(static fn (): string => $document['content'], $document['filename'])
                    ->withMime('application/pdf'),
            );
        }

        return $message;
    }

    /** @return list<array{content: string, filename: string}> */
    private function documents(): array
    {
        $documents = [];

        try {
            $documents[] = app(GenerateCompanyStatementPdfAction::class)->execute($this->statement);
        } catch (Throwable $exception) {
            report($exception);
        }

        foreach ($this->certifiedSaleInvoices() as $invoice) {
            try {
                $documents[] = app(GenerateFiscalInvoicePdfAction::class)->execute($invoice);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $documents;
    }

    /** @return Collection<int, FiscalInvoice> */
    private function certifiedSaleInvoices(): Collection
    {
        $uuids = collect($this->statement->lines ?? [])
            ->pluck('invoice.uuid')
            ->filter()
            ->values()
            ->all();

        if ($uuids === []) {
            return collect();
        }

        return FiscalInvoice::query()
            ->whereIn('uuid', $uuids)
            ->where('status', FiscalInvoiceStatus::Certified->value)
            ->where('document_kind', FiscalInvoiceDocumentKind::Sale->value)
            ->get();
    }

    private function periodLabel(): string
    {
        return (new CarbonImmutable($this->statement->period_key.'-01'))
            ->settings(['locale' => 'fr'])
            ->isoFormat('MMMM YYYY');
    }
}

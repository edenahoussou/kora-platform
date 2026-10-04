<?php

namespace App\Modules\CompanyAccounts\Actions;

use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Http\Requests\StoreCompanyTravelerRequest;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importe le roster depuis un fichier xlsx/csv. La première ligne porte les
 * en-têtes ; chaque ligne valide crée ou met à jour le voyageur identifié par
 * le téléphone normalisé (unicité scopée au compte).
 */
class ImportCompanyTravelersAction
{
    /** @var list<string> */
    public const TEMPLATE_HEADERS = [
        'prenom',
        'nom',
        'telephone',
        'email',
        'type_piece',
        'numero_piece',
        'nationalite',
        'date_naissance',
        'vaccination_fievre_jaune',
    ];

    /** @var array<string, string> */
    private const COLUMN_ALIASES = [
        'prenom' => 'first_name',
        'first_name' => 'first_name',
        'nom' => 'last_name',
        'last_name' => 'last_name',
        'telephone' => 'phone',
        'tel' => 'phone',
        'phone' => 'phone',
        'email' => 'email',
        'e_mail' => 'email',
        'type_piece' => 'document_type',
        'document_type' => 'document_type',
        'numero_piece' => 'document_number',
        'document_number' => 'document_number',
        'nationalite' => 'nationality',
        'nationality' => 'nationality',
        'date_naissance' => 'birth_date',
        'naissance' => 'birth_date',
        'vaccination_fievre_jaune' => 'yellow_fever_vaccinated',
        'fievre_jaune' => 'yellow_fever_vaccinated',
        'yellow_fever_vaccinated' => 'yellow_fever_vaccinated',
    ];

    public function __construct(
        private readonly PassengerIdentityResolver $identityResolver,
    ) {}

    /**
     * @return array{imported: int, updated: int, errors: list<array{row: int, message: string}>}
     */
    public function execute(CompanyAccount $account, UploadedFile $file): array
    {
        $rows = $this->extractRows($file);
        $report = ['imported' => 0, 'updated' => 0, 'errors' => []];
        $seenPhones = [];

        foreach ($rows as $line => $row) {
            $row['phone'] = $this->identityResolver->normalizePhone($row['phone'] ?? null);
            $row['nationality'] = isset($row['nationality']) && $row['nationality'] !== ''
                ? Str::upper((string) $row['nationality'])
                : null;
            if (isset($row['document_type']) && $row['document_type'] !== '') {
                $row['document_type'] = $this->resolveDocumentType($row['document_type']);
            }

            $validator = Validator::make($row, StoreCompanyTravelerRequest::baseRules());

            if ($validator->fails()) {
                $report['errors'][] = [
                    'row' => $line,
                    'message' => implode(' ', $validator->errors()->all()),
                ];

                continue;
            }

            $data = $validator->validated();
            $existing = $seenPhones[$data['phone']]
                ?? $account->travelers()->where('phone', $data['phone'])->first();

            if ($existing instanceof CompanyTraveler) {
                $existing->update($data);
                $report['updated']++;
            } else {
                $existing = $account->travelers()->create($data + ['status' => CompanyTravelerStatus::Active]);
                $report['imported']++;
            }

            $seenPhones[$data['phone']] = $existing;
        }

        return $report;
    }

    /** @return array<int, array<string, mixed>> clé = numéro de ligne fichier. */
    private function extractRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        $raw = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        $headers = array_shift($raw);
        if (! is_array($headers)) {
            return [];
        }

        $columns = [];
        foreach ($headers as $index => $header) {
            $key = self::COLUMN_ALIASES[$this->normalizeHeader($header)] ?? null;
            if ($key !== null) {
                $columns[$index] = $key;
            }
        }

        if (! in_array('phone', $columns, true)) {
            throw ValidationException::withMessages([
                'file' => 'La colonne « telephone » est introuvable dans le fichier.',
            ]);
        }

        $rows = [];
        foreach ($raw as $offset => $cells) {
            $row = [];
            foreach ($columns as $index => $key) {
                $value = $cells[$index] ?? null;
                if ($key === 'birth_date' && is_numeric($value)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }
                if ($key === 'yellow_fever_vaccinated') {
                    $value = $this->normalizeBoolean($value);
                }
                $row[$key] = is_scalar($value) ? trim((string) $value) : $value;
            }

            if (implode('', array_map(fn ($v) => (string) ($v ?? ''), $row)) === '') {
                continue;
            }

            $rows[$offset + 2] = $row;
        }

        return $rows;
    }

    /** Accepte la valeur enum (passport) ou le libellé français (Passeport). */
    private function resolveDocumentType(string $value): string
    {
        $value = trim($value);

        if (PassengerDocumentType::tryFrom($value) !== null) {
            return $value;
        }

        $normalized = Str::lower(Str::ascii($value));

        foreach (PassengerDocumentType::cases() as $case) {
            $label = __('enums.passenger_document_type.'.$case->value);

            if (Str::lower(Str::ascii($label)) === $normalized) {
                return $case->value;
            }
        }

        return $value;
    }

    private function normalizeHeader(mixed $header): string
    {
        $ascii = Str::ascii(mb_strtolower(trim((string) $header)));

        return preg_replace('/[^a-z0-9]+/', '_', $ascii) ?? '';
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = Str::ascii(mb_strtolower(trim((string) $value)));

        return in_array($normalized, ['oui', 'yes', '1', 'true', 'vrai', 'x'], true);
    }
}

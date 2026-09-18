<?php

namespace App\Modules\Expenses\Services;

use App\Models\User;
use App\Modules\Expenses\Data\ExpensePaymentChannel;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;

class ExpenseQuery
{
    /**
     * @param  array{q: string, status: string, channel: string, category: string, agency: string, from: string, to: string}  $filters
     * @return Builder<Expense>
     */
    public function build(User $user, array $filters): Builder
    {
        $query = Expense::query()->with(['category', 'agency', 'creator', 'cashLedger.agencyPaymentAccount']);
        if ($user->can('expenses.view-network')) {
            $query->when($filters['agency'] !== '', fn (Builder $query) => $query->whereHas('agency', fn (Builder $agency) => $agency->where('uuid', $filters['agency'])));
        } elseif ($user->agency_id !== null && $user->can('expenses.view-agency')) {
            $query->where('agency_id', $user->agency_id);
        } else {
            $query->where('created_by', $user->getKey());
        }

        return $query
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.mb_strtolower((string) $filters['q']).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('LOWER(reference) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(payee_label) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(payee_reference) LIKE ?', [$term])
                        ->orWhereHas('category', fn (Builder $category) => $category->whereRaw('LOWER(label) LIKE ?', [$term]));
                });
            })
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['channel'] !== 'all', fn (Builder $query) => $query->where('payment_channel', $filters['channel']))
            ->when($filters['category'] !== '', fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('uuid', $filters['category'])))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('occurred_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('occurred_at', '<=', $filters['to']))
            ->latest('occurred_at');
    }

    /**
     * @param  array{q: string, status: string, channel: string, category: string, agency: string, from: string, to: string}  $filters
     * @return array<string, int>
     */
    public function counters(User $user, array $filters): array
    {
        $counts = $this->build($user, $filters)->toBase()
            ->selectRaw("count(*) as total, coalesce(sum(case when status = 'approved' then amount else 0 end), 0) as approved_amount, count(case when status = 'pending' then 1 end) as pending, count(case when status = 'approved' then 1 end) as approved, count(case when status = 'rejected' then 1 end) as rejected, count(case when status = 'reversed' then 1 end) as reversed")
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'approvedAmount' => (int) ($counts->approved_amount ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'approved' => (int) ($counts->approved ?? 0),
            'rejected' => (int) ($counts->rejected ?? 0),
            'reversed' => (int) ($counts->reversed ?? 0),
        ];
    }

    /** @return array<string, string> */
    public function statuses(): array
    {
        return collect(ExpenseStatus::cases())->mapWithKeys(fn (ExpenseStatus $status): array => [$status->value => $status->label()])->all();
    }

    /** @return array<string, string> */
    public function channels(): array
    {
        return collect(ExpensePaymentChannel::cases())->mapWithKeys(fn (ExpensePaymentChannel $channel): array => [$channel->value => $channel->label()])->all();
    }

    /** @return array<int, array{uuid: string, label: string}> */
    public function categories(): array
    {
        return ExpenseCategory::query()->where('is_active', true)->orderBy('label')->get(['uuid', 'label'])->map(fn (ExpenseCategory $category): array => ['uuid' => $category->uuid, 'label' => $category->label])->all();
    }
}

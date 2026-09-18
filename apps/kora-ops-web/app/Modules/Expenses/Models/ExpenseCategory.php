<?php

namespace App\Modules\Expenses\Models;

use App\Modules\Support\Models\KoraModel;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends KoraModel
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory;

    protected static function newFactory(): ExpenseCategoryFactory
    {
        return ExpenseCategoryFactory::new();
    }

    protected $fillable = ['uuid', 'code', 'label', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}

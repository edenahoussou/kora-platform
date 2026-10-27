<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Carnet de bord OPS Mobile — catégorie de dépense « Taxes frontières »
 * (les catégories `fuel` et `tolls` existent déjà, voir
 * KoraExpenseCategorySeeder). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            return;
        }

        $categories = [
            ['code' => 'fuel', 'label' => 'Carburant'],
            ['code' => 'tolls', 'label' => 'Péages'],
            ['code' => 'border_taxes', 'label' => 'Taxes frontières'],
        ];

        foreach ($categories as $category) {
            if (DB::table('expense_categories')->where('code', $category['code'])->exists()) {
                continue;
            }

            DB::table('expense_categories')->insert([
                'uuid' => (string) Str::uuid(),
                'code' => $category['code'],
                'label' => $category['label'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            return;
        }

        $category = DB::table('expense_categories')->where('code', 'border_taxes')->first();
        if ($category !== null && ! DB::table('expenses')->where('expense_category_id', $category->id)->exists()) {
            DB::table('expense_categories')->where('id', $category->id)->delete();
        }
    }
};

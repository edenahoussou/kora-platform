<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lines')->where('type', 'regionale')->update(['type' => 'internationale']);
    }

    public function down(): void
    {
        // Irréversible : les lignes régionales converties ne sont plus distinguables.
    }
};

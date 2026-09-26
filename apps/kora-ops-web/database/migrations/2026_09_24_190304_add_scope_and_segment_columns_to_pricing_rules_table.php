<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->string('scope_type', 32)->default('network')->after('line_id');
            $table->foreignId('origin_stop_id')->nullable()->after('scope_type')->constrained('stops')->nullOnDelete();
            $table->foreignId('destination_stop_id')->nullable()->after('origin_stop_id')->constrained('stops')->nullOnDelete();
            $table->string('direction', 8)->default('both')->after('destination_stop_id');
            $table->index(['scope_type', 'status', 'priority']);
        });

        // Une règle existante rattachée à une ligne garde sa portée ligne ;
        // sans ligne, elle couvre déjà tout le réseau.
        DB::table('pricing_rules')->whereNotNull('line_id')->update(['scope_type' => 'line']);
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->dropIndex(['scope_type', 'status', 'priority']);
            $table->dropConstrainedForeignId('origin_stop_id');
            $table->dropConstrainedForeignId('destination_stop_id');
            $table->dropColumn(['scope_type', 'direction']);
        });
    }
};

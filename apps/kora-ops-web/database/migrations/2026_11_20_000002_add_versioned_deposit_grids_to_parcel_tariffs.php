<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_tariffs', function (Blueprint $table): void {
            $table->string('deposit_kind', 24)->default('agency')->after('corridor_id');
            $table->unsignedInteger('version')->default(1)->after('deposit_kind');
            $table->index(['corridor_id', 'deposit_kind', 'id']);
        });

        $tariffs = DB::table('parcel_tariffs')
            ->join('parcel_corridors', 'parcel_corridors.id', '=', 'parcel_tariffs.corridor_id')
            ->join('parcel_hubs', 'parcel_hubs.id', '=', 'parcel_corridors.origin_hub_id')
            ->orderBy('parcel_tariffs.id')
            ->get([
                'parcel_tariffs.id',
                'parcel_tariffs.corridor_id',
                'parcel_hubs.kind',
            ]);

        $versions = [];

        foreach ($tariffs as $tariff) {
            $kind = (string) $tariff->kind;
            $key = $tariff->corridor_id.':'.$kind;
            $versions[$key] = ($versions[$key] ?? 0) + 1;

            DB::table('parcel_tariffs')->where('id', $tariff->id)->update([
                'deposit_kind' => $kind,
                'version' => $versions[$key],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('parcel_tariffs', function (Blueprint $table): void {
            $table->dropIndex(['corridor_id', 'deposit_kind', 'id']);
            $table->dropColumn(['deposit_kind', 'version']);
        });
    }
};

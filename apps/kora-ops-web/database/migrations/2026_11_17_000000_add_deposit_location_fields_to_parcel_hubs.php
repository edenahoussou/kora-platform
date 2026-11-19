<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_hubs', function (Blueprint $table): void {
            $table->unsignedBigInteger('kora_point_id')->nullable()->after('agency_id');
            $table->string('kind', 16)->default('agency')->after('kora_point_id');
            $table->timestampTz('archived_at')->nullable()->after('enabled');
            $table->unique('kora_point_id');
            $table->index('archived_at');
        });

        DB::table('parcel_hubs')->whereNull('kind')->update(['kind' => 'agency']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE parcel_hubs ADD CONSTRAINT parcel_hubs_kind_check CHECK (kind IN ('agency', 'partner_point'))");
            DB::statement("ALTER TABLE parcel_hubs ADD CONSTRAINT parcel_hubs_partner_identity_check CHECK ((kind = 'partner_point' AND kora_point_id IS NOT NULL) OR (kind = 'agency' AND kora_point_id IS NULL))");
            DB::statement('ALTER TABLE parcel_hubs ADD CONSTRAINT parcel_hubs_kora_point_id_foreign FOREIGN KEY (kora_point_id) REFERENCES kora_points(id) ON DELETE RESTRICT');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE parcel_hubs DROP CONSTRAINT IF EXISTS parcel_hubs_kora_point_id_foreign');
            DB::statement('ALTER TABLE parcel_hubs DROP CONSTRAINT IF EXISTS parcel_hubs_partner_identity_check');
            DB::statement('ALTER TABLE parcel_hubs DROP CONSTRAINT IF EXISTS parcel_hubs_kind_check');
        }

        Schema::table('parcel_hubs', function (Blueprint $table): void {
            $table->dropUnique(['kora_point_id']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['kora_point_id', 'kind', 'archived_at']);
        });
    }
};

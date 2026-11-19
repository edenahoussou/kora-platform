<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table): void {
            $table->unsignedBigInteger('deposit_hub_id')->nullable()->after('corridor_id');
            $table->unsignedBigInteger('selling_agency_id')->nullable()->after('deposit_hub_id');
            $table->index('deposit_hub_id');
            $table->index('selling_agency_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE parcels ADD CONSTRAINT parcels_deposit_hub_id_foreign FOREIGN KEY (deposit_hub_id) REFERENCES parcel_hubs(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE parcels ADD CONSTRAINT parcels_selling_agency_id_foreign FOREIGN KEY (selling_agency_id) REFERENCES agencies(id) ON DELETE RESTRICT');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE parcels DROP CONSTRAINT IF EXISTS parcels_selling_agency_id_foreign');
            DB::statement('ALTER TABLE parcels DROP CONSTRAINT IF EXISTS parcels_deposit_hub_id_foreign');
        }

        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropIndex(['selling_agency_id']);
            $table->dropIndex(['deposit_hub_id']);
            $table->dropColumn(['deposit_hub_id', 'selling_agency_id']);
        });
    }
};

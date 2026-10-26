<?php

use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $standard = ServiceClass::query()->firstOrCreate(
            ['code' => ServiceClass::STANDARD_CODE],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Standard',
                'sort_order' => 0,
                'status' => 'active',
            ],
        );

        DB::table('fares')
            ->where('service_class_id', '!=', $standard->id)
            ->update(['service_class_id' => $standard->id]);

        ServiceClass::query()
            ->where('code', '!=', ServiceClass::STANDARD_CODE)
            ->delete();
    }

    public function down(): void
    {
        // Irréversible : les classes archivées ne sont pas restaurées.
    }
};

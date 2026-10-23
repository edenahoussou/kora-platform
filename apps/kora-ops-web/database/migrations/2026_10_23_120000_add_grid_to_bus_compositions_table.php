<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bus_compositions', function (Blueprint $table): void {
            $table->string('description')->nullable()->after('name');
            $table->json('grid')->nullable()->after('layout');
        });

        Schema::table('seats', function (Blueprint $table): void {
            $table->string('status_reason')->nullable()->after('status');
        });

        Schema::create('seat_status_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seat_id')->constrained('seats')->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        foreach (DB::table('bus_compositions')->whereNull('grid')->get() as $composition) {
            DB::table('bus_compositions')
                ->where('id', $composition->id)
                ->update(['grid' => json_encode($this->gridFromLayout(json_decode((string) $composition->layout, true) ?: []))]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_status_events');

        Schema::table('seats', function (Blueprint $table): void {
            $table->dropColumn('status_reason');
        });

        Schema::table('bus_compositions', function (Blueprint $table): void {
            $table->dropColumn(['description', 'grid']);
        });
    }

    /**
     * @param  list<array{type?: string, codes?: list<string>}>  $layout
     * @return array{rows: int, cols: int, cells: list<array<string, mixed>>}
     */
    private function gridFromLayout(array $layout): array
    {
        $cells = [];
        $rows = 0;
        $cols = 0;

        foreach ($layout as $group) {
            $type = $group['type'] ?? 'standard';
            foreach ($group['codes'] ?? [] as $code) {
                if (preg_match('/^(\d+)([A-Z]+)$/', (string) $code, $m) !== 1) {
                    continue;
                }

                $y = ((int) $m[1]) - 1;
                $x = 0;
                foreach (str_split($m[2]) as $letter) {
                    $x = ($x * 26) + (ord($letter) - ord('A') + 1);
                }
                $x -= 1;

                $cells[] = [
                    'kind' => 'seat',
                    'x' => $x,
                    'y' => $y,
                    'code' => (string) $code,
                    'seatKind' => in_array($type, ['standard', 'crew', 'premium'], true) ? $type : 'standard',
                ];
                $rows = max($rows, $y + 1);
                $cols = max($cols, $x + 1);
            }
        }

        return ['rows' => $rows, 'cols' => $cols, 'cells' => $cells];
    }
};

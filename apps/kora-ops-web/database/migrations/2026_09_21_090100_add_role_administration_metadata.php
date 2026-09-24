<?php

use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Data\SystemRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('guard_name');
            $table->string('label')->nullable()->after('is_system');
            $table->text('description')->nullable()->after('label');
            $table->string('category', 32)->default(RoleCategory::Central->value)->after('description');
            $table->boolean('is_active')->default(true)->after('category');
            $table->index(['category', 'is_active']);
        });

        foreach (SystemRole::cases() as $role) {
            DB::table('roles')
                ->where('name', $role->value)
                ->where('guard_name', 'web')
                ->update([
                    'is_system' => true,
                    'label' => $role->translatedLabel(),
                    'category' => $role->category()->value,
                    'is_active' => true,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['roles_category_is_active_index']);
            $table->dropColumn(['is_system', 'label', 'description', 'category', 'is_active']);
        });
    }
};

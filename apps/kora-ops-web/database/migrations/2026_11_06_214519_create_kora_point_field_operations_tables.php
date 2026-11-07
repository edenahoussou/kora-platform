<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kora_point_visits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('point_id')->constrained('kora_points')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('visit_type', 40);
            $table->unsignedTinyInteger('score');
            $table->text('report');
            $table->string('status', 24)->default('closed')->index();
            $table->timestamps();
            $table->index(['point_id', 'created_at']);
        });

        Schema::create('kora_point_visit_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('visit_id')->constrained('kora_point_visits')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('label');
            $table->date('due_date');
            $table->string('status', 24)->default('pending')->index();
            $table->text('realization_note')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamp('realized_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('kora_point_visit_photos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('visit_id')->constrained('kora_point_visits')->restrictOnDelete();
            $table->string('disk', 32)->default('private');
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('kora_point_plv_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('point_id')->constrained('kora_points')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('support', 32);
            $table->unsignedInteger('quantity');
            $table->string('status', 24)->default('prepared')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['point_id', 'created_at']);
        });

        DB::table('permissions')->insertOrIgnore([
            'name' => 'kora-points.visits.actions.update',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('guard_name', 'web')->where('name', 'kora-points.visits.actions.update')->value('id');
        $agentRoleId = DB::table('roles')->where('guard_name', 'web')->where('name', 'kora_point_agent')->value('id');
        if ($permissionId !== null && $agentRoleId !== null) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $agentRoleId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('kora_point_plv_orders');
        Schema::dropIfExists('kora_point_visit_photos');
        Schema::dropIfExists('kora_point_visit_actions');
        Schema::dropIfExists('kora_point_visits');
        $permissionId = DB::table('permissions')->where('guard_name', 'web')->where('name', 'kora-points.visits.actions.update')->value('id');
        if ($permissionId !== null) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

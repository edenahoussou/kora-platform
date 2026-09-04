<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->string('reference')->unique();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('monthly_credit_limit')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_account_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_account_id')->constrained('company_accounts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 32)->default('member');
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_account_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('group_booking_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('minimum_passengers')->default(5);
            $table->unsignedSmallInteger('maximum_passengers')->default(10);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('group_discount_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('active')->index();
            $table->unsignedSmallInteger('passenger_threshold');
            $table->decimal('percentage', 7, 4);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropUnique('reservations_user_idempotency_unique');
            $table->renameColumn('user_id', 'created_by_user_id');
            $table->foreignId('payer_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->string('payer_type', 32)->default('personal')->after('payer_user_id');
            $table->foreignId('company_account_id')->nullable()->after('payer_type')->constrained('company_accounts')->restrictOnDelete();
            $table->string('purchase_type', 32)->default('individual')->after('company_account_id');
            $table->unsignedSmallInteger('group_size')->nullable()->after('purchase_type');
            $table->index(['created_by_user_id', 'status']);
            $table->index(['payer_type', 'company_account_id']);
            $table->unique(['created_by_user_id', 'idempotency_key'], 'reservations_creator_idempotency_unique');
        });

        Schema::table('reservation_passengers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('reservation_id')->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active')->after('user_id')->index();
            $table->unsignedInteger('refunded_amount')->default(0)->after('status');
            $table->index(['phone', 'user_id']);
        });

        Schema::create('company_credit_holds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_account_id')->constrained('company_accounts')->restrictOnDelete();
            $table->foreignId('reservation_id')->unique()->constrained('reservations')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('period_key', 7);
            $table->string('status', 32)->default('reserved')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->index(['company_account_id', 'period_key', 'status']);
        });

        Schema::create('company_credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_account_id')->constrained('company_accounts')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('amount');
            $table->string('period_key', 7);
            $table->string('reference')->unique();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_account_id', 'period_key', 'type']);
        });

        Schema::table('price_quotes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('passenger_count')->default(1)->after('final_amount');
            $table->unsignedInteger('subtotal_amount')->default(0)->after('passenger_count');
            $table->unsignedInteger('group_discount_amount')->default(0)->after('subtotal_amount');
            $table->unsignedInteger('total_amount')->default(0)->after('group_discount_amount');
        });

        Schema::table('itinerary_quotes', function (Blueprint $table): void {
            $table->unsignedInteger('subtotal_amount')->default(0)->after('passenger_count');
            $table->unsignedInteger('dynamic_adjustment_amount')->default(0)->after('subtotal_amount');
            $table->unsignedInteger('group_discount_amount')->default(0)->after('dynamic_adjustment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('price_quotes', function (Blueprint $table): void {
            $table->dropColumn(['passenger_count', 'subtotal_amount', 'group_discount_amount', 'total_amount']);
        });
        Schema::table('itinerary_quotes', function (Blueprint $table): void {
            $table->dropColumn(['subtotal_amount', 'dynamic_adjustment_amount', 'group_discount_amount']);
        });
        Schema::dropIfExists('company_credit_transactions');
        Schema::dropIfExists('company_credit_holds');
        Schema::table('reservation_passengers', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['phone', 'user_id']);
            $table->dropColumn(['user_id', 'status', 'refunded_amount']);
        });
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropUnique('reservations_creator_idempotency_unique');
            $table->dropForeign(['company_account_id']);
            $table->dropForeign(['payer_user_id']);
            $table->dropIndex(['created_by_user_id', 'status']);
            $table->dropIndex(['payer_type', 'company_account_id']);
            $table->dropColumn(['payer_user_id', 'payer_type', 'company_account_id', 'purchase_type', 'group_size']);
            $table->renameColumn('created_by_user_id', 'user_id');
            $table->unique(['user_id', 'idempotency_key'], 'reservations_user_idempotency_unique');
        });
        Schema::dropIfExists('group_discount_rules');
        Schema::dropIfExists('group_booking_settings');
        Schema::dropIfExists('company_account_members');
        Schema::dropIfExists('company_accounts');
    }
};

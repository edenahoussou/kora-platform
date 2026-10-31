<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->string('kind', 32)->default('internal')->index();
        });
        Schema::create('kora_point_organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('commission_basis_points')->nullable();
            $table->unsignedBigInteger('ceiling_amount')->nullable();
            $table->json('settlement_calendar')->nullable();
            $table->timestamps();
        });
        Schema::create('kora_points', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agency_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('kora_point_organizations')->restrictOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->string('responsible_name');
            $table->string('phone');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('commission_basis_points')->nullable();
            $table->unsignedBigInteger('ceiling_amount')->nullable();
            $table->json('settlement_calendar')->nullable();
            $table->timestamps();
        });
        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('origin_kora_point_id')->nullable()->constrained('kora_points')->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('kora_point_organization_id')->nullable()->constrained('kora_point_organizations')->restrictOnDelete();
        });
        Schema::create('kora_point_sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('point_id')->constrained('kora_points')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('kora_point_organizations')->restrictOnDelete();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('open')->index();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_amount');
            $table->bigInteger('outstanding_amount');
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->unsignedBigInteger('commission_reversed')->default(0);
            $table->json('terms_snapshot');
            $table->timestamp('paid_at');
            $table->timestamp('due_at')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['point_id', 'outstanding_amount']);
        });
        Schema::create('kora_point_remittances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained('kora_point_organizations')->restrictOnDelete();
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('received_amount')->nullable();
            $table->string('method', 32);
            $table->string('reference');
            $table->text('note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('kora_point_remittance_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('remittance_id')->constrained('kora_point_remittances')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('kora_point_sales')->restrictOnDelete();
            $table->foreignId('claimed_sale_id')->nullable()->unique()->constrained('kora_point_sales')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->unique(['remittance_id', 'sale_id']);
        });
        Schema::create('kora_point_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained('kora_point_sales')->restrictOnDelete();
            $table->foreignId('passenger_id')->nullable()->constrained('reservation_passengers')->restrictOnDelete();
            $table->foreignId('payment_refund_id')->nullable()->unique()->constrained('payment_refunds')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('requested')->index();
            $table->string('executor', 32)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('method', 32)->nullable();
            $table->string('reference')->nullable();
            $table->string('beneficiary')->nullable();
            $table->text('reason');
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('kora_point_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('point_id')->constrained('kora_points')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('kora_point_sales')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event_key')->unique();
            $table->string('type', 40);
            $table->bigInteger('amount');
            $table->bigInteger('commission_delta')->default(0);
            $table->timestamps();
        });
        Schema::create('kora_partner_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('point_id')->nullable()->constrained('kora_points')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('kora_point_organizations')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('remittance_id')->nullable()->constrained('kora_point_remittances')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('kora_point_refunds')->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('title');
            $table->string('reference')->nullable();
            $table->date('signed_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('kora_point_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('kora_point_sales')->restrictOnDelete();
            $table->string('period_key');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
            $table->unique(['sale_id', 'period_key']);
        });
    }

    public function down(): void
    {
        foreach (['kora_point_reminders', 'kora_partner_documents', 'kora_point_entries', 'kora_point_refunds', 'kora_point_remittance_lines', 'kora_point_remittances', 'kora_point_sales'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('kora_point_organization_id');
        });
        Schema::table('reservations', fn (Blueprint $table) => $table->dropConstrainedForeignId('origin_kora_point_id'));
        Schema::dropIfExists('kora_points');
        Schema::dropIfExists('kora_point_organizations');
        Schema::table('agencies', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};

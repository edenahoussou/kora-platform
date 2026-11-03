<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_balance')->default(0);
            $table->unsignedBigInteger('points_balance')->default(0);
            $table->timestamp('points_expires_at')->nullable();
            $table->unsignedInteger('points_compensation_due')->default(0);
            $table->string('referral_code', 24)->unique();
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('balance_type', 16);
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount');
            $table->string('reason', 32);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('wallet_holds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_amount')->default(0);
            $table->unsignedBigInteger('points_amount')->default(0);
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamp('expires_at');
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
        });

        Schema::create('wallet_topups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 16)->default('pending');
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        Schema::create('wallet_restitutions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 16)->default('requested');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('payout_reference')->nullable();
            $table->string('payout_channel', 32)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('wallet_bonus_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('period_key', 32);
            $table->foreignId('transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['wallet_id', 'kind', 'period_key']);
        });

        Schema::create('wallet_usages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->timestamp('used_at');
            $table->timestamps();

            $table->unique(['wallet_id', 'reservation_id']);
            $table->index(['wallet_id', 'used_at']);
        });

        Schema::create('wallet_points_expiry_reminders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->date('expires_on');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['wallet_id', 'expires_on', 'days_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_points_expiry_reminders');
        Schema::dropIfExists('wallet_usages');
        Schema::dropIfExists('wallet_bonus_grants');
        Schema::dropIfExists('wallet_restitutions');
        Schema::dropIfExists('wallet_topups');
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};

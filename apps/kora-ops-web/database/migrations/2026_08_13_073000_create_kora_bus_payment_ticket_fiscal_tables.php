<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignId('reservation_passenger_id')->constrained('reservation_passengers')->restrictOnDelete();
            $table->string('qr_code')->unique();
            $table->string('short_code', 16)->unique();
            $table->string('status', 32)->default('issued')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fiscal_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('kora_reference')->unique();
            $table->string('mecef_uid')->nullable()->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total_without_tax');
            $table->unsignedInteger('total_tax');
            $table->unsignedInteger('total_with_tax');
            $table->json('tax_snapshot');
            $table->timestamp('fiscalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fiscal_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fiscal_invoice_id')->constrained('fiscal_invoices')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price');
            $table->string('tax_code', 64);
            $table->decimal('tax_rate', 7, 4);
            $table->unsignedInteger('line_total');
            $table->timestamps();
        });

        Schema::create('fiscal_sync_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fiscal_invoice_id')->constrained('fiscal_invoices')->cascadeOnDelete();
            $table->string('operation', 64);
            $table->unsignedSmallInteger('attempt_number');
            $table->string('request_hash');
            $table->string('response_code')->nullable();
            $table->string('status', 32)->index();
            $table->timestamp('attempted_at');
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_invoice_id', 'operation', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_sync_attempts');
        Schema::dropIfExists('fiscal_invoice_lines');
        Schema::dropIfExists('fiscal_invoices');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payments');
    }
};

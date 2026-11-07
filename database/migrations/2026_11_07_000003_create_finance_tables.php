<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_counters', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('category', ['monthly', 'yearly', 'one_time', 'custom']);
            $table->unsignedSmallInteger('due_day')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('allow_partial')->default(false);
            $table->enum('late_fee_type', ['fixed', 'percent'])->nullable();
            $table->bigInteger('late_fee_value')->nullable();
            $table->unsignedSmallInteger('late_fee_after_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('fee_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fee_type_id')->constrained()->cascadeOnDelete();
            $table->enum('scope', ['default', 'level', 'classroom', 'dormitory', 'student']);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('scope_level')->nullable();
            $table->bigInteger('amount');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestampsTz(6);
            $table->index(['fee_type_id', 'scope', 'scope_id']);
        });
        DB::statement('ALTER TABLE fee_rates ADD CONSTRAINT fee_rates_amount_positive CHECK (amount >= 0)');

        Schema::create('student_fee_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('custom_amount')->nullable();
            $table->date('start_month');
            $table->date('end_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
            $table->index(['student_id', 'fee_type_id']);
        });

        Schema::create('student_discounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['percent', 'fixed']);
            $table->bigInteger('value');
            $table->string('reason');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->index('student_id');
        });
        DB::statement("ALTER TABLE student_discounts ADD CONSTRAINT student_discounts_value CHECK (value >= 0 AND (type <> 'percent' OR value <= 100))");

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_no')->unique();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('applicant_id')->nullable();
            $table->date('period')->nullable();
            $table->date('due_date');
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('late_fee_total')->default(0);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('paid_total')->default(0);
            $table->enum('status', ['unpaid', 'partial', 'paid', 'overdue', 'void'])->default('unpaid');
            $table->enum('source', ['auto', 'manual', 'ppdb'])->default('auto');
            $table->text('void_reason')->nullable();
            $table->softDeletesTz(precision: 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->index(['status', 'due_date']);
            $table->index(['student_id', 'period']);
        });
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amounts CHECK (total >= 0 AND paid_total >= 0)');

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('period_key')->nullable();
            $table->string('description');
            $table->unsignedInteger('qty')->default(1);
            $table->bigInteger('unit_price');
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('amount');
            $table->boolean('is_late_fee')->default(false);
            $table->timestampsTz(6);
            $table->unique(['student_id', 'fee_type_id', 'period_key']);
        });
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_amounts CHECK (unit_price >= 0 AND discount >= 0 AND amount >= 0)');

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('bank_name');
            $table->string('account_no');
            $table->string('account_holder');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz(6);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('payment_no')->unique();
            $table->foreignId('payer_guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->enum('purpose', ['invoice', 'wallet_topup', 'admission_fee'])->default('invoice');
            $table->enum('method', ['midtrans', 'manual_transfer', 'cash', 'wallet']);
            $table->string('channel')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('admin_fee')->default(0);
            $table->unsignedSmallInteger('unique_code')->nullable();
            $table->bigInteger('gross_amount');
            $table->enum('status', ['pending', 'review', 'settlement', 'failed', 'expired', 'refunded'])->default('pending');
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('gateway_transaction_id')->nullable();
            $table->jsonb('gateway_payload')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('proof_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at', 6)->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestampTz('paid_at', 6)->nullable();
            $table->timestampTz('expires_at', 6)->nullable();
            $table->text('refund_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->index(['status', 'created_at']);
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amounts CHECK (amount >= 0 AND admin_fee >= 0 AND gross_amount >= 0)');

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->timestampsTz(6);
            $table->unique(['payment_id', 'invoice_id']);
        });
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount CHECK (amount > 0)');

        Schema::create('cash_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->enum('type', ['in', 'out']);
            $table->timestampsTz(6);
            $table->unique(['name', 'type']);
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['in', 'out']);
            $table->foreignId('category_id')->constrained('cash_categories')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->index(['transaction_date', 'type']);
            $table->index(['source_type', 'source_id']);
        });
        DB::statement('ALTER TABLE cash_transactions ADD CONSTRAINT cash_transactions_amount CHECK (amount > 0)');
        // Pembayaran yang sama tidak boleh tercatat dua kali ke kas pada kategori yang sama.
        DB::statement('CREATE UNIQUE INDEX cash_transactions_source_once ON cash_transactions (source_type, source_id, category_id) WHERE source_id IS NOT NULL');
    }

    public function down(): void
    {
        foreach (['cash_transactions', 'cash_categories', 'payment_allocations', 'payments', 'bank_accounts', 'invoice_items', 'invoices', 'student_discounts', 'student_fee_assignments', 'fee_rates', 'fee_types', 'document_counters'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

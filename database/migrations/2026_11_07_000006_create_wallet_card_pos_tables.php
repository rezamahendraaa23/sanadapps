<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger_block_changes() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Buku besar % tidak boleh diubah atau dihapus. Koreksi lewat transaksi baru.', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->unique()->constrained()->restrictOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('daily_limit')->nullable();
            $table->bigInteger('per_transaction_limit')->nullable();
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_not_negative CHECK (balance >= 0)');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_limits_positive CHECK ((daily_limit IS NULL OR daily_limit >= 0) AND (per_transaction_limit IS NULL OR per_transaction_limit >= 0))');

        Schema::create('student_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('card_uid')->nullable()->unique();
            $table->text('qr_secret');
            $table->string('pin_hash')->nullable();
            $table->unsignedSmallInteger('pin_failed_count')->default(0);
            $table->timestampTz('locked_until', 6)->nullable();
            $table->enum('status', ['active', 'blocked', 'lost'])->default('active');
            $table->timestampTz('issued_at', 6);
            $table->timestampTz('blocked_at', 6)->nullable();
            $table->string('blocked_reason')->nullable();
            $table->timestampsTz(6);
        });
        DB::statement("CREATE UNIQUE INDEX student_cards_one_active ON student_cards (student_id) WHERE status = 'active'");

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->enum('type', ['reader_usb', 'camera_qr'])->default('reader_usb');
            $table->jsonb('scopes')->default('[]');
            $table->string('api_key_hash')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_seen_at', 6)->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('card_scans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('card_id')->nullable()->constrained('student_cards')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('scope', ['attendance', 'gate', 'canteen', 'library', 'meal']);
            $table->enum('result', ['ok', 'denied', 'blocked', 'not_found']);
            $table->string('reason')->nullable();
            $table->timestampTz('scanned_at', 6);
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->index(['scanned_at', 'result']);
            $table->index('card_id');
        });

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->enum('type', ['mart', 'warung', 'kantin'])->default('warung');
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name')->nullable();
            $table->string('location')->nullable();
            $table->jsonb('opening_hours')->nullable();
            $table->jsonb('payment_modes')->default('["card","qr"]');
            $table->decimal('commission_percent', 4, 2)->default(0);
            $table->bigInteger('pin_threshold')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        // Kasir per merchant. PRD hanya punya pic_user_id, padahal satu merchant bisa punya beberapa kasir (A52).
        Schema::create('merchant_user', function (Blueprint $table) {
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['merchant_id', 'user_id']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_restricted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz(6);
        });

        Schema::create('merchant_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->bigInteger('price');
            $table->bigInteger('cost_price')->nullable();
            $table->integer('stock')->nullable();
            $table->integer('min_stock')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
            $table->unique(['merchant_id', 'barcode']);
            $table->index(['merchant_id', 'is_active']);
        });
        DB::statement('ALTER TABLE merchant_products ADD CONSTRAINT merchant_products_values CHECK (price >= 0 AND (stock IS NULL OR stock >= 0))');

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['topup', 'spend', 'refund', 'adjustment', 'payout']);
            // PRD: nominal selalu positif dan arah dari jenis. Refund dan penyesuaian bisa dua arah (A51).
            $table->enum('direction', ['credit', 'debit']);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_scan_id')->nullable()->constrained('card_scans')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['wallet_id', 'created_at']);
        });
        DB::statement('ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_values CHECK (amount > 0 AND balance_after >= 0)');
        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_direction CHECK (
            (type = 'topup' AND direction = 'credit') OR (type IN ('spend', 'payout') AND direction = 'debit') OR type IN ('refund', 'adjustment')
        )");
        DB::unprepared('CREATE TRIGGER wallet_transactions_append_only BEFORE UPDATE OR DELETE ON wallet_transactions FOR EACH ROW EXECUTE FUNCTION ledger_block_changes()');

        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->enum('status', ['pending', 'settled', 'refunded'])->default('pending');
            $table->timestampsTz(6);
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no')->unique();
            $table->foreignId('cashier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->bigInteger('total');
            $table->bigInteger('commission')->default(0);
            $table->enum('scan_method', ['card', 'qr', 'face']);
            $table->enum('status', ['paid', 'void'])->default('paid');
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestampTz('voided_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->index(['merchant_id', 'created_at']);
            $table->index(['student_id', 'created_at']);
        });
        DB::statement('ALTER TABLE pos_orders ADD CONSTRAINT pos_orders_total CHECK (total > 0)');

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('merchant_products')->nullOnDelete();
            $table->string('name');
            $table->integer('qty');
            $table->bigInteger('unit_price');
            $table->bigInteger('amount');
        });

        Schema::create('merchant_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['sale', 'commission', 'refund', 'payout', 'adjust']);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['merchant_id', 'id']);
        });
        DB::statement('ALTER TABLE merchant_ledger ADD CONSTRAINT merchant_ledger_balance CHECK (balance_after >= 0)');
        DB::unprepared('CREATE TRIGGER merchant_ledger_append_only BEFORE UPDATE OR DELETE ON merchant_ledger FOR EACH ROW EXECUTE FUNCTION ledger_block_changes()');
    }

    public function down(): void
    {
        foreach (['merchant_ledger', 'pos_order_items', 'pos_orders', 'wallet_topups', 'wallet_transactions', 'merchant_products', 'product_categories', 'merchant_user', 'merchants', 'card_scans', 'devices', 'student_cards', 'wallets'] as $table) {
            Schema::dropIfExists($table);
        }

        DB::unprepared('DROP FUNCTION IF EXISTS ledger_block_changes()');
    }
};

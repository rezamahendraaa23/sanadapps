<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan POS atas permintaan pemilik (8 November 2026): shift kasir, penjualan tunai untuk
 * pembeli bukan santri, pemasok dan pembelian, kedaluwarsa, promo, retur, dan struk thermal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('opened_at', 6);
            $table->timestampTz('closed_at', 6)->nullable();
            $table->bigInteger('opening_cash')->default(0);
            $table->bigInteger('expected_cash')->nullable();
            $table->bigInteger('counted_cash')->nullable();
            $table->bigInteger('cash_sales')->nullable();
            $table->bigInteger('card_sales')->nullable();
            $table->bigInteger('refunds')->nullable();
            $table->unsignedInteger('order_count')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz(6);
            $table->index(['merchant_id', 'opened_at']);
        });
        // Satu kasir hanya punya satu shift terbuka per merchant.
        DB::statement('CREATE UNIQUE INDEX pos_shifts_one_open ON pos_shifts (merchant_id, cashier_user_id) WHERE closed_at IS NULL');

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->bigInteger('paid_amount')->nullable();
            $table->bigInteger('change_amount')->nullable();
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('returned_amount')->default(0);
        });
        DB::statement('ALTER TABLE pos_orders ALTER COLUMN student_id DROP NOT NULL');
        DB::statement('ALTER TABLE pos_orders DROP CONSTRAINT pos_orders_scan_method_check');
        DB::statement("ALTER TABLE pos_orders ADD CONSTRAINT pos_orders_scan_method_check CHECK (scan_method IN ('card', 'qr', 'face', 'cash'))");
        // Penjualan kartu wajib punya santri; tunai wajib mencatat uang diterima.
        DB::statement("ALTER TABLE pos_orders ADD CONSTRAINT pos_orders_payer CHECK ((scan_method = 'cash' AND paid_amount IS NOT NULL AND paid_amount >= total) OR (scan_method <> 'cash' AND student_id IS NOT NULL))");

        Schema::create('pos_promotions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('merchant_products')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['percent', 'amount']);
            $table->bigInteger('value');
            $table->unsignedInteger('min_qty')->default(1);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
            $table->index(['merchant_id', 'is_active']);
        });
        DB::statement("ALTER TABLE pos_promotions ADD CONSTRAINT pos_promotions_value CHECK (value > 0 AND (kind <> 'percent' OR value <= 100) AND (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on))");

        Schema::table('pos_order_items', function (Blueprint $table) {
            $table->bigInteger('discount')->default(0);
            $table->foreignId('promotion_id')->nullable()->constrained('pos_promotions')->nullOnDelete();
            $table->integer('returned_qty')->default(0);
        });
        DB::statement('ALTER TABLE pos_order_items ADD CONSTRAINT pos_order_items_returned CHECK (returned_qty >= 0 AND returned_qty <= qty AND discount >= 0)');

        Schema::create('pos_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pos_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_order_item_id')->constrained()->restrictOnDelete();
            $table->integer('qty');
            $table->bigInteger('amount');
            $table->boolean('restock');
            $table->text('reason');
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
        });
        DB::statement('ALTER TABLE pos_returns ADD CONSTRAINT pos_returns_values CHECK (qty > 0 AND amount >= 0)');

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type IN ('in', 'out', 'sale', 'void', 'adjust', 'return', 'purchase'))");

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchase_no')->unique();
            $table->date('purchased_on');
            $table->bigInteger('total');
            $table->bigInteger('paid_amount')->default(0);
            $table->date('due_on')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz(6);
            $table->index(['merchant_id', 'purchased_on']);
        });
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_values CHECK (total >= 0 AND paid_amount >= 0 AND paid_amount <= total)');

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('merchant_products')->restrictOnDelete();
            $table->integer('qty');
            $table->bigInteger('unit_cost');
            $table->bigInteger('amount');
            $table->date('expires_on')->nullable();
            $table->index(['product_id', 'expires_on']);
        });
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT purchase_items_values CHECK (qty > 0 AND unit_cost >= 0)');

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->date('paid_on');
            $table->enum('method', ['cash', 'transfer'])->default('cash');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
        });
        DB::statement('ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type IN ('in', 'out', 'sale', 'void', 'adjust'))");
        Schema::dropIfExists('pos_returns');
        DB::statement('ALTER TABLE pos_order_items DROP CONSTRAINT pos_order_items_returned');
        Schema::table('pos_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['discount', 'returned_qty']);
        });
        Schema::dropIfExists('pos_promotions');
        DB::statement('ALTER TABLE pos_orders DROP CONSTRAINT pos_orders_payer');
        DB::statement('ALTER TABLE pos_orders DROP CONSTRAINT pos_orders_scan_method_check');
        DB::statement("ALTER TABLE pos_orders ADD CONSTRAINT pos_orders_scan_method_check CHECK (scan_method IN ('card', 'qr', 'face'))");
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['customer_name', 'paid_amount', 'change_amount', 'discount', 'returned_amount']);
        });
        Schema::dropIfExists('pos_shifts');
    }
};

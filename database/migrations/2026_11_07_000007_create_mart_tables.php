<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_merchant_limits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
            $table->bigInteger('daily_limit')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE wallet_merchant_limits ADD CONSTRAINT wallet_merchant_limits_target CHECK ((merchant_id IS NULL) <> (category_id IS NULL))');
        DB::statement('CREATE UNIQUE INDEX wallet_limits_merchant_once ON wallet_merchant_limits (wallet_id, merchant_id) WHERE merchant_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX wallet_limits_category_once ON wallet_merchant_limits (wallet_id, category_id) WHERE category_id IS NOT NULL');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('merchant_products')->restrictOnDelete();
            $table->enum('type', ['in', 'out', 'sale', 'void', 'adjust']);
            $table->integer('quantity');
            $table->integer('stock_after');
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['product_id', 'id']);
        });
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_values CHECK (quantity <> 0 AND stock_after >= 0)');
        DB::unprepared('CREATE TRIGGER stock_movements_append_only BEFORE UPDATE OR DELETE ON stock_movements FOR EACH ROW EXECUTE FUNCTION ledger_block_changes()');

        Schema::create('merchant_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->enum('status', ['requested', 'approved', 'paid', 'rejected'])->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('paid_at', 6)->nullable();
            $table->enum('method', ['cash', 'transfer'])->default('cash');
            $table->text('note')->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignId('cash_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampsTz(6);
            $table->index(['status', 'created_at']);
        });
        DB::statement('ALTER TABLE merchant_payouts ADD CONSTRAINT merchant_payouts_amount CHECK (amount > 0)');

        Schema::table('pos_order_items', function (Blueprint $table) {
            $table->bigInteger('cost_price')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('cost_price');
        });

        foreach (['merchant_payouts', 'stock_movements', 'wallet_merchant_limits'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

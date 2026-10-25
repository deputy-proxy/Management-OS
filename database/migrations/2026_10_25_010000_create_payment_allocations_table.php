<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('customer_invoice_id')->nullable()->constrained('customer_invoices')->restrictOnDelete();
            $table->foreignId('vendor_invoice_id')->nullable()->constrained('vendor_invoices')->restrictOnDelete();
            $table->string('allocation_kind', 16);
            $table->decimal('amount_decimal', 19, 2);
            $table->char('currency_code', 3);
            $table->string('status', 16)->default('proposed');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['transaction_id', 'status'], 'payment_allocations_transaction_status_index');
            $table->index(['customer_invoice_id', 'status'], 'payment_allocations_customer_invoice_status_index');
            $table->index(['vendor_invoice_id', 'status'], 'payment_allocations_vendor_invoice_status_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_positive_amount_check CHECK (amount_decimal > 0)');
            DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_kind_check CHECK (allocation_kind IN ('invoice', 'fee'))");
            DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_status_check CHECK (status IN ('proposed', 'confirmed', 'reversed', 'rejected'))");
            DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_target_check CHECK ((allocation_kind = 'invoice' AND ((customer_invoice_id IS NOT NULL AND vendor_invoice_id IS NULL) OR (customer_invoice_id IS NULL AND vendor_invoice_id IS NOT NULL))) OR (allocation_kind = 'fee' AND customer_invoice_id IS NULL AND vendor_invoice_id IS NULL))");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_allocations') && DB::table('payment_allocations')->exists()) {
            throw new RuntimeException('Cannot drop payment allocation history while allocation records exist.');
        }

        Schema::dropIfExists('payment_allocations');
    }
};

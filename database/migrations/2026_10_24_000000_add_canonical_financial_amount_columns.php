<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $columns = [
        'transactions' => ['amount_decimal', 'currency_code'],
        'customer_invoices' => ['amount_without_vat_decimal', 'vat_decimal', 'currency_code'],
        'vendor_invoices' => ['amount_decimal', 'amount_without_vat_decimal', 'vat_decimal', 'currency_code'],
        'taxes' => ['amount_decimal', 'currency_code'],
        'financial_account_balances' => ['current_balance_decimal', 'currency_code'],
        'product_resources' => ['amount_decimal', 'currency_code'],
        'product_costs' => ['resource_amount_decimal', 'allocated_amount_decimal', 'currency_code'],
        'product_revenues' => ['amount_decimal', 'currency_code'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    if (str_ends_with($column, '_decimal')) {
                        $table->decimal($column, 19, 2)->nullable();
                    } else {
                        $table->char($column, 3)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $tableName => $columns) {
            foreach ($columns as $column) {
                if (DB::table($tableName)->whereNotNull($column)->exists()) {
                    throw new RuntimeException("Cannot roll back {$tableName}.{$column} while canonical financial values are populated.");
                }
            }
        }

        foreach ($this->columns as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};

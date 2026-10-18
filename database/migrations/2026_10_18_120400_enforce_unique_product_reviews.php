<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->foreign('customer_id', 'product_reviews_customer_id_foreign')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
            $table->unique(['product_type', 'product_id', 'customer_id'], 'product_reviews_product_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropUnique('product_reviews_product_customer_unique');
            $table->dropForeign('product_reviews_customer_id_foreign');
        });
    }
};

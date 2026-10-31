<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('policy_key', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->json('parameters');
            $table->json('required_parameters');
            $table->json('calculation_order');
            $table->string('rounding_mode', 16)->nullable();
            $table->unsignedTinyInteger('rounding_precision')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['policy_key', 'version']);
            $table->index(['policy_key', 'status', 'effective_from', 'effective_until'], 'payroll_policy_effective_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_policies');
    }
};

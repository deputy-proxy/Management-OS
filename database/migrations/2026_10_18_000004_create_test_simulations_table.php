<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_simulations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('employees');
            $table->foreignId('test_id')->nullable()->constrained('tests')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('title');
            $table->dateTime('date');
            $table->decimal('number_of_hours', 8, 2);
            $table->string('status')->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('test_simulations')) {
            if (DB::table('test_simulations')->exists()) {
                throw new RuntimeException('Test simulations may be referenced by payslip audit records; export and reconcile them before rollback.');
            }

            Schema::dropIfExists('test_simulations');
        }
    }
};

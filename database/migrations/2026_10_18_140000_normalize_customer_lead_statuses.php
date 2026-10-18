<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_leads')) {
            return;
        }

        DB::table('customer_leads')->whereRaw('LOWER(status) IN (?, ?)', ['new', 'lead'])->update(['status' => 'new']);
        DB::table('customer_leads')->whereRaw('LOWER(status) IN (?, ?)', ['contacted', 'in_progress'])->update(['status' => 'contacted']);
        DB::table('customer_leads')->whereRaw('LOWER(status) IN (?, ?)', ['qualified', 'qualifying'])->update(['status' => 'qualified']);
        DB::table('customer_leads')->whereRaw('LOWER(status) IN (?, ?)', ['proposal', 'proposed'])->update(['status' => 'proposal']);
        DB::table('customer_leads')->whereRaw('LOWER(status) IN (?, ?)', ['converted', 'customer'])->update(['status' => 'converted']);
        DB::table('customer_leads')->whereRaw('LOWER(status) IN (?, ?)', ['lost', 'closed_lost'])->update(['status' => 'lost']);
        DB::table('customer_leads')->whereNotIn('status', ['new', 'contacted', 'qualified', 'proposal', 'converted', 'lost'])->update(['status' => 'new']);
        DB::table('customer_leads')->where('status', 'converted')->where(function ($query): void {
            $query->whereNull('converted_customer_id')->orWhereNull('converted_at');
        })->update(['status' => 'new']);
    }

    public function down(): void
    {
        // Status normalization is intentionally irreversible to preserve valid lifecycle state.
    }
};

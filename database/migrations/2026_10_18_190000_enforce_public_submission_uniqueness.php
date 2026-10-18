<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicates('competition_entries', ['competition_id', 'email'], 'competition entry email');
        $this->assertNoDuplicates('customer_leads', ['email'], 'active customer lead email', true);
        $this->assertNoDuplicates('newsletter_subscribers', ['email'], 'active newsletter email', true);

        Schema::table('competition_entries', function (Blueprint $table): void {
            $table->unique(['competition_id', 'email'], 'competition_entries_competition_email_unique');
        });

        Schema::table('customer_leads', function (Blueprint $table): void {
            $table->string('active_email')->nullable()->virtualAs('CASE WHEN deleted_at IS NULL THEN email ELSE NULL END');
            $table->unique('active_email', 'customer_leads_active_email_unique');
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->string('active_email')->nullable()->virtualAs('CASE WHEN deleted_at IS NULL THEN email ELSE NULL END');
            $table->unique('active_email', 'newsletter_subscribers_active_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->dropUnique('newsletter_subscribers_active_email_unique');
            $table->dropColumn('active_email');
        });

        Schema::table('customer_leads', function (Blueprint $table): void {
            $table->dropUnique('customer_leads_active_email_unique');
            $table->dropColumn('active_email');
        });

        Schema::table('competition_entries', function (Blueprint $table): void {
            $table->dropUnique('competition_entries_competition_email_unique');
        });
    }

    /** @param array<int, string> $columns */
    private function assertNoDuplicates(string $table, array $columns, string $description, bool $activeOnly = false): void
    {
        $query = DB::table($table)->select($columns)->selectRaw('COUNT(*) AS duplicate_count')->groupBy($columns)->havingRaw('COUNT(*) > 1');

        if ($activeOnly) {
            $query->whereNull('deleted_at');
        }

        $duplicates = $query->limit(5)->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot enforce %s uniqueness: existing duplicate groups were found in %s. Reconcile duplicates before rerunning this migration.',
                $description,
                $table,
            ));
        }
    }
};

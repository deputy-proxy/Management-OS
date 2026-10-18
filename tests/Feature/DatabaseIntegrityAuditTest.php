<?php

namespace Tests\Feature;

use App\Models\CustomerLead;
use App\Models\Session;
use App\Models\SessionAttendance;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_integrity_audit_runs_without_mutating_clean_database(): void
    {
        $this->artisan('database:integrity-audit')
            ->expectsOutputToContain('Read-only integrity audit. No records will be changed.')
            ->expectsOutputToContain('No configured integrity findings detected.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('session_attendances', 0);
    }

    public function test_database_rejects_two_active_attendance_rows_for_same_session_and_student(): void
    {
        $session = Session::factory()->create();
        $student = Student::factory()->create();
        SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => $student->id]);

        $this->expectException(QueryException::class);
        SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => $student->id]);
    }

    public function test_soft_deleted_attendance_can_be_replaced_without_rewriting_history(): void
    {
        $session = Session::factory()->create();
        $student = Student::factory()->create();
        $historical = SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => $student->id]);
        $historical->delete();

        $replacement = SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => $student->id]);

        $this->assertNotNull($historical->fresh()->deleted_at);
        $this->assertNull($replacement->deleted_at);
        $this->assertDatabaseCount('session_attendances', 2);
    }

    public function test_attendance_foreign_keys_reject_missing_session_and_student(): void
    {
        $student = Student::factory()->create();

        try {
            SessionAttendance::factory()->create(['session_id' => 99999999, 'student_id' => $student->id]);
            $this->fail('Expected missing session reference to be rejected by the database.');
        } catch (QueryException) {
            $this->assertDatabaseCount('session_attendances', 0);
        }

        $session = Session::factory()->create();
        $this->expectException(QueryException::class);
        SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => 99999999]);
    }

    public function test_integrity_audit_reports_invalid_converted_lead_without_rewriting_it(): void
    {
        $lead = CustomerLead::factory()->create();
        DB::table('customer_leads')->where('id', $lead->id)->update(['status' => 'converted']);

        $this->artisan('database:integrity-audit')
            ->expectsOutputToContain('customer lead converted without customer/timestamp: 1')
            ->expectsOutputToContain('No data was modified.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('customer_leads', ['id' => $lead->id, 'status' => 'converted', 'converted_customer_id' => null, 'converted_at' => null]);
    }

    public function test_attendance_migration_aborts_on_existing_duplicate_active_rows_without_deleting_history(): void
    {
        $migration = require database_path('migrations/2026_10_18_200000_enforce_session_attendance_integrity.php');
        $migration->down();

        $session = Session::factory()->create();
        $student = Student::factory()->create();
        foreach ([1, 2] as $unused) {
            DB::table('session_attendances')->insert([
                'session_id' => $session->id,
                'student_id' => $student->id,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        try {
            $migration->up();
            $this->fail('Expected the migration to reject pre-existing duplicate attendance rows.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('duplicate attendance rows', $exception->getMessage());
        }

        $this->assertDatabaseCount('session_attendances', 2);
    }
}

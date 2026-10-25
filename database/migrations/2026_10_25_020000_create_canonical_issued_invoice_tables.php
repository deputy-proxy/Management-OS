<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'issued_invoice_events',
        'invoice_submission_attempts',
        'invoice_submissions',
        'issued_invoice_artifacts',
        'issued_invoice_tax_breakdowns',
        'issued_invoice_line_taxes',
        'issued_invoice_lines',
        'issued_invoice_parties',
        'issued_invoices',
        'legal_entities',
    ];

    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_name');
            $table->char('country_code', 2);
            $table->string('tax_identifier_type', 32)->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('vat_registration_id')->nullable();
            $table->string('registration_id')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('locality')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('region')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tax_identifier_type', 'tax_identifier'], 'legal_entities_tax_identifier_unique');
            $table->index(['legal_name', 'is_active'], 'legal_entities_name_active_index');
        });

        Schema::create('issued_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->foreignId('legacy_customer_invoice_id')->nullable()->unique()
                ->constrained('customer_invoices')->restrictOnDelete();
            $table->string('document_type', 24);
            $table->string('series', 64)->nullable();
            $table->string('invoice_number', 64)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('supply_date')->nullable();
            $table->date('due_date')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->decimal('net_total', 19, 2)->nullable();
            $table->decimal('tax_total', 19, 2)->nullable();
            $table->decimal('gross_total', 19, 2)->nullable();
            $table->string('payment_terms_code', 64)->nullable();
            $table->string('payment_status', 24)->nullable();
            $table->string('document_status', 24)->default('draft');
            $table->foreignId('corrects_issued_invoice_id')->nullable()
                ->constrained('issued_invoices')->restrictOnDelete();
            $table->string('correction_reason_code', 64)->nullable();
            $table->text('correction_reason')->nullable();
            $table->char('issued_snapshot_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['legal_entity_id', 'series', 'invoice_number'],
                'issued_invoices_legal_number_unique'
            );
            $table->index(['document_status', 'issue_date'], 'issued_invoices_status_issue_date_index');
            $table->index(['payment_status', 'due_date'], 'issued_invoices_payment_due_date_index');
            $table->index('corrects_issued_invoice_id', 'issued_invoices_correction_index');
        });

        Schema::create('issued_invoice_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_invoice_id')->constrained('issued_invoices')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->uuid('correlation_uuid')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['issued_invoice_id', 'created_at'], 'issued_invoice_events_invoice_created_index');
            $table->index('correlation_uuid', 'issued_invoice_events_correlation_index');
        });

        Schema::create('issued_invoice_parties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_invoice_id')->constrained('issued_invoices')->restrictOnDelete();
            $table->string('role', 16);
            $table->string('legal_name');
            $table->char('country_code', 2);
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('locality')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('region')->nullable();
            $table->string('tax_identifier_type', 32)->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('vat_registration_id')->nullable();
            $table->string('registration_id')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->unique(['issued_invoice_id', 'role'], 'issued_invoice_parties_role_unique');
        });

        Schema::create('issued_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_invoice_id')->constrained('issued_invoices')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->text('description');
            $table->string('seller_item_code', 100)->nullable();
            $table->decimal('quantity', 19, 6);
            $table->string('unit_code', 16);
            $table->decimal('unit_price_net', 19, 6);
            $table->decimal('discount_amount', 19, 2)->default(0);
            $table->decimal('net_amount', 19, 2);
            $table->timestamps();
            $table->unique(['issued_invoice_id', 'line_number'], 'issued_invoice_lines_number_unique');
            $table->index('issued_invoice_id', 'issued_invoice_lines_invoice_index');
        });

        Schema::create('issued_invoice_line_taxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_invoice_line_id')->constrained('issued_invoice_lines')->restrictOnDelete();
            $table->string('tax_scheme_code', 32);
            $table->string('tax_category_code', 32);
            $table->decimal('tax_rate_percent', 7, 4)->nullable();
            $table->decimal('taxable_base', 19, 2);
            $table->decimal('tax_amount', 19, 2);
            $table->string('exemption_reason_code', 64)->nullable();
            $table->text('exemption_reason')->nullable();
            $table->boolean('reverse_charge')->nullable();
            $table->timestamps();
            $table->index(['issued_invoice_line_id', 'tax_category_code'], 'issued_invoice_line_taxes_category_index');
        });

        Schema::create('issued_invoice_tax_breakdowns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_invoice_id')->constrained('issued_invoices')->restrictOnDelete();
            $table->string('tax_scheme_code', 32);
            $table->string('tax_category_code', 32);
            $table->decimal('tax_rate_percent', 7, 4)->nullable();
            $table->decimal('taxable_base', 19, 2);
            $table->decimal('tax_amount', 19, 2);
            $table->string('exemption_reason_code', 64)->nullable();
            $table->text('exemption_reason')->nullable();
            $table->timestamps();
            $table->index(['issued_invoice_id', 'tax_category_code'], 'issued_invoice_tax_breakdowns_category_index');
        });

        Schema::create('issued_invoice_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_invoice_id')->constrained('issued_invoices')->restrictOnDelete();
            $table->string('artifact_type', 40);
            $table->string('storage_disk', 64);
            $table->string('storage_key', 1024);
            $table->string('media_type', 127)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->char('sha256', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('source_artifact_id')->nullable()
                ->constrained('issued_invoice_artifacts')->restrictOnDelete();
            $table->timestamps();
            $table->index(['issued_invoice_id', 'artifact_type'], 'issued_invoice_artifacts_type_index');
        });

        Schema::create('invoice_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_invoice_id')->constrained('issued_invoices')->restrictOnDelete();
            $table->char('invoice_snapshot_hash', 64);
            $table->string('environment', 16);
            $table->string('technical_spec_version', 100)->nullable();
            $table->string('applicability_decision', 24);
            $table->text('applicability_reason')->nullable();
            $table->foreignId('payload_artifact_id')->nullable()
                ->constrained('issued_invoice_artifacts')->restrictOnDelete();
            $table->char('payload_sha256', 64)->nullable();
            $table->uuid('correlation_uuid')->unique();
            $table->string('external_message_id', 128)->nullable();
            $table->string('transmission_state', 32)->default('not_required');
            $table->string('official_processing_state', 32)->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('redacted_error_code', 128)->nullable();
            $table->text('redacted_error_message')->nullable();
            $table->foreignId('response_artifact_id')->nullable()
                ->constrained('issued_invoice_artifacts')->restrictOnDelete();
            $table->char('response_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['issued_invoice_id', 'invoice_snapshot_hash', 'environment'],
                'invoice_submissions_snapshot_environment_unique'
            );
            $table->unique(['environment', 'external_message_id'], 'invoice_submissions_external_id_unique');
            $table->index(['transmission_state', 'updated_at'], 'invoice_submissions_state_updated_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE issued_invoices ADD CONSTRAINT issued_invoices_document_type_check CHECK (document_type IN ('invoice', 'credit_note', 'debit_note'))");
            DB::statement("ALTER TABLE issued_invoices ADD CONSTRAINT issued_invoices_document_status_check CHECK (document_status IN ('draft', 'approved', 'issued', 'cancelled', 'corrected'))");
            DB::statement("ALTER TABLE issued_invoice_parties ADD CONSTRAINT issued_invoice_parties_role_check CHECK (role IN ('seller', 'buyer'))");
            DB::statement("ALTER TABLE invoice_submissions ADD CONSTRAINT invoice_submissions_environment_check CHECK (environment IN ('test', 'production'))");
        }

        Schema::create('invoice_submission_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_submission_id')->constrained('invoice_submissions')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->uuid('local_attempt_uuid')->unique();
            $table->timestamp('request_started_at');
            $table->timestamp('request_completed_at')->nullable();
            $table->string('transport_result', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('external_message_id', 128)->nullable();
            $table->string('redacted_error_code', 128)->nullable();
            $table->text('redacted_error_message')->nullable();
            $table->json('safe_request_metadata')->nullable();
            $table->json('safe_response_metadata')->nullable();
            $table->foreignId('response_artifact_id')->nullable()
                ->constrained('issued_invoice_artifacts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['invoice_submission_id', 'attempt_number'],
                'invoice_submission_attempts_number_unique'
            );
            $table->index(['transport_result', 'request_started_at'], 'invoice_submission_attempts_result_started_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE issued_invoice_lines ADD CONSTRAINT issued_invoice_lines_number_check CHECK (line_number > 0)');
            DB::statement('ALTER TABLE issued_invoice_lines ADD CONSTRAINT issued_invoice_lines_discount_check CHECK (discount_amount >= 0)');
            DB::statement('ALTER TABLE invoice_submission_attempts ADD CONSTRAINT invoice_submission_attempts_number_check CHECK (attempt_number > 0)');
            DB::statement('ALTER TABLE invoice_submission_attempts ADD CONSTRAINT invoice_submission_attempts_http_status_check CHECK (http_status IS NULL OR http_status BETWEEN 100 AND 599)');
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Cannot drop canonical invoice history while {$table} contains records.");
            }
        }

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};

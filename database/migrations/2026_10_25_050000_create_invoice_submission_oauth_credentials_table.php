<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_submission_oauth_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->string('environment', 16);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('token_refreshed_at')->nullable();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'environment'], 'invoice_submission_oauth_credentials_entity_env_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoice_submission_oauth_credentials ADD CONSTRAINT invoice_submission_oauth_credentials_environment_check CHECK (environment IN ('test', 'production'))");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_submission_oauth_credentials')
            && DB::table('invoice_submission_oauth_credentials')->exists()) {
            throw new RuntimeException('Cannot drop encrypted e-Factura OAuth credentials while credentials exist.');
        }

        Schema::dropIfExists('invoice_submission_oauth_credentials');
    }
};

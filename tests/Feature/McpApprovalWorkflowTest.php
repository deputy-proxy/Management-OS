<?php

namespace Tests\Feature;

use App\Mcp\Authorization\Scopes\McpGlobalRecordScope;
use App\Models\Mcp\McpApproval;
use App\Models\Mcp\McpExecutionAudit;
use App\Models\User;
use App\Services\Mcp\McpApprovalAccess;
use App\Services\Mcp\McpApprovalService;
use App\Services\Mcp\McpExecutionAuditRecorder;
use App\Services\Mcp\McpPayloadCanonicalizer;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class McpApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'mcp.ai_principal_email' => 'chatgpt@edventure.ro',
            'mcp.domain_permission_guard' => 'web',
            'mcp.permission_matrix_version' => '1.0.0',
            'mcp.approval_ttl_minutes' => 15,
            'mcp.approval_max_payload_bytes' => 65536,
            'mcp.execution_stale_minutes' => 5,
            'mcp.tool_permissions' => [
                'create_task' => [
                    'mcp_scope' => 'mcp.operations.tasks.write',
                    'domain_permissions' => ['task.create'],
                    'record_scope' => McpGlobalRecordScope::class,
                    'allowed_fields' => ['approval_id' => true, 'status' => true, 'expires_at' => true, 'preview_summary' => true, 'task_id' => true, 'title' => true],
                    'operation_type' => 'write',
                    'requires_approval' => true,
                ],
            ],
        ]);

        request()->attributes->set('oauth_client_id', 'd8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c');
        request()->attributes->set('request_id', 'mcp-request-test-default');

        $keyPath = storage_path('framework/testing/mcp-approval-keys');
        File::ensureDirectoryExists($keyPath, 0700, true);
        $privateKeyPath = $keyPath.'/oauth-private.key';
        $publicKeyPath = $keyPath.'/oauth-public.key';
        if (! is_file($privateKeyPath) || ! is_file($publicKeyPath)) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $this->assertNotFalse($key);
            $this->assertTrue(openssl_pkey_export($key, $privateKey));
            $details = openssl_pkey_get_details($key);
            $this->assertIsArray($details);
            file_put_contents($privateKeyPath, $privateKey);
            file_put_contents($publicKeyPath, $details['key']);
            chmod($privateKeyPath, 0600);
            chmod($publicKeyPath, 0600);
        }
        Passport::loadKeysFrom($keyPath);
    }

    public function test_approval_payload_is_encrypted_payload_bound_and_idempotent(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        request()->attributes->set('oauth_client_id', 'd8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c');
        request()->attributes->set('request_id', 'mcp-request-approval-001');

        $service = app(McpApprovalService::class);
        $arguments = ['title' => 'Quarterly review task', 'description' => 'Prepare the Q4 review'];
        $preview = ['action' => 'Create task', 'title' => 'Quarterly review task'];
        $approval = $service->request(
            $principal,
            'create_task',
            $arguments,
            $preview,
            'task-request-001',
            'task',
            null,
        );

        $raw = DB::table('mcp_approvals')->where('id', $approval->id)->first();
        $this->assertNotSame(json_encode($arguments), $raw->arguments);
        $this->assertStringNotContainsString('Quarterly review task', $raw->arguments);
        $this->assertStringNotContainsString('Quarterly review task', $raw->preview);
        $this->assertSame('d8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c', $approval->oauth_client_id);
        $this->assertSame('mcp-request-approval-001', $approval->request_id);

        $duplicate = $service->request(
            $principal,
            'create_task',
            $arguments,
            $preview,
            'task-request-001',
            'task',
            null,
        );
        $this->assertSame($approval->id, $duplicate->id);

        try {
            $service->request(
                $principal,
                'create_task',
                ['title' => 'Different task'],
                ['action' => 'Create task', 'title' => 'Different task'],
                'task-request-001',
                'task',
                null,
            );
            $this->fail('Reusing an idempotency key with a different payload must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approval_requested',
            'approval_id' => $approval->id,
            'payload_hash' => $approval->payload_hash,
            'oauth_client_id' => 'd8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c',
            'request_id' => 'mcp-request-approval-001',
        ]);
    }

    public function test_approval_rejects_payload_hash_tampering(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $approval = $this->requestApproval($principal);
        $reviewer = $this->reviewer();

        DB::table('mcp_approvals')
            ->where('id', $approval->id)
            ->update(['payload_hash' => str_repeat('0', 64)]);

        $this->expectException(ValidationException::class);
        app(McpApprovalService::class)->approve($approval, $reviewer, 'Reject the altered payload.');
    }

    public function test_idempotency_key_is_bound_to_the_original_oauth_client(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $approval = $this->requestApproval($principal);
        request()->attributes->set('oauth_client_id', 'c54f37c0-8c83-4d8e-9b70-bbc9a0e42c18');

        $this->expectException(ValidationException::class);
        app(McpApprovalService::class)->request(
            $principal,
            'create_task',
            ['title' => 'Synthetic MCP task', 'description' => 'A test-only operation'],
            ['action' => 'Create task', 'title' => 'Synthetic MCP task'],
            $approval->idempotency_key,
            'task',
            null,
        );
    }

    public function test_approved_operation_cannot_be_executed_by_a_different_oauth_client(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $approval = app(McpApprovalService::class)->approve(
            $this->requestApproval($principal),
            $reviewer,
            'Approved for execution.',
        );
        request()->attributes->set('oauth_client_id', 'c54f37c0-8c83-4d8e-9b70-bbc9a0e42c18');

        $calls = 0;
        $this->expectException(AuthorizationException::class);
        app(McpApprovalService::class)->executeApproved($approval, $principal, function () use (&$calls): array {
            $calls++;

            return ['unexpected' => true];
        });
        $this->assertSame(0, $calls);
    }

    public function test_ai_principal_cannot_approve_its_own_operation(): void
    {
        $principal = $this->principal(['Tasks' => ['task.create'], 'System' => ['mcp_approval.approve']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $approval = $this->requestApproval($principal);

        $this->assertFalse(app(McpApprovalAccess::class)->canReview($principal));

        $this->expectException(AuthorizationException::class);
        app(McpApprovalService::class)->approve($approval, $principal, 'Self approval is prohibited.');
    }

    public function test_human_approval_is_single_use_and_successful_execution_is_replayed(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $service = app(McpApprovalService::class);
        $approval = $service->approve($this->requestApproval($principal), $reviewer, 'Reviewed the task request and approved it.');

        $calls = 0;
        $executor = function (User $actor, array $arguments) use (&$calls): array {
            $calls++;

            return [
                'task_id' => 73,
                'title' => $arguments['title'],
                'domain_event_reference' => 'task:73:created',
            ];
        };

        $result = $service->executeApproved($approval, $principal, $executor);
        $this->assertSame([
            'task_id' => 73,
            'title' => 'Synthetic MCP task',
            'domain_event_reference' => 'task:73:created',
        ], $result);
        $this->assertSame(1, $calls);
        $this->assertSame(McpApproval::STATUS_EXECUTED, $approval->fresh()->status);
        $this->assertSame($reviewer->id, $approval->fresh()->human_approver_id);
        $this->assertSame($result, $approval->fresh()->result);
        $rawApproval = DB::table('mcp_approvals')->where('id', $approval->id)->first();
        $this->assertStringNotContainsString('task:73:created', $rawApproval->result);

        $replayed = $service->executeApproved($approval->fresh(), $principal, $executor);
        $this->assertSame($result, $replayed);
        $this->assertSame(1, $calls);

        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approved_operation_execution',
            'approval_id' => $approval->id,
            'status' => 'succeeded',
            'human_approver_id' => $reviewer->id,
            'domain_event_reference' => 'task:73:created',
        ]);
    }

    public function test_approval_decision_cannot_be_reused_or_overwritten(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $service = app(McpApprovalService::class);
        $approval = $service->approve(
            $this->requestApproval($principal),
            $reviewer,
            'Reviewed and approved.',
        );

        try {
            $service->reject($approval, $reviewer, 'Attempt to overwrite the prior decision.');
            $this->fail('An approval decision must be single-use.');
        } catch (ValidationException) {
            $this->assertSame(McpApproval::STATUS_APPROVED, $approval->fresh()->status);
        }

        $this->assertSame(1, DB::table('mcp_execution_audits')->where('event_type', 'approval_approved')->count());
        $this->assertSame(0, DB::table('mcp_execution_audits')->where('event_type', 'approval_rejected')->count());
    }

    public function test_reviewer_must_be_distinct_from_known_human_initiator(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $initiator = $this->reviewer();
        request()->attributes->set('mcp_human_initiator_id', $initiator->id);
        $approval = $this->requestApproval($principal);

        $this->expectException(AuthorizationException::class);
        app(McpApprovalService::class)->approve($approval, $initiator, 'The initiator cannot approve this request.');
    }

    public function test_human_rejection_and_ai_cancellation_are_recorded_without_execution(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $service = app(McpApprovalService::class);

        $rejected = $service->reject($this->requestApproval($principal), $reviewer, 'The task description is incomplete.');
        $this->assertSame(McpApproval::STATUS_REJECTED, $rejected->status);
        $this->assertSame($reviewer->id, $rejected->human_approver_id);
        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approval_rejected',
            'status' => McpApproval::STATUS_REJECTED,
        ]);

        $cancelled = $service->request(
            $principal,
            'create_task',
            ['title' => 'Cancelled task'],
            ['action' => 'Create task', 'title' => 'Cancelled task'],
            'task-request-cancel',
            'task',
            null,
        );
        $cancelled = $service->cancel($cancelled, $principal, 'The request is no longer needed.');
        $this->assertSame(McpApproval::STATUS_CANCELLED, $cancelled->status);
        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approval_cancelled',
            'status' => McpApproval::STATUS_CANCELLED,
        ]);
    }

    public function test_expired_pending_approvals_are_marked_and_audited(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $approval = $this->requestApproval($principal);
        $this->travel(16)->minutes();

        $expired = app(McpApprovalService::class)->expirePending();

        $this->assertSame(1, $expired);
        $this->assertSame(McpApproval::STATUS_EXPIRED, $approval->fresh()->status);
        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approval_expired',
            'approval_id' => $approval->id,
            'status' => McpApproval::STATUS_EXPIRED,
        ]);
    }

    public function test_permission_revoked_after_execution_claim_blocks_domain_callback(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $approval = app(McpApprovalService::class)->approve(
            $this->requestApproval($principal),
            $reviewer,
            'Approved for execution.',
        );
        $role = $this->assistantRole($principal);

        McpExecutionAudit::created(function (McpExecutionAudit $audit) use ($role): void {
            if ($audit->event_type === 'approved_operation_execution') {
                $role->forceFill(['access' => []])->save();
            }
        });

        $calls = 0;
        try {
            app(McpApprovalService::class)->executeApproved($approval, $principal, function () use (&$calls): array {
                $calls++;

                return ['unexpected' => true];
            });
            $this->fail('The second authorization check must block the domain callback.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('execution', $exception->errors());
        }

        $this->assertSame(0, $calls);
        $this->assertSame(McpApproval::STATUS_EXECUTION_BLOCKED, $approval->fresh()->status);
        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approved_operation_execution',
            'approval_id' => $approval->id,
            'status' => 'failed',
            'error_code' => 'authorization_revoked_before_execution',
        ]);
    }

    public function test_uncertain_execution_is_never_automatically_retried(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $service = app(McpApprovalService::class);
        $approval = $service->approve($this->requestApproval($principal), $reviewer, 'Approved for execution.');
        $calls = 0;

        try {
            $service->executeApproved($approval, $principal, function () use (&$calls): array {
                $calls++;
                throw new RuntimeException('Simulated unconfirmed domain outcome.');
            });
            $this->fail('An unconfirmed execution outcome must not be reported as successful.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('execution', $exception->errors());
        }

        $this->assertSame(1, $calls);
        $this->assertSame(McpApproval::STATUS_EXECUTION_UNCERTAIN, $approval->fresh()->status);
        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approved_operation_execution',
            'approval_id' => $approval->id,
            'status' => 'uncertain',
            'error_code' => 'execution_outcome_uncertain',
        ]);

        $this->expectException(ValidationException::class);
        $service->executeApproved($approval->fresh(), $principal, function () use (&$calls): array {
            $calls++;

            return ['should_not_run' => true];
        });
    }

    public function test_stale_execution_claims_are_marked_uncertain_and_their_audit_is_updated(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $approval = $this->requestApproval($principal);
        $audit = app(McpExecutionAuditRecorder::class)->recordEvent('approved_operation_execution', [
            'ai_principal_id' => $principal->id,
            'approval_id' => $approval->id,
            'tool_name' => 'create_task',
            'tool_version' => '1.0.0',
            'payload_hash' => $approval->payload_hash,
            'status' => 'started',
            'started_at' => now()->subMinutes(10),
            'completed_at' => null,
        ]);
        DB::table('mcp_approvals')->where('id', $approval->id)->update([
            'status' => McpApproval::STATUS_EXECUTING,
            'execution_audit_id' => $audit->id,
            'updated_at' => now()->subMinutes(10),
        ]);

        $marked = app(McpApprovalService::class)->markStaleExecutionsUncertain();

        $this->assertSame(1, $marked);
        $this->assertSame(McpApproval::STATUS_EXECUTION_UNCERTAIN, $approval->fresh()->status);
        $this->assertDatabaseHas('mcp_execution_audits', [
            'id' => $audit->id,
            'status' => 'uncertain',
            'error_code' => 'execution_interrupted',
        ]);
    }

    public function test_concurrent_execution_claim_is_rejected_without_invoking_callback(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $approval = app(McpApprovalService::class)->approve(
            $this->requestApproval($principal),
            $reviewer,
            'Approved for execution.',
        );
        DB::table('mcp_approvals')->where('id', $approval->id)->update([
            'status' => McpApproval::STATUS_EXECUTING,
        ]);

        $calls = 0;
        try {
            app(McpApprovalService::class)->executeApproved($approval, $principal, function () use (&$calls): array {
                $calls++;

                return ['unexpected' => true];
            });
            $this->fail('A second worker must not execute an already claimed operation.');
        } catch (ValidationException) {
            $this->assertSame(0, $calls);
        }
    }

    public function test_approval_service_rejects_tools_not_marked_as_approval_required_writes(): void
    {
        config(['mcp.tool_permissions.create_task.operation_type' => 'read', 'mcp.tool_permissions.create_task.requires_approval' => false]);
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);

        $this->expectException(LogicException::class);
        $this->requestApproval($principal);
    }

    public function test_permission_matrix_version_change_invalidates_existing_approval(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $reviewer = $this->reviewer();
        $approval = $this->requestApproval($principal);
        config(['mcp.permission_matrix_version' => '2.0.0']);

        $this->expectException(ValidationException::class);
        app(McpApprovalService::class)->approve($approval, $reviewer, 'The old tool contract must not be approved.');
    }

    public function test_approval_rejects_sensitive_target_identifiers(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);

        $this->expectException(ValidationException::class);
        app(McpApprovalService::class)->request(
            $principal,
            'create_task',
            ['title' => 'Synthetic task'],
            ['action' => 'Create task', 'title' => 'Synthetic task'],
            'task-request-sensitive-target',
            'task',
            '1234567890123',
        );
    }

    public function test_approval_preview_rejects_sensitive_field_names(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);

        $this->expectException(ValidationException::class);
        app(McpApprovalService::class)->request(
            $principal,
            'create_task',
            ['title' => 'Synthetic task'],
            ['action' => 'Create task', 'customer_email' => 'private@example.test'],
            'task-request-sensitive-preview',
            'task',
            null,
        );
    }

    public function test_approval_and_audit_views_require_explicit_human_permissions(): void
    {
        $unassigned = User::factory()->create();
        $access = app(McpApprovalAccess::class);

        $this->assertFalse($access->canViewApprovals($unassigned));
        $this->assertFalse($access->canReview($unassigned));
        $this->assertFalse($access->canViewExecutionAudit($unassigned));

        $reviewer = $this->reviewer();

        $this->assertTrue($access->canViewApprovals($reviewer));
        $this->assertTrue($access->canReview($reviewer));
        $this->assertTrue($access->canViewExecutionAudit($reviewer));
    }

    public function test_audit_and_approval_identity_fields_are_immutable_and_records_cannot_be_deleted(): void
    {
        $principal = $this->principal();
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        $approval = $this->requestApproval($principal);
        $audit = McpExecutionAudit::query()->firstOrFail();

        try {
            $approval->forceFill(['tool_name' => 'tampered_tool'])->save();
            $this->fail('Approval identity fields must be immutable.');
        } catch (LogicException) {
            $this->assertSame('create_task', $approval->fresh()->tool_name);
        }

        try {
            $audit->forceFill(['tool_name' => 'tampered_tool'])->save();
            $this->fail('Execution audit identity fields must be immutable.');
        } catch (LogicException) {
            $this->assertNotSame('tampered_tool', $audit->fresh()->tool_name);
        }

        try {
            $approval->forceFill(['status' => McpApproval::STATUS_EXECUTED])->save();
            $this->fail('The approval lifecycle must reject an invalid direct transition.');
        } catch (LogicException) {
            $this->assertSame(McpApproval::STATUS_PENDING, $approval->fresh()->status);
        }

        try {
            $approval->delete();
            $this->fail('Approval history must not be deletable through the application.');
        } catch (LogicException) {
            $this->assertDatabaseHas('mcp_approvals', ['id' => $approval->id]);
        }

        $this->expectException(LogicException::class);
        $audit->delete();
    }

    public function test_payload_canonicalization_sorts_associative_keys_but_preserves_list_order(): void
    {
        $canonicalizer = app(McpPayloadCanonicalizer::class);

        $first = $canonicalizer->hash('create_task', '1.0.0', 'task', null, [
            'title' => 'Example',
            'metadata' => ['b' => 2, 'a' => 1],
            'labels' => ['urgent', 'management'],
        ]);
        $same = $canonicalizer->hash('create_task', '1.0.0', 'task', null, [
            'labels' => ['urgent', 'management'],
            'metadata' => ['a' => 1, 'b' => 2],
            'title' => 'Example',
        ]);
        $differentListOrder = $canonicalizer->hash('create_task', '1.0.0', 'task', null, [
            'title' => 'Example',
            'metadata' => ['a' => 1, 'b' => 2],
            'labels' => ['management', 'urgent'],
        ]);

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $differentListOrder);
    }

    public function test_filament_approval_and_execution_audit_pages_render_for_an_authorized_human(): void
    {
        $reviewer = $this->reviewer();
        $this->withoutVite();

        $this->actingAs($reviewer)->get('/admin/mcp-approvals')->assertOk();
        $this->actingAs($reviewer)->get('/admin/mcp-execution-audits')->assertOk();
    }

    private function principal(array $permissions = ['Tasks' => ['task.create']]): User
    {
        $principal = User::factory()->create([
            'name' => 'AI Assistant',
            'email' => 'chatgpt@edventure.ro',
        ]);

        $role = HexaRole::query()->create([
            'name' => 'Assistant',
            'created_by_name' => 'Test',
            'access' => $permissions,
            'guard' => 'web',
        ]);
        DB::table('hexa_role_user')->insert([
            'user_id' => $principal->id,
            'role_id' => $role->getKey(),
        ]);

        return $principal;
    }

    private function assistantRole(User $principal): HexaRole
    {
        return HexaRole::query()
            ->join('hexa_role_user', 'hexa_role_user.role_id', '=', 'hexa_roles.id')
            ->where('hexa_role_user.user_id', $principal->id)
            ->firstOrFail(['hexa_roles.*']);
    }

    private function reviewer(): User
    {
        $reviewer = User::factory()->create(['name' => 'MCP Reviewer']);
        $role = HexaRole::query()->create([
            'name' => 'MCP Approval Reviewer',
            'created_by_name' => 'Test',
            'access' => [
                'MCP' => [
                    'mcp_approval.index',
                    'mcp_approval.approve',
                    'mcp_execution_audit.index',
                ],
            ],
            'guard' => 'web',
        ]);
        DB::table('hexa_role_user')->insert([
            'user_id' => $reviewer->id,
            'role_id' => $role->getKey(),
        ]);

        return $reviewer;
    }

    private function requestApproval(User $principal): McpApproval
    {
        return app(McpApprovalService::class)->request(
            $principal,
            'create_task',
            ['title' => 'Synthetic MCP task', 'description' => 'A test-only operation'],
            ['action' => 'Create task', 'title' => 'Synthetic MCP task'],
            'task-request-'.uniqid('', true),
            'task',
            null,
        );
    }
}

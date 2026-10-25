<?php

namespace App\Services\Finance;

use App\Models\IssuedInvoiceArtifact;
use App\Models\IssuedInvoiceEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Retention is fail-closed until a qualified owner records the applicable policy reference. */
class IssuedInvoiceArtifactRetentionService
{
    public function placeLegalHold(int $artifactId, User $actor, string $reason): IssuedInvoiceArtifact
    {
        Gate::forUser($actor)->authorize('customer_invoice.retention_manage');
        if (blank($reason)) {
            throw ValidationException::withMessages(['legal_hold_reason' => 'A reason is required to place a legal hold.']);
        }

        return DB::transaction(function () use ($artifactId, $actor, $reason): IssuedInvoiceArtifact {
            $artifact = IssuedInvoiceArtifact::query()->with('issuedInvoice')->lockForUpdate()->findOrFail($artifactId);
            if ($artifact->legal_hold) {
                return $artifact;
            }
            $artifact->forceFill(['legal_hold' => true, 'legal_hold_reason' => trim($reason), 'legal_hold_at' => now(), 'legal_hold_by' => $actor->getKey()])->save();
            $this->event($artifact, $actor, 'artifact_legal_hold_placed', ['artifact_id' => $artifact->getKey(), 'artifact_type' => $artifact->artifact_type, 'reason_sha256' => hash('sha256', trim($reason))]);

            return $artifact->refresh();
        });
    }

    public function releaseLegalHold(int $artifactId, User $actor, string $reason): IssuedInvoiceArtifact
    {
        Gate::forUser($actor)->authorize('customer_invoice.retention_manage');
        if (blank($reason)) {
            throw ValidationException::withMessages(['legal_hold_reason' => 'A reason is required to release a legal hold.']);
        }

        return DB::transaction(function () use ($artifactId, $actor, $reason): IssuedInvoiceArtifact {
            $artifact = IssuedInvoiceArtifact::query()->with('issuedInvoice')->lockForUpdate()->findOrFail($artifactId);
            if (! $artifact->legal_hold) {
                throw ValidationException::withMessages(['legal_hold' => 'The artifact has no active legal hold.']);
            }
            $artifact->forceFill(['legal_hold' => false, 'legal_hold_reason' => null, 'legal_hold_at' => null, 'legal_hold_by' => null])->save();
            $this->event($artifact, $actor, 'artifact_legal_hold_released', ['artifact_id' => $artifact->getKey(), 'artifact_type' => $artifact->artifact_type, 'reason_sha256' => hash('sha256', trim($reason))]);

            return $artifact->refresh();
        });
    }

    public function recordApprovedPolicy(int $artifactId, User $actor, string $policyReference): IssuedInvoiceArtifact
    {
        Gate::forUser($actor)->authorize('customer_invoice.retention_review');
        if (blank($policyReference)) {
            throw ValidationException::withMessages(['retention_policy_reference' => 'An approved policy reference is required.']);
        }

        return DB::transaction(function () use ($artifactId, $actor, $policyReference): IssuedInvoiceArtifact {
            $artifact = IssuedInvoiceArtifact::query()->with('issuedInvoice')->lockForUpdate()->findOrFail($artifactId);
            $artifact->forceFill([
                'retention_review_status' => 'approved', 'retention_reviewed_at' => now(),
                'retention_reviewed_by' => $actor->getKey(), 'retention_policy_reference' => trim($policyReference),
            ])->save();
            $this->event($artifact, $actor, 'artifact_retention_policy_reviewed', [
                'artifact_id' => $artifact->getKey(), 'artifact_type' => $artifact->artifact_type,
                'policy_reference' => trim($policyReference),
            ]);

            return $artifact->refresh();
        });
    }

    private function event(IssuedInvoiceArtifact $artifact, User $actor, string $type, array $metadata): void
    {
        IssuedInvoiceEvent::query()->create([
            'issued_invoice_id' => $artifact->issued_invoice_id, 'event_type' => $type,
            'actor_id' => $actor->getKey(), 'safe_metadata' => $metadata, 'created_at' => now(),
        ]);
    }
}

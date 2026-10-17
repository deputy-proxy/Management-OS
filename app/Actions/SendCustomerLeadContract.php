<?php

namespace App\Actions;

use App\Jobs\SendCustomerLeadContractEmail;
use App\Models\CustomerLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendCustomerLeadContract
{
    public function handle(CustomerLead $customerLead): CustomerLead
    {
        $leadId = $customerLead->getKey();

        DB::transaction(function () use ($leadId): void {
            $lead = CustomerLead::query()->whereKey($leadId)->lockForUpdate()->firstOrFail();

            if (in_array($lead->contract_send_status, ['queued', 'sent'], true)) {
                throw ValidationException::withMessages([
                    'contract_send_status' => 'This contract has already been sent or is waiting to be sent.',
                ]);
            }

            if (! $lead->email || ! filter_var($lead->email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email' => 'Add a valid email address to the customer lead before sending the contract.',
                ]);
            }

            if (! $lead->contract_document || ! Storage::disk('local')->exists($lead->contract_document)) {
                throw ValidationException::withMessages([
                    'contract_document' => 'Upload an existing contract PDF before sending it.',
                ]);
            }

            $lead->forceFill([
                'contract_send_status' => 'queued',
                'contract_send_error' => null,
                'contract_sent_at' => null,
            ])->save();
        });

        try {
            SendCustomerLeadContractEmail::dispatch($leadId)->afterCommit();
        } catch (Throwable $exception) {
            CustomerLead::withTrashed()->whereKey($leadId)->update([
                'contract_send_status' => 'failed',
                'contract_sent_at' => null,
                'contract_send_error' => mb_substr($exception->getMessage(), 0, 4000),
            ]);

            throw $exception;
        }

        return $customerLead->fresh();
    }
}
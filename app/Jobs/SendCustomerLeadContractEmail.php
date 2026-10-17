<?php

namespace App\Jobs;

use App\Mail\CustomerLeadContractMail;
use App\Models\CustomerLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendCustomerLeadContractEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public int $customerLeadId) {}

    public function handle(): void
    {
        $lead = CustomerLead::query()->findOrFail($this->customerLeadId);

        if ($lead->contract_send_status !== 'queued') {
            return;
        }

        if (! $lead->email || ! filter_var($lead->email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('The customer lead does not have a valid email address.');
        }

        if (! $lead->contract_document || ! Storage::disk('local')->exists($lead->contract_document)) {
            throw new \RuntimeException('The contract PDF is missing from private storage.');
        }

        Mail::to($lead->email)->send(new CustomerLeadContractMail($lead));

        $lead->forceFill([
            'contract_send_status' => 'sent',
            'contract_sent_at' => now(),
            'contract_send_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        CustomerLead::withTrashed()->whereKey($this->customerLeadId)->update([
            'contract_send_status' => 'failed',
            'contract_sent_at' => null,
            'contract_send_error' => mb_substr($exception->getMessage(), 0, 4000),
        ]);
    }
}
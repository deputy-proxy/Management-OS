<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertCustomerLead
{
    /**
     * Convert a lead only when the operator confirms that the uploaded contract is signed.
     *
     * @param array{
     *     customer_type: string,
     *     cnp_cui: string,
     *     address: string,
     *     contract_code: string,
     *     start_date: string,
     *     end_date: string,
     *     document: string,
     *     confirm_signed: bool
     * } $data
     */
    public function handle(CustomerLead $lead, array $data): Customer
    {
        if (! ($data['confirm_signed'] ?? false)) {
            throw ValidationException::withMessages([
                'confirm_signed' => 'Confirm that the attached contract has been signed before converting this lead.',
            ]);
        }

        return DB::transaction(function () use ($lead, $data): Customer {
            $lockedLead = CustomerLead::query()
                ->whereKey($lead->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLead->converted_customer_id || $lockedLead->converted_at || $lockedLead->status === 'converted') {
                throw ValidationException::withMessages([
                    'record' => 'This lead has already been converted into a customer.',
                ]);
            }

            $customerByIdentity = Customer::withTrashed()
                ->where('cnp_cui', $data['cnp_cui'])
                ->lockForUpdate()
                ->first();

            $customerByEmail = Customer::withTrashed()
                ->where('email', $lockedLead->email)
                ->lockForUpdate()
                ->first();

            if ($customerByIdentity && $customerByEmail
                && $customerByIdentity->getKey() !== $customerByEmail->getKey()) {
                throw ValidationException::withMessages([
                    'cnp_cui' => 'The email and CNP/CUI match different existing customers. Reconcile the customer records before converting this lead.',
                ]);
            }

            $customer = $customerByIdentity ?? $customerByEmail;

            if ($customer?->trashed()) {
                throw ValidationException::withMessages([
                    'cnp_cui' => 'A soft-deleted customer matches this identity. Restore or reconcile that customer before converting this lead.',
                ]);
            }

            if ($customer && (
                $customer->cnp_cui !== $data['cnp_cui']
                || $customer->email !== $lockedLead->email
            )) {
                throw ValidationException::withMessages([
                    'cnp_cui' => 'An existing customer matches this email or CNP/CUI but has conflicting identity details. Reconcile the customer record before converting this lead.',
                ]);
            }

            if (! $customer) {
                $customer = Customer::query()->create([
                    'name' => $lockedLead->full_name,
                    'customer_type' => $data['customer_type'],
                    'cnp_cui' => $data['cnp_cui'],
                    'address' => $data['address'],
                    'email' => $lockedLead->email,
                    'phone' => $lockedLead->phone,
                ]);
            } elseif (blank($customer->phone) && filled($lockedLead->phone)) {
                $customer->forceFill(['phone' => $lockedLead->phone])->save();
            }

            CustomerContract::query()->create([
                'customer_id' => $customer->getKey(),
                'source_customer_lead_id' => $lockedLead->getKey(),
                'contract_code' => $data['contract_code'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'document' => $data['document'],
                'signed_at' => now(),
            ]);

            $lockedLead->forceFill([
                'status' => 'converted',
                'converted_customer_id' => $customer->getKey(),
                'converted_at' => now(),
            ])->save();

            return $customer;
        });
    }
}
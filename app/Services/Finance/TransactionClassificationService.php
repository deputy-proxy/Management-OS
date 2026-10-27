<?php

namespace App\Services\Finance;

class TransactionClassificationService
{
    /** @return array{code: string, label: string, status: string, direction: ?string} */
    public function classify(?string $legacyType, ?string $direction): array
    {
        $type = strtolower(trim((string) $legacyType));
        $direction = strtolower(trim((string) $direction));
        if (! in_array($type, ['customer', 'vendor', 'tax', 'other'], true) || ! in_array($direction, ['money_in', 'money_out'], true)) {
            return [
                'code' => 'unclassified_legacy',
                'label' => 'Unclassified legacy / unresolved',
                'status' => 'unclassified',
                'direction' => in_array($direction, ['money_in', 'money_out'], true) ? $direction : null,
            ];
        }

        $labels = [
            'customer_money_in' => 'Customer-related money in',
            'customer_money_out' => 'Customer-related money out',
            'vendor_money_in' => 'Vendor-related money in',
            'vendor_money_out' => 'Vendor-related money out',
            'tax_money_in' => 'Tax-related money in',
            'tax_money_out' => 'Tax-related money out',
            'other_money_in' => 'Other money in',
            'other_money_out' => 'Other money out',
        ];
        $code = $type.'_'.$direction;

        return [
            'code' => $code,
            'label' => $labels[$code],
            'status' => 'classified',
            'direction' => $direction,
        ];
    }
}

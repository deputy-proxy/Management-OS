<?php

/*
| Financial normalization is opt-in by exact field mapping. A null source unit,
| empty value map, or null semantic rule intentionally quarantines rows rather
| than inferring meaning from the current values. Approve mappings only after
| source evidence and the financial data contract have been reviewed.
*/

return [
    'batch_size' => 200,

    'tables' => [
        'transactions' => [
            'amounts' => [
                ['source' => 'amount', 'target' => 'amount_decimal', 'unit' => null, 'non_negative' => true],
            ],
            'currencies' => [
                ['source' => null, 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [
                ['source' => 'transaction_type', 'target' => 'transaction_type_code', 'map' => []],
            ],
            'invoice_semantics' => null,
        ],
        'customer_invoices' => [
            'amounts' => [
                ['source' => 'amount_without_vat', 'target' => 'amount_without_vat_decimal', 'unit' => null],
                ['source' => 'vat', 'target' => 'vat_decimal', 'unit' => null],
            ],
            'currencies' => [
                ['source' => null, 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [
                ['source' => 'status', 'target' => 'status_code', 'map' => []],
            ],
            'invoice_semantics' => ['rule' => null, 'fields' => ['amount_without_vat', 'vat']],
        ],
        'vendor_invoices' => [
            'amounts' => [
                ['source' => null, 'target' => 'amount_decimal', 'unit' => null, 'reason' => 'Canonical payable-total source field is not approved.'],
                ['source' => 'amount_without_vat', 'target' => 'amount_without_vat_decimal', 'unit' => null],
                ['source' => 'vat', 'target' => 'vat_decimal', 'unit' => null],
            ],
            'currencies' => [
                ['source' => 'currency', 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [
                ['source' => 'status', 'target' => 'status_code', 'map' => []],
            ],
            'invoice_semantics' => ['rule' => null, 'fields' => ['amount', 'amount_without_vat', 'vat']],
        ],
        'taxes' => [
            'amounts' => [
                ['source' => 'amount', 'target' => 'amount_decimal', 'unit' => null],
            ],
            'currencies' => [
                ['source' => null, 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [
                ['source' => 'status', 'target' => 'status_code', 'map' => []],
            ],
            'invoice_semantics' => null,
        ],
        'financial_account_balances' => [
            'amounts' => [
                ['source' => 'current_balance', 'target' => 'current_balance_decimal', 'unit' => null],
            ],
            'currencies' => [
                ['source' => 'currency', 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [],
            'invoice_semantics' => null,
        ],
        'product_resources' => [
            'amounts' => [
                ['source' => 'amount', 'target' => 'amount_decimal', 'unit' => null],
            ],
            'currencies' => [
                ['source' => null, 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [],
            'invoice_semantics' => null,
        ],
        'product_costs' => [
            'amounts' => [
                ['source' => 'resource_amount', 'target' => 'resource_amount_decimal', 'unit' => null],
                ['source' => 'allocated_amount', 'target' => 'allocated_amount_decimal', 'unit' => null],
            ],
            'currencies' => [
                ['source' => null, 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [],
            'invoice_semantics' => null,
        ],
        'product_revenues' => [
            'amounts' => [
                ['source' => 'amount', 'target' => 'amount_decimal', 'unit' => null],
            ],
            'currencies' => [
                ['source' => null, 'target' => 'currency_code', 'map' => []],
            ],
            'classifications' => [],
            'invoice_semantics' => null,
        ],
    ],
];

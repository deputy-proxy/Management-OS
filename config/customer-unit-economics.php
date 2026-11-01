<?php

return [
    'policy_version' => 'operational-cac-ltv-framework-v1',
    'timezone' => 'Europe/Bucharest',
    'acquisition_event' => 'verified_conversion',

    // No customer-level attribution or deduplication policy has been approved.
    'attribution_model' => env('CUSTOMER_UNIT_ECONOMICS_ATTRIBUTION_MODEL', 'unapproved'),
    'deduplication' => env('CUSTOMER_UNIT_ECONOMICS_DEDUPLICATION_POLICY', 'unapproved'),

    // These calculation inputs remain disabled until their business owners approve them.
    'cac_basis' => env('CUSTOMER_UNIT_ECONOMICS_CAC_BASIS', 'unapproved'),
    'revenue_basis' => env('CUSTOMER_UNIT_ECONOMICS_REVENUE_BASIS', 'unapproved'),
    'ltv_window' => env('CUSTOMER_UNIT_ECONOMICS_LTV_WINDOW', 'unapproved'),
    'retention_basis' => env('CUSTOMER_UNIT_ECONOMICS_RETENTION_BASIS', 'unapproved'),
    'retention_policy_version' => env('CUSTOMER_UNIT_ECONOMICS_RETENTION_POLICY_VERSION', 'unapproved'),

    'supported_attribution_model' => 'first_touch_lead_snapshot',
    'supported_deduplication' => 'earliest_verified_conversion',
    'supported_cac_basis' => 'source_category_matched_entries',
    'supported_revenue_basis' => 'canonical_product_revenue',
    'supported_ltv_window' => 'monthly_rows_from_conversion_date',
    'supported_retention_basis' => 'latest_explicit_observation',

    'retention_states' => ['retained', 'churned', 'reactivated', 'unknown'],
    'shared_cost_treatment' => 'Unattributed/shared costs are excluded and surfaced; no allocation is inferred.',
    'revenue_semantics' => 'Operational product revenue only; not invoiced totals, collected cash, recognized revenue, or profit.',
    'currency' => 'Currencies remain separate; no foreign-exchange conversion.',
    'missing_data' => 'Missing attribution, customer links, cost coverage, retention observations, and revenue coverage remain explicit; missing values are never zero.',
];

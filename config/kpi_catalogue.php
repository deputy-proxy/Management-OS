<?php

use App\Models\CompetitorOfferObservation;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\Task;
use App\Services\KpiAggregationService;

return [
    'version' => 'v1',
    'approval_status' => 'provisional',
    'timezone' => env('KPI_REPORTING_TIMEZONE', 'Europe/Bucharest'),
    'dimensions' => [],
    'definitions' => [
        [
            'key' => 'customer_record_count', 'label' => 'Customer records', 'domain' => 'customer',
            'calculation_type' => 'customer_record_count', 'calculation_service' => KpiAggregationService::class,
            'source_service' => Customer::class, 'source' => 'customers', 'unit' => 'records', 'dimensions' => [],
            'supported_period' => ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false],
            'date_basis' => 'as_of', 'permission' => 'customer.index', 'missing_data' => 'unavailable_not_zero',
            'formula' => 'COUNT(customers.id) WHERE customers.deleted_at IS NULL',
            'description' => 'Count non-soft-deleted customer records. This is not a count of active or paying customers.',
        ],
        [
            'key' => 'unconverted_lead_count', 'label' => 'Unconverted lead records', 'domain' => 'sales',
            'calculation_type' => 'unconverted_lead_count', 'calculation_service' => KpiAggregationService::class,
            'source_service' => CustomerLead::class, 'source' => 'customer_leads', 'unit' => 'records', 'dimensions' => [],
            'supported_period' => ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false],
            'date_basis' => 'as_of', 'permission' => 'customer_lead.index', 'missing_data' => 'unavailable_not_zero',
            'formula' => 'COUNT(customer_leads.id) WHERE deleted_at IS NULL AND converted_customer_id IS NULL',
            'description' => 'Count non-soft-deleted lead records with no converted customer link, regardless of stored lead status.',
        ],
        [
            'key' => 'unknown_lead_source_count', 'label' => 'Leads with unknown source', 'domain' => 'sales',
            'calculation_type' => 'unknown_lead_source_count', 'calculation_service' => KpiAggregationService::class,
            'source_service' => CustomerLead::class, 'source' => 'customer_leads.source_category', 'unit' => 'records', 'dimensions' => [],
            'supported_period' => ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false],
            'date_basis' => 'as_of', 'permission' => 'customer_lead.index', 'missing_data' => 'unknown_is_explicit_category',
            'formula' => "COUNT(customer_leads.id) WHERE deleted_at IS NULL AND source_category = 'unknown'",
            'description' => 'Count non-soft-deleted leads whose controlled source category is unknown. Do not infer a category from legacy free text.',
        ],
        [
            'key' => 'open_task_count', 'label' => 'Open task records', 'domain' => 'operations',
            'calculation_type' => 'open_task_count', 'calculation_service' => KpiAggregationService::class,
            'source_service' => Task::class, 'source' => 'tasks', 'unit' => 'records', 'dimensions' => [],
            'supported_period' => ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false],
            'date_basis' => 'as_of', 'permission' => 'task.index', 'missing_data' => 'unavailable_not_zero',
            'formula' => "COUNT(tasks.id) WHERE status != 'done' AND (assigned_to = viewer OR created_by = viewer), unless task.view_all",
            'description' => 'Count visible tasks whose status is not exactly done. Visibility follows assigned-to/created-by scope unless task.view_all is granted.',
        ],
        [
            'key' => 'overdue_task_count', 'label' => 'Overdue task records', 'domain' => 'operations',
            'calculation_type' => 'overdue_task_count', 'calculation_service' => KpiAggregationService::class,
            'source_service' => Task::class, 'source' => 'tasks.due_date,status', 'unit' => 'records', 'dimensions' => [],
            'supported_period' => ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false],
            'date_basis' => 'local_date_as_of', 'permission' => 'task.index', 'missing_data' => 'unavailable_not_zero',
            'formula' => "COUNT(tasks.id) WHERE status != 'done' AND due_date < reporting_local_date AND visible_to_viewer",
            'description' => 'Count visible tasks whose status is not exactly done and whose due date is strictly before the reporting date. Tasks due today are not overdue.',
        ],
        [
            'key' => 'competitor_offer_snapshot_count', 'label' => 'Recorded competitor offer snapshots', 'domain' => 'market_intelligence',
            'calculation_type' => 'competitor_offer_snapshot_count', 'calculation_service' => KpiAggregationService::class,
            'source_service' => CompetitorOfferObservation::class, 'source' => 'competitor_offer_observations', 'unit' => 'snapshots', 'dimensions' => [],
            'supported_period' => ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false],
            'date_basis' => 'as_of', 'permission' => 'competitor_offer.index', 'missing_data' => 'unavailable_not_zero',
            'formula' => 'COUNT(competitor_offer_observations.id), including append-only history for later soft-deleted offers',
            'description' => 'Count append-only competitor offer observations, including historical snapshots for offers later soft-deleted. This is inventory coverage, not market share or source accuracy.',
        ],
    ],
];

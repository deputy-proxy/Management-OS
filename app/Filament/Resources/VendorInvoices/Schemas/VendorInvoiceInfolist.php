<?php

namespace App\Filament\Resources\VendorInvoices\Schemas;

use App\Models\VendorInvoice;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VendorInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('vendor_id'),
                TextEntry::make('vendor_contract_id'),
                TextEntry::make('worker_engagement_id')->label('PFA contractor engagement'),
                TextEntry::make('workflow_status')->badge()->label('Contractor workflow'),
                TextEntry::make('tax_treatment_status')->badge()->label('Tax treatment'),
                TextEntry::make('submitted_at')->dateTime(),
                TextEntry::make('reviewed_at')->dateTime(),
                TextEntry::make('review_reason'),
                TextEntry::make('payable_on')->date(),
                TextEntry::make('corrects_vendor_invoice_id')->label('Corrects invoice ID'),
                TextEntry::make('correction_reason'),
                TextEntry::make('reversal_reason'),
                TextEntry::make('paymentAllocations_count')->label('Payment allocation records')->state(fn (VendorInvoice $record): int => $record->paymentAllocations()->count()),
                TextEntry::make('lifecycleEvents_count')->label('Lifecycle events')->state(fn (VendorInvoice $record): int => $record->lifecycleEvents()->count()),
                TextEntry::make('amount'),
                TextEntry::make('vat'),
                TextEntry::make('currency'),
                TextEntry::make('month'),
                TextEntry::make('status'),
                TextEntry::make('invoice'),
                TextEntry::make('invoice_number'),
                TextEntry::make('identifier'),
            ]);
    }
}

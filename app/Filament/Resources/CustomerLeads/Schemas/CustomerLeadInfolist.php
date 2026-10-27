<?php

namespace App\Filament\Resources\CustomerLeads\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerLeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('first_name'),
                TextEntry::make('middle_name'),
                TextEntry::make('last_name'),
                TextEntry::make('full_name'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('source_category')->label('Source category')->badge(),
                TextEntry::make('source'),
                TextEntry::make('campaign_name'),
                TextEntry::make('referral_partner'),
                TextEntry::make('attribution_policy_version')->label('Attribution policy version'),
                TextEntry::make('source_captured_at')->dateTime(),
                TextEntry::make('topic_interest'),
                TextEntry::make('product_interest'),
                TextEntry::make('status'),
                TextEntry::make('last_contacted_at')->dateTime(),
                TextEntry::make('contract_document')->label('Contract PDF'),
                TextEntry::make('contract_send_status')->label('Contract delivery status'),
                TextEntry::make('contract_sent_at')->dateTime(),
                TextEntry::make('contract_send_error')->label('Last delivery error')->placeholder('None'),
            ]);
    }
}

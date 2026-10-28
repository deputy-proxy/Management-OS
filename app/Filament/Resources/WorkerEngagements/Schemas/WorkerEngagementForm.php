<?php

namespace App\Filament\Resources\WorkerEngagements\Schemas;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Models\WorkerEngagement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkerEngagementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Arrangement classification')
                ->description('Classification is explicit. A login, vendor record, invoice, or payslip does not determine legal worker type.')
                ->schema([
                    Select::make('engagement_type')->options(WorkerEngagement::TYPES)->required()->native(false),
                    Select::make('status')->options(WorkerEngagement::STATUSES)->required()->default('unresolved')->native(false),
                    TextInput::make('source_reference')
                        ->label('Source document/reference')
                        ->maxLength(255)
                        ->helperText('Reference only. Do not enter personal identifiers or sensitive document contents.'),
                ])->columns(2),
            Section::make('Linked records')
                ->description('Link at most one employee or vendor and a matching contract. Unknown arrangements may remain unlinked.')
                ->schema([
                    Select::make('employee_id')
                        ->label('Employee')
                        ->options(fn (): array => Employee::query()->orderBy('full_name')->pluck('full_name', 'id')->all())
                        ->searchable()->preload(),
                    Select::make('vendor_id')
                        ->label('Vendor / contracting party')
                        ->options(fn (): array => Vendor::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->preload(),
                    Select::make('employee_contract_id')
                        ->label('Employee contract')
                        ->options(fn (): array => EmployeeContract::query()->pluck('employee_id', 'id')
                            ->mapWithKeys(fn ($employeeId, $id): array => [
                                $id => 'Employee contract #'.$id.' · employee #'.$employeeId,
                            ])->all())
                        ->searchable()->preload(),
                    Select::make('vendor_contract_id')
                        ->label('Vendor contract')
                        ->options(fn (): array => VendorContract::query()->pluck('vendor_id', 'id')
                            ->mapWithKeys(fn ($vendorId, $id): array => [
                                $id => 'Vendor contract #'.$id.' · vendor #'.$vendorId,
                            ])->all())
                        ->searchable()->preload(),
                ])->columns(2),
            Section::make('Effective dates')->schema([
                DatePicker::make('start_date'),
                DatePicker::make('end_date')->afterOrEqual('start_date'),
            ])->columns(2),
        ]);
    }
}

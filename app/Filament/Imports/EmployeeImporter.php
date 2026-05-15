<?php

namespace App\Filament\Imports;

use App\Models\Employee;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class EmployeeImporter extends Importer
{
    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('full_name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required']),
            // ImportColumn::make('user')
            //     ->requiredMapping()
            //     ->relationship()
            //     ->rules(['required']),
            // ImportColumn::make('date_of_birth')
            //     ->rules(['date']),
        ];
    }

    public function resolveRecord(): Employee
    {
        return Employee::firstOrNew([
            'full_name' => $this->data['full_name'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your employee import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}

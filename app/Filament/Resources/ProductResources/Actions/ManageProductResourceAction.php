<?php

namespace App\Filament\Resources\ProductResources\Actions;

use App\Models\ProductResource;
use Filament\Actions\Action;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;

class ManageProductResourceAction extends Action
{
    protected string $amountField = 'amount';

    public static function getDefaultName(): ?string
    {
        return 'manage_product_resource';
    }

    public function amountField(string $field): static
    {
        $this->amountField = $field;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (Model $record) => $record->productResources()->exists()
                ? 'Product Resource'
                : 'Add as Product Resource'
            )
            ->icon(fn (Model $record) => $record->productResources()->exists()
                ? 'heroicon-o-cube'
                : 'heroicon-o-plus-circle'
            )
            ->color(fn (Model $record) => $record->productResources()->exists()
                ? 'info'
                : 'primary'
            )
            ->fillForm(function (Model $record) {
                $existing = $record->productResources()->first();

                if ($existing) {
                    return [
                        'name'                 => $existing->name,
                        'amount'               => $existing->amount,
                        'month'                => $existing->month,
                        'allocated_percentage' => $existing->allocated_percentage,
                    ];
                }

                return [
                    'name'   => $this->guessName($record),
                    'amount' => $record->{$this->amountField},
                    'month'  => $record->month,
                ];
            })
            ->form(function (Model $record) {
                $existing = $record->productResources()->exists();

                return [
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->disabled($existing),

                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->readOnly()
                        ->required(),

                    Forms\Components\DatePicker::make('month')
                        ->required()
                        ->disabled($existing),

                    Forms\Components\TextInput::make('allocated_percentage')
                        ->label('Allocated Percentage')
                        ->numeric()
                        ->readOnly()
                        ->dehydrated(false)
                        ->visible($existing),
                ];
            })
            ->modalHeading(fn (Model $record) => $record->productResources()->exists()
                ? 'Product Resource Details'
                : 'Add as Product Resource'
            )
            ->modalSubmitActionLabel('Save')
            ->modalFooterActions(fn (Action $action, Model $record) => $record->productResources()->exists()
                ? []
                : [$action->getModalSubmitAction()]
            )
            ->action(function (Model $record, array $data) {
                ProductResource::create([
                    'name'              => $data['name'],
                    'amount'            => $data['amount'],
                    'month'             => $data['month'],
                    'resourceable_type' => $record->getMorphClass(),
                    'resourceable_id'   => $record->id,
                ]);

                $this->success();
            })
            ->successNotificationTitle('Product resource created');
    }

    protected function guessName(Model $record): string
    {
        foreach (['name', 'title', 'label', 'identifier'] as $field) {
            if (isset($record->{$field})) {
                if($field ==='identifier'){
                    return $record->{$field};    
                }else{
                    return $record->{$field} . ($record->month ? ' — ' . $record->month->format('M Y') : '');
                }                
            }
        }

        return class_basename($record) . ' #' . $record->id;
    }
}
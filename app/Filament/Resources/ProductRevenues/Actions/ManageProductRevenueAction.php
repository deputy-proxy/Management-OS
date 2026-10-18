<?php

namespace App\Filament\Resources\ProductRevenues\Actions;

use App\Models\Product;
use App\Models\ProductRevenue;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

class ManageProductRevenueAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'manage_product_revenue';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->visible(fn (Model $record): bool => $record->productRevenue()->exists()
                ? hexa()->can('product_revenue.index')
                : hexa()->can('product_revenue.create')
            )
            ->authorize(fn (Model $record): bool => $record->productRevenue()->exists()
                ? hexa()->can('product_revenue.index')
                : hexa()->can('product_revenue.create')
            )
            ->label(fn (Model $record) => $record->productRevenue()->exists()
                ? 'Product Revenue'
                : 'Add as Product Revenue'
            )
            ->icon(fn (Model $record) => $record->productRevenue()->exists()
                ? 'heroicon-o-banknotes'
                : 'heroicon-o-plus-circle'
            )
            ->color(fn (Model $record) => $record->productRevenue()->exists()
                ? 'success'
                : 'primary'
            )
            ->fillForm(function (Model $record) {
                $existing = $record->productRevenue()->first();

                if ($existing) {
                    return [
                        'product_id' => $existing->product_id,
                        'month'      => $existing->month,
                        'amount'     => $existing->amount,
                    ];
                }

                return [
                    'month'  => $record->month,
                    'amount' => $record->amount_without_vat,
                ];
            })
            ->form(function (Model $record) {
                $existing = $record->productRevenue()->exists();

                return [
                    Forms\Components\Select::make('product_id')
                        ->label('Product')
                        ->options(fn () => Product::query()
                            ->get()
                            ->mapWithKeys(fn ($product) => [
                                $product->id => $product->title,
                            ])
                        )
                        ->searchable()
                        ->required()
                        ->disabled($existing),

                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->readOnly()
                        ->required(),

                    Forms\Components\DatePicker::make('month')
                        ->required()
                        ->disabled($existing),
                ];
            })
            ->modalHeading(fn (Model $record) => $record->productRevenue()->exists()
                ? 'Product Revenue Details'
                : 'Add as Product Revenue'
            )
            ->modalSubmitActionLabel('Save')
            ->modalFooterActions(fn (Action $action, Model $record) => $record->productRevenue()->exists()
                ? []
                : [$action->getModalSubmitAction()]
            )
            ->action(function (Model $record, array $data) {
                abort_unless(
                    ! $record->productRevenue()->exists() && hexa()->can('product_revenue.create'),
                    403,
                );

                ProductRevenue::create([
                    'product_id'          => $data['product_id'],
                    'customer_invoice_id' => $record->id,
                    'month'               => $data['month'],
                    'amount'              => $data['amount'],
                ]);

                $this->success();
            })
            ->successNotificationTitle('Product revenue created');
    }
}
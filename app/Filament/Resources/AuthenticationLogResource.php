<?php

namespace App\Filament\Resources;

use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Tapp\FilamentAuthenticationLog\Resources\AuthenticationLogResource as BaseResource;

class AuthenticationLogResource extends BaseResource
{
    use HasHexaLite;

    public function defineGates(): array
    {
        return [
            'auth-log.index' => __('Allows viewing the authentication logs'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('auth-log.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function table(Table $table): Table
    {
        parent::table($table);

        $locationColumn = TextColumn::make('location_display')
            ->label('Location')
            ->getStateUsing(function (Model $record) {
                $location = $record->getRawOriginal('location');

                if (!$location) {
                    return '-';
                }

                $data = json_decode($location, true);

                if (!$data) {
                    return '-';
                }

                if ($data['default'] ?? false) {
                    return 'Local / Unknown';
                }

                return implode(', ', array_filter([
                    $data['city'] ?? null,
                    $data['postal_code'] ?? null,
                    $data['state_name'] ?? null,                    
                    $data['country'] ?? null,
                ])) ?: '-';
            });

        return $table->columns([
            ...collect($table->getColumns())
                ->values()
                ->all(),
            $locationColumn,
        ]);
    }
}
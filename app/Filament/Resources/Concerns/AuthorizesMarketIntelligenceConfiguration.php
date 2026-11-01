<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesMarketIntelligenceConfiguration
{
    public function defineGates(): array
    {
        return [
            'market_intelligence_configuration.view' => __('Allows viewing market intelligence configuration'),
            'market_intelligence_configuration.manage' => __('Allows managing market intelligence configuration'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('market_intelligence_configuration.view') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('market_intelligence_configuration.manage') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('market_intelligence_configuration.manage') ? Response::allow() : Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('market_intelligence_configuration.manage') ? Response::allow() : Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('market_intelligence_configuration.manage') ? Response::allow() : Response::deny();
    }
}

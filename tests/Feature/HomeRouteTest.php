<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    public function test_root_url_uses_the_named_controller_backed_home_route(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertViewIs('welcome');

        $homeRoute = Route::getRoutes()->getByName('home');
        $this->assertNotNull($homeRoute);
        $this->assertSame('/', $homeRoute->uri());
        $this->assertSame(HomeController::class, $homeRoute->getControllerClass());
    }

    public function test_only_one_get_route_handles_the_root_url(): void
    {
        $rootGetRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === '/' && in_array('GET', $route->methods(), true));

        $this->assertCount(1, $rootGetRoutes);
    }
}

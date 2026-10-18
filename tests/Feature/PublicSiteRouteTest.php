<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicSiteRouteTest extends TestCase
{
    public function test_missing_public_page_views_return_not_found_instead_of_placeholder_text(): void
    {
        foreach ([
            '/about',
            '/contact',
            '/cariere',
            '/privacy',
            '/cookies',
            '/courses',
            '/courses/english-kids',
            '/courses/english-adults',
            '/courses/english',
            '/courses/kids',
            '/courses/corporate',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_unimplemented_blog_search_and_language_course_routes_are_not_registered(): void
    {
        foreach ([
            '/blog' => 'blog',
            '/search' => 'search',
            '/courses/german-kids' => 'courses.german-kids',
            '/courses/french-kids' => 'courses.french-kids',
            '/courses/japanese-kids' => 'courses.japanese-kids',
            '/courses/english-individual' => 'courses.english-individual',
            '/courses/english-conversation' => 'courses.english-conversation',
            '/courses/schools' => 'courses.schools',
        ] as $path => $name) {
            $this->get($path)->assertNotFound();
            $this->assertNull(Route::getRoutes()->getByName($name));
        }
    }

    public function test_homepage_remains_available_without_links_to_unimplemented_pages_or_search(): void
    {
        $this->get('/')->assertOk()->assertViewIs('welcome');

        $header = $this->blade("@include('components.header.desktop-header')");
        $header->assertDontSee('grve-search-modal');

        foreach ([
            '/about',
            '/contact',
            '/cariere',
            '/privacy',
            '/cookies',
            '/courses/english-kids',
            '/courses/english-adults',
            '/courses/german-kids',
            '/courses/french-kids',
            '/courses/japanese-kids',
            '/courses/corporate',
        ] as $path) {
            $header->assertDontSee('href="'.url($path).'"', false);
        }

        $menu = $this->blade("@include('components.navigation.main-menu')");
        $menu->assertSee('Cursuri Germană Copii', false);
        $menu->assertDontSee('href="'.url('/courses/german-kids').'"', false);

        $footer = $this->blade("@include('components.footer.footer-content') @include('components.footer.copyright')");
        foreach (['/about', '/contact', '/cariere', '/privacy', '/cookies', '/blog'] as $path) {
            $footer->assertDontSee('href="'.url($path).'"', false);
        }

        $responsiveMenu = $this->blade("@include('components.navigation.responsive-menu')");
        $responsiveMenu->assertSee('href="'.url('/').'"', false);
        $responsiveMenu->assertDontSee('href="'.url('/contact').'"', false);
    }
}


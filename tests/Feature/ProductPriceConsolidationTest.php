<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Course;
use App\Models\CoursePrice;
use App\Models\Event;
use App\Models\EventPrice;
use App\Models\ProductPrice;
use App\Models\Workshop;
use App\Models\WorkshopPrice;
use App\Services\ProductPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPriceConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_price_rows_are_copied_without_losing_amount_currency_validity_or_soft_delete_state(): void
    {
        $migration = require database_path('migrations/2026_10_17_160000_unify_legacy_product_prices.php');
        $migration->down();

        $timestamp = '2026-01-02 03:04:05';

        DB::table('events')->insert([
            'id' => 502, 'title' => 'Event', 'description' => 'Event description',
            'start_date' => '2026-01-01', 'end_date' => '2026-01-02',
            'start_time' => $timestamp, 'end_time' => $timestamp,
            'event_price_id' => 42, 'featured_image' => 'event.png',
            'created_at' => $timestamp, 'updated_at' => $timestamp, 'deleted_at' => null,
        ]);
        DB::table('workshops')->insert([
            'id' => 503, 'title' => 'Workshop', 'description' => 'Workshop description',
            'start_date' => '2026-01-01', 'end_date' => '2026-01-02',
            'start_time' => $timestamp, 'end_time' => $timestamp,
            'type' => 'training', 'location' => 'Room 1', 'workshop_price_id' => '43',
            'created_at' => $timestamp, 'updated_at' => $timestamp, 'deleted_at' => null,
        ]);

        DB::table('course_prices')->insert([
            'id' => 41,
            'course_id' => 501,
            'amount' => 1250,
            'start_on' => '2026-02-01',
            'end_on' => '2026-06-30',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
        DB::table('event_prices')->insert([
            'id' => 42,
            'event_id' => 502,
            'amount' => 499,
            'currency' => 'EUR',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => $timestamp,
        ]);
        DB::table('workshop_prices')->insert([
            'id' => 43,
            'workshop_id' => 503,
            'amount' => 750,
            'currency' => 'RON',
            'start_on' => '2026-03-01',
            'end_on' => '2026-03-31',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
        DB::table('camps')->insert([
            'id' => 504,
            'title' => 'Camp',
            'description' => 'Camp description',
            'location' => 'Somewhere',
            'start_on' => '2026-07-01',
            'end_on' => '2026-07-07',
            'camp_price' => 880,
            'camp_price_id' => 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);

        $migration->up();

        $this->assertDatabaseHas('product_prices', [
            'legacy_source_type' => 'course_prices',
            'legacy_source_id' => 41,
            'price' => 1250,
            'currency' => null,
            'priceable_type' => 'course',
            'priceable_id' => 501,
            'valid_from' => '2026-02-01',
            'valid_until' => '2026-06-30',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('product_prices', [
            'legacy_source_type' => 'event_prices',
            'legacy_source_id' => 42,
            'price' => 499,
            'currency' => 'EUR',
            'priceable_type' => 'event',
            'priceable_id' => 502,
            'deleted_at' => $timestamp,
        ]);
        $this->assertDatabaseHas('product_prices', [
            'legacy_source_type' => 'workshop_prices',
            'legacy_source_id' => 43,
            'price' => 750,
            'currency' => 'RON',
            'priceable_type' => 'workshop',
            'priceable_id' => 503,
            'valid_from' => '2026-03-01',
            'valid_until' => '2026-03-31',
        ]);
        $this->assertDatabaseHas('product_prices', [
            'legacy_source_type' => 'camps.camp_price',
            'legacy_source_id' => 504,
            'price' => 880,
            'currency' => null,
            'priceable_type' => 'camp',
            'priceable_id' => 504,
            'valid_from' => '2026-07-01',
            'valid_until' => '2026-07-07',
        ]);

        $this->assertSame('880.00', Camp::query()->findOrFail(504)->productPrice->price);

        $this->assertDatabaseCount('course_prices', 1);
        $this->assertDatabaseCount('event_prices', 1);
        $this->assertDatabaseCount('workshop_prices', 1);
        $this->assertDatabaseCount('product_prices', 4);
        $eventPriceId = DB::table('product_prices')->where('legacy_source_type', 'event_prices')->value('id');
        $workshopPriceId = DB::table('product_prices')->where('legacy_source_type', 'workshop_prices')->value('id');
        $this->assertDatabaseHas('events', ['id' => 502, 'event_price_id' => $eventPriceId]);
        $this->assertDatabaseHas('workshops', ['id' => 503, 'workshop_price_id' => (string) $workshopPriceId]);

        $migration->down();

        $this->assertDatabaseCount('product_prices', 0);
        $this->assertDatabaseCount('course_prices', 1);
        $this->assertDatabaseCount('event_prices', 1);
        $this->assertDatabaseCount('workshop_prices', 1);
        $this->assertDatabaseHas('events', ['id' => 502, 'event_price_id' => 42]);
        $this->assertDatabaseHas('workshops', ['id' => 503, 'workshop_price_id' => '43']);
    }

    public function test_legacy_price_models_write_to_the_unified_table(): void
    {
        $course = Course::factory()->create();
        $coursePrice = CoursePrice::query()->create([
            'course_id' => $course->id,
            'amount' => '125.50',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
        ]);

        $this->assertSame('course', $coursePrice->priceable_type);
        $this->assertSame($course->id, $coursePrice->course_id);
        $this->assertSame($course->id, $coursePrice->course->id);
        $this->assertDatabaseHas('product_prices', [
            'id' => $coursePrice->id,
            'priceable_type' => 'course',
            'priceable_id' => $course->id,
            'price' => '125.50',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);
        $this->assertDatabaseCount('course_prices', 0);

        $event = Event::query()->create([
            'title' => 'Event',
            'description' => 'Event description',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-02',
            'start_time' => '2026-01-01 09:00:00',
            'end_time' => '2026-01-01 10:00:00',
            'event_price_id' => null,
            'featured_image' => 'event.png',
        ]);
        $eventPrice = EventPrice::query()->create([
            'event_id' => $event->id,
            'amount' => '49.00',
            'currency' => 'EUR',
        ]);
        $this->assertSame($event->id, $eventPrice->event->id);
        $this->assertDatabaseHas('product_prices', [
            'id' => $eventPrice->id,
            'priceable_type' => 'event',
            'priceable_id' => $event->id,
            'price' => '49.00',
            'currency' => 'EUR',
        ]);
        $this->assertDatabaseCount('event_prices', 0);

        $workshop = Workshop::query()->create([
            'title' => 'Workshop',
            'description' => 'Workshop description',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-02',
            'start_time' => '2026-01-01 09:00:00',
            'end_time' => '2026-01-01 10:00:00',
            'type' => 'training',
            'location' => 'Room 1',
            'workshop_price_id' => null,
        ]);
        $workshopPrice = WorkshopPrice::query()->create([
            'workshop_id' => $workshop->id,
            'amount' => '75.00',
            'currency' => 'RON',
            'start_on' => '2026-04-01',
            'end_on' => '2026-04-30',
        ]);
        $this->assertSame($workshop->id, $workshopPrice->workshop->id);
        $this->assertDatabaseHas('product_prices', [
            'id' => $workshopPrice->id,
            'priceable_type' => 'workshop',
            'priceable_id' => $workshop->id,
            'price' => '75.00',
            'currency' => 'RON',
            'valid_from' => '2026-04-01',
            'valid_until' => '2026-04-30',
        ]);
        $this->assertDatabaseCount('workshop_prices', 0);
    }

    public function test_effective_price_lookup_respects_historical_validity_and_prefers_latest_start_date(): void
    {
        $course = Course::factory()->create();
        $course->productPrices()->create([
            'price' => '100.00',
            'currency' => 'RON',
            'price_type' => 'standard',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);
        $newer = $course->productPrices()->create([
            'price' => '125.00',
            'currency' => 'RON',
            'price_type' => 'standard',
            'valid_from' => '2026-06-01',
            'valid_until' => null,
        ]);

        $service = app(ProductPriceService::class);

        $this->assertSame('100.00', $service->effectivePrice($course, '2026-03-01')?->price);
        $this->assertSame($newer->id, $service->effectivePrice($course, '2026-07-01')?->id);
        $this->assertNull($service->effectivePrice($course, '2025-12-31'));
    }

    public function test_unified_price_model_exposes_the_polymorphic_owner_and_validity_dates(): void
    {
        $price = ProductPrice::query()->create([
            'price' => '42.50',
            'currency' => 'RON',
            'priceable_type' => 'course',
            'priceable_id' => 987,
            'price_type' => 'standard',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);

        $this->assertSame('42.50', $price->fresh()->price);
        $this->assertSame('standard', $price->fresh()->price_type);
        $this->assertSame('2026-01-01', $price->fresh()->valid_from->toDateString());
        $this->assertSame('2026-12-31', $price->fresh()->valid_until->toDateString());
    }
}
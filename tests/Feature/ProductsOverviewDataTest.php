<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Course;
use App\Models\Event;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Workshop;
use App\Services\ProductsOverviewData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsOverviewDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_single_effective_price_and_does_not_choose_between_overlapping_prices(): void
    {
        $date = CarbonImmutable::parse('2026-06-15', config('app.timezone'));
        $course = Course::factory()->create(['title' => 'Robotics']);
        $product = Product::factory()->create([
            'title' => 'Robotics Course',
            'status' => 'published',
            'productable_type' => 'course',
            'productable_id' => $course->id,
        ]);
        ProductPrice::factory()->create(['priceable_type' => 'course', 'priceable_id' => $course->id, 'price' => 120, 'currency' => 'EUR', 'valid_from' => '2026-06-01', 'valid_until' => '2026-06-30']);
        $ambiguousCourse = Course::factory()->create(['title' => 'Ambiguous']);
        Product::factory()->create(['title' => 'Ambiguous Product', 'status' => 'draft', 'productable_type' => 'course', 'productable_id' => $ambiguousCourse->id]);
        ProductPrice::factory()->create(['priceable_type' => 'course', 'priceable_id' => $ambiguousCourse->id, 'price' => 100, 'currency' => 'EUR']);
        ProductPrice::factory()->create(['priceable_type' => 'course', 'priceable_id' => $ambiguousCourse->id, 'price' => 200, 'currency' => 'EUR']);

        $summary = app(ProductsOverviewData::class)->summary($date);
        $rows = $summary['products']->keyBy('id');

        $this->assertSame('available', $rows[$product->id]['price_state']);
        $this->assertSame('120.00', $rows[$product->id]['price']);
        $this->assertSame('EUR', $rows[$product->id]['currency']);
        $this->assertSame('ambiguous', $rows->firstWhere('title', 'Ambiguous Product')['price_state']);
        $this->assertNull($rows->firstWhere('title', 'Ambiguous Product')['price']);
        $this->assertSame(1, $summary['with_effective_price']);
        $this->assertSame(1, $summary['ambiguous_price']);
    }

    public function test_it_reports_missing_and_future_prices_without_guessing(): void
    {
        $course = Course::factory()->create();
        $product = Product::factory()->create(['productable_type' => 'course', 'productable_id' => $course->id]);
        ProductPrice::factory()->create(['priceable_type' => 'course', 'priceable_id' => $course->id, 'valid_from' => '2026-07-01', 'valid_until' => null]);

        $summary = app(ProductsOverviewData::class)->summary(CarbonImmutable::parse('2026-06-15', config('app.timezone')));

        $this->assertSame('missing', $summary['products']->firstWhere('id', $product->id)['price_state']);
        $this->assertSame(1, $summary['missing_price']);
    }

    public function test_it_supports_each_productable_type_and_only_infers_availability_from_date_ranges(): void
    {
        $camp = Camp::query()->forceCreate(['title' => 'Camp', 'description' => 'Camp description', 'location' => 'Satu Mare', 'start_on' => '2026-06-01', 'end_on' => '2026-06-30', 'camp_price' => 100, 'camp_price_id' => 'legacy-camp-price']);
        $event = Event::query()->create(['title' => 'Event', 'description' => 'Event description', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'start_time' => '2026-06-01 09:00:00', 'end_time' => '2026-06-30 17:00:00', 'event_price_id' => 'legacy-event-price', 'featured_image' => 'event.jpg']);
        $workshop = Workshop::query()->create(['title' => 'Workshop', 'description' => 'Workshop description', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'start_time' => '2026-06-01 09:00:00', 'end_time' => '2026-06-30 17:00:00', 'type' => 'training', 'location' => 'Satu Mare', 'workshop_price_id' => 'legacy-workshop-price']);
        $course = Course::factory()->create();

        foreach ([['camp', $camp], ['event', $event], ['workshop', $workshop], ['course', $course]] as [$type, $owner]) {
            Product::factory()->create(['title' => ucfirst($type), 'productable_type' => $type, 'productable_id' => $owner->id]);
        }

        $summary = app(ProductsOverviewData::class)->summary(CarbonImmutable::parse('2026-06-15', config('app.timezone')));
        $rows = $summary['products']->keyBy('type');

        $this->assertSame('In date range', $rows['camp']['availability']);
        $this->assertSame('In date range', $rows['event']['availability']);
        $this->assertSame('In date range', $rows['workshop']['availability']);
        $this->assertSame('Not defined by model', $rows['course']['availability']);
        $this->assertSame(['camp', 'course', 'event', 'workshop'], array_keys($summary['types']));
    }

    public function test_it_filters_by_stored_status_product_type_and_price_state(): void
    {
        $course = Course::factory()->create();
        $camp = Camp::query()->forceCreate(['title' => 'Camp', 'description' => 'Camp description', 'location' => 'Satu Mare', 'start_on' => '2026-06-01', 'end_on' => '2026-06-30', 'camp_price' => 100, 'camp_price_id' => 'legacy-camp-price']);
        Product::factory()->create(['title' => 'Published course', 'status' => 'published', 'productable_type' => 'course', 'productable_id' => $course->id]);
        Product::factory()->create(['title' => 'Draft camp', 'status' => 'draft', 'productable_type' => 'camp', 'productable_id' => $camp->id]);

        $summary = app(ProductsOverviewData::class)->summary(
            CarbonImmutable::parse('2026-06-15', config('app.timezone')),
            statusFilter: 'published',
            typeFilter: 'course',
            priceFilter: 'missing',
        );

        $this->assertCount(1, $summary['products']);
        $this->assertSame('Published course', $summary['products']->first()['title']);
    }

    public function test_it_marks_missing_productable_records_without_fabricating_a_price(): void
    {
        $product = Product::factory()->create(['productable_type' => 'course', 'productable_id' => 999999]);

        $summary = app(ProductsOverviewData::class)->summary(CarbonImmutable::parse('2026-06-15', config('app.timezone')));
        $row = $summary['products']->firstWhere('id', $product->id);

        $this->assertFalse($row['productable_exists']);
        $this->assertSame('unavailable product record', $row['price_state']);
        $this->assertSame('Unavailable: linked record missing', $row['availability']);
        $this->assertSame(1, $summary['missing_productable']);
    }
}
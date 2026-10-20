<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Course;
use App\Models\Event;
use App\Models\Product;
use App\Models\Workshop;
use App\Services\ProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ProductableObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_supported_productable_creates_exactly_one_draft_product(): void
    {
        $models = [
            Course::factory()->create(['title' => 'Course product']),
            Camp::query()->forceCreate(['title' => 'Camp product', 'description' => 'Description', 'location' => 'Satu Mare', 'start_on' => '2026-06-01', 'end_on' => '2026-06-02']),
            Event::query()->create(['title' => 'Event product', 'description' => 'Description', 'start_date' => '2026-06-01', 'end_date' => '2026-06-02', 'start_time' => '2026-06-01 09:00:00', 'end_time' => '2026-06-02 17:00:00', 'featured_image' => 'event.jpg']),
            Workshop::query()->create(['title' => 'Workshop product', 'description' => 'Description', 'start_date' => '2026-06-01', 'end_date' => '2026-06-02', 'start_time' => '2026-06-01 09:00:00', 'end_time' => '2026-06-02 17:00:00', 'type' => 'training', 'location' => 'Satu Mare']),
        ];

        foreach ($models as $model) {
            $product = Product::query()->where('productable_type', $model->getMorphClass())->where('productable_id', $model->getKey())->sole();
            $this->assertSame($model->title, $product->title);
            $this->assertSame('draft', $product->status);
        }

        $this->assertDatabaseCount('products', 4);
    }

    public function test_observer_is_idempotent_and_syncs_title_without_overwriting_status(): void
    {
        $course = Course::factory()->create(['title' => 'Original title']);
        $product = $course->product()->firstOrFail();
        $product->update(['status' => 'published']);

        app(ProductSyncService::class)->sync($course);
        app(ProductSyncService::class)->sync($course);
        $course->update(['title' => 'Updated title']);

        $this->assertDatabaseCount('products', 1);
        $this->assertSame('Updated title', $product->fresh()->title);
        $this->assertSame('published', $product->fresh()->status);
    }

    public function test_soft_delete_deactivates_product_and_restore_reuses_it(): void
    {
        $course = Course::factory()->create(['title' => 'Lifecycle course']);
        $product = $course->product()->firstOrFail();

        $course->delete();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $course->restore();
        $this->assertDatabaseCount('products', 1);
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_product_creation_rolls_back_with_the_source_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                Course::factory()->create(['title' => 'Rolled back course']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_backfill_defaults_to_dry_run_and_apply_is_repeatable(): void
    {
        $course = Course::factory()->create(['title' => 'Backfill course']);
        $product = $course->product()->firstOrFail();
        $product->delete();

        $this->artisan('products:backfill')->expectsOutputToContain('Dry run: 0 missing product(s), 1 existing product link(s).')->assertExitCode(0);
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->artisan('products:backfill', ['--apply' => true])->assertExitCode(0);
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
        $this->artisan('products:backfill', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_backfill_creates_a_missing_product_only_when_apply_is_requested(): void
    {
        $course = Course::withoutEvents(fn () => Course::factory()->create(['title' => 'Legacy course']));

        $this->artisan('products:backfill')
            ->expectsOutputToContain('Dry run: 1 missing product(s), 0 existing product link(s).')
            ->assertExitCode(0);
        $this->assertDatabaseCount('products', 0);

        $this->artisan('products:backfill', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseHas('products', [
            'title' => 'Legacy course',
            'productable_type' => 'course',
            'productable_id' => $course->id,
        ]);
    }

    public function test_service_rejects_unsupported_productable_models(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProductSyncService::class)->sync(new Product);
    }
}

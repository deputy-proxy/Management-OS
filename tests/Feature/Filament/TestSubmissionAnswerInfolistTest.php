<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TestSubmissionAnswers\Pages\ViewTestSubmissionAnswer;
use App\Filament\Resources\TestSubmissionAnswers\TestSubmissionAnswerResource;
use App\Models\TestSubmissionAnswer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class TestSubmissionAnswerInfolistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::before(fn (): bool => true);
    }

    public function test_answer_view_renders_legacy_scalar_answer_and_metadata(): void
    {
        $user = User::factory()->create();
        $answer = TestSubmissionAnswer::factory()->create([
            'answer' => 'The student wrote a free-text answer.',
            'metadata' => 'Legacy metadata value',
        ]);

        $this->actingAs($user);

        Livewire::test(ViewTestSubmissionAnswer::class, ['record' => $answer->getKey()])
            ->assertSee('The student wrote a free-text answer.')
            ->assertSee('Legacy metadata value');
    }

    public function test_answer_view_handles_missing_answer_and_metadata(): void
    {
        $user = User::factory()->create();
        $answer = TestSubmissionAnswer::factory()->create([
            'answer' => null,
            'metadata' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(ViewTestSubmissionAnswer::class, ['record' => $answer->getKey()])
            ->assertSee('Answer')
            ->assertSee('Metadata')
            ->assertSee('—');
    }

    public function test_answer_view_renders_structured_values_and_has_a_registered_route(): void
    {
        $user = User::factory()->create();
        $answer = TestSubmissionAnswer::factory()->create([
            'answer' => ['selected' => ['A', 'C']],
            'metadata' => ['time_spent_seconds' => 42],
        ]);

        $this->actingAs($user);

        Livewire::test(ViewTestSubmissionAnswer::class, ['record' => $answer->getKey()])
            ->assertSee('selected')
            ->assertSee('time_spent_seconds')
            ->assertSee('42');

        $this->get(TestSubmissionAnswerResource::getUrl('view', ['record' => $answer]))
            ->assertOk()
            ->assertSee('time_spent_seconds');
    }
}

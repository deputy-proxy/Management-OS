<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Student;
use App\Models\Task;
use PHPUnit\Framework\TestCase;

class PanelSearchTitleTest extends TestCase
{
    public function test_it_uses_a_human_readable_title_and_id(): void
    {
        $article = new Article;
        $article->setRawAttributes(['id' => 17, 'title' => 'Autumn Programme']);

        $this->assertSame('Autumn Programme', $article->title);
    }

    public function test_it_builds_a_name_from_first_and_last_name_when_needed(): void
    {
        $student = new Student;
        $student->setRawAttributes(['id' => 4, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->assertSame('Ada Lovelace · #4', $student->title);
    }

    public function test_it_falls_back_without_exposing_email_or_other_private_fields(): void
    {
        $task = new Task;
        $task->setRawAttributes(['id' => 9, 'email' => 'private@example.test', 'description' => 'Sensitive note']);

        $this->assertSame('Task · #9', $task->title);
    }

    public function test_unsaved_records_have_a_human_readable_fallback(): void
    {
        $task = new Task;

        $this->assertSame('Task', $task->title);
    }
}

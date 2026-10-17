<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            CommentsAction::make(),
            TasksAction::make(),
        ];
    }
}

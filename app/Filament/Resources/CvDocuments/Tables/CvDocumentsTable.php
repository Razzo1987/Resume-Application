<?php

namespace App\Filament\Resources\CvDocuments\Tables;

use App\Models\CvDocument;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('language')->latest('id'))
            ->defaultGroup(Group::make('language.name')->label(__('admin.fields.language'))->collapsible())
            ->columns([
                IconColumn::make('is_current')->label(__('admin.fields.is_current'))->boolean(),
                TextColumn::make('created_at')->label(__('admin.fields.uploaded_at'))->dateTime('d/m/Y H:i'),
                TextColumn::make('original_name')->label(__('admin.fields.original_name'))->searchable(),
                TextColumn::make('download_name')
                    ->label(__('admin.fields.download_name'))
                    ->state(fn (CvDocument $record): string => $record->downloadName())
                    ->color('gray'),
                TextColumn::make('size')->label(__('admin.fields.size'))->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextColumn::make('note')->label(__('admin.fields.note'))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('language_id')->label(__('admin.fields.language'))->relationship('language', 'name'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('admin.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (CvDocument $record): StreamedResponse => Storage::disk(CvDocument::DISK)->download($record->path, $record->downloadName())),
                Action::make('makeCurrent')
                    ->label(__('admin.actions.make_current'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->hidden(fn (CvDocument $record): bool => $record->is_current)
                    ->requiresConfirmation()
                    ->action(function (CvDocument $record): void {
                        $record->update(['is_current' => true]);
                        Notification::make()->success()->title(__('admin.messages.cv_now_current'))->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

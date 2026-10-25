<?php

namespace App\Filament\Resources\CvDocuments\Schemas;

use App\Models\CvDocument;
use App\Models\Language;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CvDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Select::make('language_id')
                        ->label(__('admin.fields.language'))
                        ->options(fn (): array => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->required()
                        ->live()
                        ->disabledOn('edit'),
                    FileUpload::make('path')
                        ->label(__('admin.fields.cv_file'))
                        ->helperText(__('admin.help.cv_file'))
                        ->disk(CvDocument::DISK)
                        ->directory(CvDocument::DIRECTORY)
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(20 * 1024)
                        ->storeFileNamesIn('original_name')
                        ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, Get $get): string {
                            $language = Language::query()->find($get('language_id'));

                            return CvDocument::storageName($language instanceof Language ? $language->code : 'xx');
                        })
                        ->required()
                        ->hiddenOn('edit'),
                    TextInput::make('note')->label(__('admin.fields.note'))->helperText(__('admin.help.cv_note'))->maxLength(255),
                    Toggle::make('is_current')->label(__('admin.fields.is_current'))->helperText(__('admin.help.is_current'))->default(true),
                ])
                ->columnSpanFull(),
        ]);
    }
}

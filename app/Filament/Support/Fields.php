<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Media\ImageOptimizer;
use App\Themes\ThemeManager;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Campi ricorrenti nei form admin.
 */
class Fields
{
    /**
     * Immagine pubblica (disco uploads), ridimensionata e convertita in WebP.
     */
    /**
     * Testo lungo (pagine di dettaglio, articoli): titoli, elenchi, citazioni, tabelle e immagini.
     * Le immagini inserite finiscono in public/uploads/{cartella}, servite direttamente dal web server.
     */
    public static function richText(string $name, string $attachmentsDirectory): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3'],
                ['blockquote', 'bulletList', 'orderedList'],
                ['table', 'attachFiles'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk('uploads')
            ->fileAttachmentsDirectory($attachmentsDirectory)
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->fileAttachmentsMaxSize(8 * 1024);
    }

    /**
     * Testo breve (sintesi, descrizioni): grassetto, corsivo, link ed elenchi.
     */
    public static function richSummary(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']]);
    }

    public static function image(string $name, string $directory, int $maxSize = 1600): FileUpload
    {
        return FileUpload::make($name)
            ->label(__("admin.fields.{$name}"))
            ->image()
            ->disk('uploads')
            ->directory($directory)
            ->visibility('public')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->maxSize(8 * 1024)
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageOptimizer::class)->store(
                $file->getRealPath(),
                $file->getClientOriginalName(),
                'uploads',
                $directory,
                $maxSize,
            ));
    }

    /**
     * PDF pubblico (disco uploads), es. attestato di una certificazione.
     */
    public static function publicPdf(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label(__("admin.fields.{$name}"))
            ->disk('uploads')
            ->directory($directory)
            ->visibility('public')
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize(20 * 1024)
            ->downloadable()
            ->preserveFilenames(false);
    }

    /**
     * Periodo: inizio obbligatorio (o no), fine vuota = "in corso".
     */
    public static function period(bool $startRequired = true): Grid
    {
        return Grid::make(2)->schema([
            DatePicker::make('start_date')
                ->label(__('admin.fields.start_date'))
                ->required($startRequired)
                ->native(false)
                ->displayFormat('m/Y')
                ->closeOnDateSelection(),
            DatePicker::make('end_date')
                ->label(__('admin.fields.end_date'))
                ->helperText(__('admin.help.end_date_empty'))
                ->native(false)
                ->displayFormat('m/Y')
                ->closeOnDateSelection()
                ->afterOrEqual(fn (Get $get) => $get('start_date') ?: null),
        ]);
    }

    public static function visible(): Toggle
    {
        return Toggle::make('is_visible')
            ->label(__('admin.fields.is_visible'))
            ->helperText(__('admin.help.is_visible'))
            ->default(true);
    }

    /**
     * Icona semantica scelta dall'elenco del tema attivo.
     */
    public static function icon(string $name = 'icon'): Select
    {
        $icons = array_keys(app(ThemeManager::class)->icons());
        sort($icons);

        return Select::make($name)
            ->label(__('admin.fields.icon'))
            ->options(array_combine($icons, $icons))
            ->searchable();
    }
}

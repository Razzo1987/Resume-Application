<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Media\ImageOptimizer;
use App\Themes\ThemeManager;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Fields that recur in the admin forms.
 */
class Fields
{
    /** Lengths shown by search results: titles and descriptions longer than this are cut */
    public const int SEO_TITLE_LENGTH = 60;

    public const int SEO_DESCRIPTION_LENGTH = 160;

    /**
     * Character counter next to the label ("142 / 160"), in orange over the recommended length. The field is not
     * limited by it: the hard limit stays maxLength().
     *
     * @template T of TextInput|Textarea
     *
     * @param  T  $field
     * @return T
     */
    public static function counted(TextInput|Textarea $field, int $recommended): TextInput|Textarea
    {
        $length = fn (mixed $state): int => is_string($state) ? mb_strlen($state) : 0;

        return $field
            ->live(debounce: 400)
            ->hint(fn (mixed $state): string => __('admin.help.characters', ['count' => $length($state), 'recommended' => $recommended]))
            ->hintColor(fn (mixed $state): string => $length($state) > $recommended ? 'warning' : 'gray');
    }

    /**
     * Long text (detail pages, articles): headings, lists, quotes, tables and images.
     * Inserted images end up in public/uploads/{directory}, served directly by the web server.
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
     * Short text (summaries, descriptions): bold, italic, links and lists.
     */
    public static function richSummary(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']]);
    }

    /**
     * Public image (uploads disk), resized and converted to WebP.
     */
    public static function image(string $name, string $directory, int $maxSize = 1600, string $format = 'webp'): FileUpload
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
                $format,
            ));
    }

    /**
     * Public PDF (uploads disk), e.g. the certificate of a certification.
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
     * Period: start required (or not), empty end = "ongoing".
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
     * Semantic icon chosen from the active theme's list.
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

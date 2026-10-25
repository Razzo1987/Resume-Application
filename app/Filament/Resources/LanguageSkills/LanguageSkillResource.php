<?php

namespace App\Filament\Resources\LanguageSkills;

use App\Enums\CefrLevel;
use App\Filament\Resources\LanguageSkills\Pages\ManageLanguageSkills;
use App\Filament\Support\Columns;
use App\Filament\Support\Fields;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Translatable;
use App\Models\Certification;
use App\Models\LanguageSkill;
use App\Support\Languages\IsoLanguages;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class LanguageSkillResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = LanguageSkill::class;

    protected static string $labelKey = 'language_skills';

    protected static string $navigationGroupKey = 'cv';

    protected static ?int $navigationSort = 70;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function form(Schema $schema): Schema
    {
        $notNative = fn (Get $get): bool => ! $get('is_native');

        return $schema->components([
            Grid::make(2)->schema([
                Select::make('language_code')
                    ->label(__('admin.fields.language'))
                    ->options(fn (): array => app(IsoLanguages::class)->options())
                    ->searchable()
                    ->required(),
                Toggle::make('is_native')->label(__('admin.fields.is_native'))->inline(false)->live(),
            ]),
            Grid::make(3)->schema([
                Select::make('oral')->label(__('admin.fields.oral'))->options(CefrLevel::class)->required($notNative)->visible($notNative),
                Select::make('written')->label(__('admin.fields.written'))->options(CefrLevel::class)->required($notNative)->visible($notNative),
                Select::make('comprehension')->label(__('admin.fields.comprehension'))->helperText(__('admin.help.optional'))->options(CefrLevel::class)->visible($notNative),
            ]),
            Select::make('certification_id')
                ->label(__('admin.fields.language_certificate'))
                ->relationship('certification', 'id')
                ->getOptionLabelFromRecordUsing(fn (Certification $record): string => (string) $record->t('title'))
                ->searchable()
                ->preload(),
            Translatable::tabs(fn (string $locale): array => [
                TextInput::make("note.{$locale}")->label(__('admin.fields.note'))->helperText(__('admin.help.language_note'))->maxLength(160),
            ]),
            Fields::visible(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('language')
                    ->label(__('admin.fields.language'))
                    ->state(fn (LanguageSkill $record): string => $record->displayName()),
                IconColumn::make('is_native')->label(__('admin.fields.is_native'))->boolean(),
                TextColumn::make('oral')->label(__('admin.fields.oral'))->badge(),
                TextColumn::make('written')->label(__('admin.fields.written'))->badge(),
                TextColumn::make('comprehension')->label(__('admin.fields.comprehension'))->badge()->toggleable(),
                Columns::missingTranslations(),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLanguageSkills::route('/'),
        ];
    }
}

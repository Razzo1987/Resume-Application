<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\StatExclusion;
use App\Models\StatVisit;
use App\Support\Settings\Settings;
use App\Support\Stats\Exclusions;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Exclusions from the statistics: addresses used for the admin (automatic, they expire), IP addresses, networks and
 * ASNs excluded by hand, addresses always counted. Adding an exclusion deletes the visits already recorded (see
 * App\Support\Stats\Exclusions); removing it starts the count again from that moment.
 */
class StatsExclusions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static ?string $slug = 'statistics/exclusions';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.stats-exclusions';

    public function getTitle(): string
    {
        return __('admin.stats.exclusions.title');
    }

    public function getSubheading(): string
    {
        return __('admin.stats.exclusions.intro', ['days' => app(Exclusions::class)->days(), 'ip' => (string) request()->ip()]);
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [Statistics::getUrl() => __('admin.stats.title'), __('admin.stats.exclusions.title')];
    }

    protected function getHeaderActions(): array
    {
        return [$this->addAction()];
    }

    public function addAction(): Action
    {
        return Action::make('add')
            ->label(__('admin.stats.exclusions.add'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(__('admin.stats.exclusions.add'))
            ->schema([
                Radio::make('type')
                    ->label(__('admin.stats.exclusions.fields.type'))
                    ->options(fn (): array => collect(StatExclusion::TYPES)->mapWithKeys(fn (string $type): array => [$type => __('admin.stats.exclusions.types.'.$type)])->all())
                    ->descriptions(fn (): array => collect(StatExclusion::TYPES)->mapWithKeys(fn (string $type): array => [$type => __('admin.stats.exclusions.types_help.'.$type)])->all())
                    ->default('network')
                    ->required()
                    ->live(),
                TextInput::make('value')
                    ->label(__('admin.stats.exclusions.fields.value'))
                    ->placeholder(fn (Get $get): string => $get('type') === 'ip' ? (string) request()->ip() : '93.44.12.x')
                    ->hidden(fn (Get $get): bool => $get('type') === 'asn')
                    ->required(fn (Get $get): bool => $get('type') !== 'asn')
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if (Exclusions::normalize((string) $get('type'), (string) $value) === null) {
                            $fail(__('admin.stats.exclusions.invalid'));
                        }
                    })
                    ->live(debounce: 500),
                Select::make('asn')
                    ->label(__('admin.stats.exclusions.types.asn'))
                    ->options(fn (): array => StatVisit::query()->whereNotNull('asn')->selectRaw('asn, max(as_org) as name, count(*) as total')->groupBy('asn')->orderByDesc('total')->limit(300)->toBase()->get()
                        ->mapWithKeys(fn (object $row): array => [(string) $row->asn => ($row->name ?? '?').' (AS'.$row->asn.')'])->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('type') === 'asn')
                    ->required(fn (Get $get): bool => $get('type') === 'asn')
                    ->live(),
                Radio::make('status')
                    ->label(__('admin.stats.exclusions.fields.status'))
                    ->options([
                        StatExclusion::EXCLUDED => __('admin.stats.exclusions.statuses.excluded'),
                        StatExclusion::ALWAYS => __('admin.stats.exclusions.statuses.always'),
                    ])
                    ->descriptions([
                        StatExclusion::EXCLUDED => __('admin.stats.exclusions.statuses_help.excluded'),
                        StatExclusion::ALWAYS => __('admin.stats.exclusions.statuses_help.always'),
                    ])
                    ->default(StatExclusion::EXCLUDED)
                    ->required()
                    ->live(),
                TextInput::make('note')->label(__('admin.stats.exclusions.fields.note'))->maxLength(150),
                Text::make(function (Get $get): string {
                    $type = (string) $get('type');
                    $value = (string) ($type === 'asn' ? $get('asn') : $get('value'));

                    if ($get('status') !== StatExclusion::EXCLUDED || Exclusions::normalize($type, $value) === null) {
                        return '';
                    }

                    $count = app(Exclusions::class)->count($type, $value);

                    return trans_choice('admin.stats.exclusions.preview.'.($type === 'ip' ? 'ip' : 'all'), $count, ['count' => $count]);
                })->color('warning'),
            ])
            ->modalSubmitActionLabel(__('admin.stats.exclusions.save'))
            ->action(function (array $data): void {
                $type = (string) $data['type'];
                $result = app(Exclusions::class)->add($type, (string) ($type === 'asn' ? $data['asn'] : $data['value']), (string) $data['status'], $data['note'] ?? null);
                $this->notify($result['deleted']);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StatExclusion::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')->label(__('admin.stats.exclusions.fields.type'))
                    ->formatStateUsing(fn (string $state): string => __('admin.stats.exclusions.types.'.$state)),
                TextColumn::make('value')->label(__('admin.stats.exclusions.fields.value'))->fontFamily('mono')->searchable()
                    ->formatStateUsing(fn (StatExclusion $record): string => $record->type === 'asn' ? 'AS'.$record->value : $record->value)
                    ->description(fn (StatExclusion $record): ?string => $record->type === 'asn' ? StatVisit::query()->where('asn', (int) $record->value)->value('as_org') : null),
                TextColumn::make('status')->label(__('admin.stats.exclusions.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin.stats.exclusions.statuses.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        StatExclusion::EXCLUDED => 'danger',
                        StatExclusion::ALWAYS => 'success',
                        default => 'gray',
                    })
                    ->description(fn (StatExclusion $record): ?string => $record->status === StatExclusion::AUTO && $record->last_seen_at !== null
                        ? __('admin.stats.exclusions.last_seen', ['when' => $record->last_seen_at->diffForHumans(), 'until' => $record->last_seen_at->copy()->addDays(app(Exclusions::class)->days())->timezone(app(Settings::class)->timezone())->format('d/m/Y')])
                        : null),
                TextColumn::make('note')->label(__('admin.stats.exclusions.fields.note'))->default('-')->wrap(),
                TextColumn::make('created_at')->label(__('admin.stats.exclusions.fields.created_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('exclude')
                    ->label(__('admin.stats.exclusions.exclude'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (StatExclusion $record): bool => $record->status !== StatExclusion::EXCLUDED)
                    ->requiresConfirmation()
                    ->modalDescription(function (StatExclusion $record): string {
                        $count = app(Exclusions::class)->matching($record->type, $record->value)->count();

                        return trans_choice('admin.stats.exclusions.preview.'.($record->type === 'ip' ? 'ip' : 'all'), $count, ['count' => $count]);
                    })
                    ->action(fn (StatExclusion $record) => $this->notify(app(Exclusions::class)->setStatus($record, StatExclusion::EXCLUDED)['deleted'])),
                Action::make('always')
                    ->label(__('admin.stats.exclusions.always'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (StatExclusion $record): bool => $record->status !== StatExclusion::ALWAYS && $record->type !== 'asn')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.stats.exclusions.always_help'))
                    ->action(function (StatExclusion $record): void {
                        app(Exclusions::class)->setStatus($record, StatExclusion::ALWAYS);
                        Notification::make()->success()->title(__('admin.messages.saved'))->send();
                    }),
                Action::make('remove')
                    ->label(__('admin.stats.exclusions.remove'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (StatExclusion $record): string => __($record->status === StatExclusion::AUTO ? 'admin.stats.exclusions.remove_auto_help' : 'admin.stats.exclusions.remove_help'))
                    ->action(function (StatExclusion $record): void {
                        $record->delete();
                        Notification::make()->success()->title(__('admin.stats.exclusions.removed'))->send();
                    }),
            ])
            ->emptyStateHeading(__('admin.stats.exclusions.empty'))
            ->paginated([25, 50, 100]);
    }

    private function notify(int $deleted): void
    {
        Notification::make()->success()->title(trans_choice('admin.stats.exclusions.added', $deleted, ['count' => $deleted]))->send();
    }
}

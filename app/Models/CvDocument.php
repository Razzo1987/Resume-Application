<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Versione del CV in PDF per una lingua. Il file sta sul disco privato "local",
 * con nome cv_{lingua}_{timestamp}.pdf; si scarica con un nome standard senza date.
 *
 * @property int $id
 * @property int $language_id
 * @property string $path
 * @property string $original_name
 * @property int $size
 * @property string $sha256
 * @property bool $is_current
 * @property string|null $note
 * @property-read Language $language
 */
class CvDocument extends Model
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'cv';

    protected $fillable = ['language_id', 'path', 'original_name', 'size', 'sha256', 'is_current', 'note'];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CvDocument $document): void {
            $disk = Storage::disk(self::DISK);

            if ((! $document->exists || $document->isDirty('path')) && $disk->exists($document->path)) {
                $document->size = (int) $disk->size($document->path);
                $document->sha256 = hash_file('sha256', $disk->path($document->path)) ?: '';
            }
        });

        // Una sola versione corrente per lingua
        static::saved(function (CvDocument $document): void {
            if ($document->is_current) {
                self::query()
                    ->where('language_id', $document->language_id)
                    ->whereKeyNot($document->getKey())
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }
        });

        static::deleted(function (CvDocument $document): void {
            Storage::disk(self::DISK)->delete($document->path);

            // Se era la versione corrente, diventa corrente la più recente rimasta
            if ($document->is_current) {
                self::query()->where('language_id', $document->language_id)->latest('id')->first()?->update(['is_current' => true]);
            }
        });
    }

    /**
     * Nome del file salvato: cv_{lingua}_{AAAAMMGG-hhmmss}.pdf
     */
    public static function storageName(string $languageCode, ?\DateTimeInterface $at = null): string
    {
        return sprintf('cv_%s_%s.pdf', $languageCode, ($at ?? now())->format('Ymd-His'));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Nome con cui il file viene scaricato, es. "CV Luca Racchetti - IT.pdf".
     */
    public function downloadName(): string
    {
        $pattern = (string) app(Settings::class)->get('cv.filename_pattern', 'CV {name} - {lang}.pdf');
        $language = $this->language->code;

        $name = strtr($pattern, [
            '{name}' => Profile::current()->full_name,
            '{lang}' => strtoupper(strtok($language, '-') ?: $language),
            '{locale}' => $language,
        ]);

        $name = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '', $name) ?? '');

        return str_ends_with(strtolower($name), '.pdf') ? $name : $name.'.pdf';
    }
}

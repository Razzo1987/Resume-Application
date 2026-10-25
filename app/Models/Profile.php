<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Profilo della persona (una sola riga).
 *
 * @property int $id
 * @property string $full_name
 * @property string|null $photo
 * @property Carbon|null $birth_date
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $country_code
 * @property Carbon|null $career_start_date
 */
class Profile extends Model implements Translatable
{
    use HasTranslations;

    /** @var list<string> */
    protected array $translatable = ['photo_alt', 'location', 'headlines', 'bio'];

    protected $fillable = [
        'full_name', 'photo', 'photo_alt', 'birth_date', 'email', 'phone',
        'location', 'country_code', 'headlines', 'bio', 'career_start_date',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'career_start_date' => 'date',
        ];
    }

    /**
     * Il profilo del sito (creato vuoto se non esiste ancora).
     */
    public static function current(): self
    {
        return self::query()->oldest('id')->first() ?? self::query()->create(['full_name' => '']);
    }

    /**
     * Qualifiche nella lingua indicata, una per riga.
     *
     * @return list<string>
     */
    public function headlineList(?string $locale = null): array
    {
        $lines = preg_split('/\R/', (string) $this->t('headlines', $locale)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }

    /**
     * Inizio della carriera: data indicata nel profilo, altrimenti la prima esperienza di lavoro.
     */
    public function careerStart(): ?Carbon
    {
        if ($this->career_start_date !== null) {
            return $this->career_start_date;
        }

        $first = Experience::query()->work()->visible()->min('start_date');

        return $first !== null ? Carbon::parse($first) : null;
    }
}

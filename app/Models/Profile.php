<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Profile of the person (a single row).
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
     * The site profile (created empty if it does not exist yet).
     */
    public static function current(): self
    {
        return self::query()->oldest('id')->first() ?? self::query()->create(['full_name' => '']);
    }

    /**
     * Headlines in the given language, one per line.
     *
     * @return list<string>
     */
    public function headlineList(?string $locale = null): array
    {
        $lines = preg_split('/\R/', (string) $this->t('headlines', $locale)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }

    /**
     * Career start: the date set in the profile, otherwise the first work experience.
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

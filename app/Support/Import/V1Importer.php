<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Enums\CertificationType;
use App\Enums\ExperienceKind;
use App\Enums\OrganizationType;
use App\Enums\SocialPlatform;
use App\Models\Certification;
use App\Models\CvDocument;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Language;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Redirect;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialLink;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use App\Support\Media\ImageOptimizer;
use App\Support\Settings\Settings;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

/**
 * Imports the content of Resume Application version 1 (one row per language, IT/EN pairs
 * linked by convention: odd id = first language, even id = second).
 *
 * Profile, social links, experiences (work and volunteering) with organizations and skills, projects
 * (linked to the experience their dates fall into), education, certifications with badges and PDFs,
 * redirects from the old file URLs. Images are optimized like those uploaded from the admin.
 */
class V1Importer
{
    /** @var list<string> */
    private array $log = [];

    /** @var array<string, Organization> */
    private array $organizations = [];

    private string $default;

    private string $files;

    public function __construct(
        private readonly ImageOptimizer $images,
        private readonly Locales $locales,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  string  $files  v1 public_html folder (images, attachment/, resume/)
     * @param  list<int>  $volunteering  v1 ids (of any language) of the volunteering experiences
     * @param  array<string, string>  $cvs  language => PDF of the current CV
     * @return list<string> report
     */
    public function import(ConnectionInterface $legacy, string $files, array $volunteering = [], array $cvs = [], bool $fresh = false): array
    {
        $this->log = [];
        $this->organizations = [];
        $this->files = rtrim($files, '/');
        $this->default = $this->locales->default();

        DB::transaction(function () use ($legacy, $volunteering, $cvs, $fresh): void {
            if ($fresh) {
                $this->wipe();
            }

            $this->profile($legacy);
            $skills = $this->skills($legacy);
            $experiences = $this->experiences($legacy, $skills, $volunteering);
            $this->projects($legacy, $experiences);
            $this->educations($legacy);
            $this->certifications($legacy);
            $this->resumes($cvs);
            $this->footer();
            $this->siteIcon();
        });

        return $this->log;
    }

    private function wipe(): void
    {
        foreach ([Project::class, Experience::class, Education::class, Certification::class, Skill::class, SocialLink::class, Organization::class] as $model) {
            $model::query()->get()->each->delete();
        }

        $this->note('Contenuti esistenti eliminati (--fresh)');
    }

    private function profile(ConnectionInterface $legacy): void
    {
        $rows = $legacy->table('profile')->get()->keyBy('language');
        $main = $this->primary($rows);

        if ($main === null) {
            $this->warn('Profilo: nessuna riga');

            return;
        }

        $images = $this->json($main->images ?? null);
        $profile = Profile::current();
        $headlines = $rows->map(fn (stdClass $row): string => implode("\n", array_filter(array_map('trim', explode(',', (string) $row->designation)))))->filter()->all();
        $locations = $this->translated($rows, 'address');

        $profile->fill([
            'full_name' => trim((string) $main->name),
            'photo_alt' => $this->translated($rows, fn (stdClass $row): string => (string) ($this->json($row->images ?? null)['profile_alt'] ?? '')),
            'birth_date' => $main->birthdate ?: null,
            'email' => $main->email ?: null,
            'phone' => $main->phone ?: null,
            'location' => $locations,
            'country_code' => collect($locations)->contains(fn (string $place): bool => (bool) preg_match('/\b(Ital[yi]a?|IT)\b/i', $place)) ? 'IT' : $profile->country_code,
            'headlines' => $headlines,
            'bio' => $this->translated($rows, 'information'),
            'career_start_date' => $main->first_work_experience ?: null,
        ]);

        if (is_string($images['profile'] ?? null) && ($photo = $this->image($images['profile'], 'profile', 800)) !== null) {
            $profile->photo = $photo;
        }

        $profile->save();

        SocialLink::query()->delete();
        $position = 0;

        foreach ($this->json($main->socials ?? null) as $platform => $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            SocialLink::query()->create([
                'platform' => (SocialPlatform::tryFrom(strtolower((string) $platform)) ?? SocialPlatform::Website)->value,
                'url' => $url,
                'sort_order' => ++$position * 10,
            ]);
        }

        $this->note("Profilo: {$profile->full_name}, {$position} profili social");
    }

    /**
     * @return array<int, int> v1 pair key => skill id
     */
    private function skills(ConnectionInterface $legacy): array
    {
        $categories = SkillCategory::query()->pluck('id', 'key');
        $map = [];
        $position = 0;

        foreach ($this->pairs($legacy->table('skills')->orderBy('id')->get()) as $key => $group) {
            $main = $this->primary($group);
            $category = match (Str::slug((string) $main?->type)) {
                'soft-skill', 'soft-skills' => 'soft-skills',
                'tool', 'tools' => 'tools',
                default => 'expertise',
            };

            $skill = Skill::query()->create([
                'skill_category_id' => $categories[$category] ?? $categories->first(),
                'name' => $this->translated($group, 'name'),
                'sort_order' => ++$position * 10,
            ]);

            $map[$key] = $skill->id;
        }

        $this->note('Competenze: '.count($map));

        return $map;
    }

    /**
     * @param  array<int, int>  $skills
     * @param  list<int>  $volunteering
     * @return Collection<int, Experience> by v1 pair key
     */
    private function experiences(ConnectionInterface $legacy, array $skills, array $volunteering): Collection
    {
        $volunteerKeys = array_map(fn (int $id): int => $this->pairKey($id), $volunteering);
        $experiences = collect();

        foreach ($this->pairs($legacy->table('experiences')->orderBy('id')->get()) as $key => $group) {
            $main = $this->primary($group);

            if ($main === null) {
                continue;
            }

            $icon = $this->json($main->images ?? null);
            $experience = Experience::query()->create([
                'kind' => in_array($key, $volunteerKeys, true) ? ExperienceKind::Volunteering : ExperienceKind::Work,
                'organization_id' => $this->organization((string) $main->company_name, $icon['icon'] ?? null, $main->link ?? null)?->id,
                'role' => $this->translated($group, 'job_title'),
                'location' => array_map(fn (string $place): string => $this->city($place), $this->translated($group, 'location')),
                'start_date' => $main->start,
                'end_date' => $main->end ?: null,
                'summary' => $this->translated($group, 'description'),
                'details' => $this->translated($group, 'details'),
            ]);

            $experiences[$key] = $experience;
        }

        // Skills: the rows of all languages, mapped back to the pairs (fixes cross-language links)
        $links = [];

        foreach ($legacy->table('experience_skill')->get() as $link) {
            $experience = $experiences[$this->pairKey((int) $link->id_experience)] ?? null;
            $skill = $skills[$this->pairKey((int) $link->id_skill)] ?? null;

            if ($experience !== null && $skill !== null) {
                $links[$experience->id][$skill] = true;
            }
        }

        foreach ($links as $experienceId => $skillIds) {
            $experiences->firstWhere('id', $experienceId)?->skills()->sync(array_keys($skillIds));
        }

        $volunteers = $experiences->where('kind', ExperienceKind::Volunteering)->count();
        $this->note('Esperienze: '.$experiences->count()." (di cui {$volunteers} di volontariato), ".count($links).' con competenze');

        return $experiences;
    }

    /**
     * @param  Collection<int, Experience>  $experiences
     */
    private function projects(ConnectionInterface $legacy, Collection $experiences): void
    {
        $work = $experiences->where('kind', ExperienceKind::Work);
        $count = 0;
        $linked = 0;

        foreach ($this->pairs($legacy->table('projects')->orderBy('id')->get()) as $group) {
            $main = $this->primary($group);

            if ($main === null) {
                continue;
            }

            $start = Carbon::parse($main->start);

            // Experience the project's start date falls into (the most recent one, if more than one)
            $experience = $work
                ->filter(fn (Experience $item): bool => $item->start_date->lte($start) && ($item->end_date === null || $item->end_date->gte($start)))
                ->sortByDesc(fn (Experience $item): int => $item->start_date->getTimestamp())
                ->first();

            $client = trim((string) $main->company) !== '' ? $this->organization((string) $main->company, null, null, OrganizationType::Client) : null;

            Project::query()->create([
                'experience_id' => $experience?->id,
                'client_id' => $client?->id,
                'title' => $this->translated($group, 'title'),
                'role' => $this->translated($group, 'role'),
                'sector' => $this->translated($group, 'sector'),
                'start_date' => $main->start,
                'end_date' => $main->end ?: null,
                'summary' => $this->translated($group, 'description'),
                'details' => $this->translated($group, 'details'),
            ]);

            $count++;
            $linked += $experience !== null ? 1 : 0;

            if ($experience === null) {
                $this->warn('Progetto "'.trim((string) $main->title).'": nessuna esperienza nelle sue date, da collegare a mano');
            }
        }

        $this->note("Progetti: {$count} ({$linked} collegati a un'esperienza)");
    }

    private function educations(ConnectionInterface $legacy): void
    {
        // No id: the languages of the same degree have the same dates
        $groups = $legacy->table('educations')->get()
            ->groupBy(fn (stdClass $row): string => $row->start.'|'.$row->end)
            ->map(fn (Collection $rows): Collection => $rows->keyBy('language'));

        foreach ($groups as $group) {
            $main = $this->primary($group);

            if ($main === null) {
                continue;
            }

            $icon = $this->json($main->images ?? null);

            Education::query()->create([
                'organization_id' => $this->organization((string) $main->institution_name, $icon['icon'] ?? null, null)?->id,
                'degree' => $this->translated($group, 'degree_type'),
                'field_of_study' => $this->translated($group, 'field_of_study'),
                'start_date' => $main->start ?: null,
                'end_date' => $main->end ?: null,
                'grade' => $main->grade_value !== null && $main->grade_value !== '' ? (string) $main->grade_value : null,
                'grade_scale' => $main->grade_scale !== null && $main->grade_scale !== '' ? (string) $main->grade_scale : null,
                'cum_laude' => (bool) $main->grade_laude,
                'summary' => $this->translated($group, 'description'),
                'details' => $this->translated($group, 'details'),
                'link' => $main->link ?: null,
            ]);
        }

        $this->note('Formazione: '.$groups->count());
    }

    private function certifications(ConnectionInterface $legacy): void
    {
        $count = 0;
        $files = 0;

        foreach ($this->pairs($legacy->table('certifications')->orderBy('id')->get()) as $group) {
            $main = $this->primary($group);

            if ($main === null) {
                continue;
            }

            $title = trim((string) $main->title);
            $dates = $group->pluck('date')->filter()->unique();

            if ($dates->count() > 1) {
                $this->warn("Certificazione \"{$title}\": date diverse tra le lingue ({$dates->implode(', ')}), usata {$main->date}");
            }

            // Link: local PDF (attachment/…, also with the duplicated path) or verification page
            $link = $group->pluck('link')->filter()->map(fn (string $value): string => (string) preg_replace('#^(attachment/)+#', 'attachment/', trim($value)));
            $local = $link->first(fn (string $value): bool => str_starts_with($value, 'attachment/'));
            $remote = $link->first(fn (string $value): bool => str_starts_with($value, 'http'));
            $file = $local !== null ? $this->pdf($local) : null;

            Certification::query()->create([
                'organization_id' => trim((string) $main->institution) !== '' ? $this->organization((string) $main->institution, null, null, OrganizationType::Certifier)?->id : null,
                'title' => $this->translated($group, 'title'),
                'type' => $this->certificationType($group)->value,
                'issued_on' => $main->date ?: null,
                'verification_url' => $remote,
                'file' => $file,
                'badge' => is_string($main->images) && $main->images !== '' ? $this->image($main->images, 'badges', 320) : null,
            ]);

            if ($file !== null) {
                $files++;

                // Old URLs of the PDF (including the broken one with attachment/attachment/)
                foreach (['/'.$local, '/attachment/'.$local] as $old) {
                    Redirect::query()->updateOrCreate(['from_path' => Redirect::normalizePath($old)], ['to_path' => '/uploads/'.$file, 'status_code' => 301, 'is_active' => true]);
                }
            }

            $count++;
        }

        $this->note("Certificazioni: {$count} ({$files} con PDF)");
    }

    /**
     * @param  array<string, string>  $cvs
     */
    private function resumes(array $cvs): void
    {
        foreach ($cvs as $code => $source) {
            $language = Language::query()->where('code', $code)->first();

            if ($language === null || ! is_file($source)) {
                $this->warn("CV {$code}: lingua o file non trovati ({$source})");

                continue;
            }

            $path = CvDocument::DIRECTORY.'/'.CvDocument::storageName($language->code);
            Storage::disk(CvDocument::DISK)->put($path, (string) file_get_contents($source));
            CvDocument::query()->where('language_id', $language->id)->update(['is_current' => false]);
            CvDocument::query()->create([
                'language_id' => $language->id,
                'path' => $path,
                'original_name' => basename($source),
                'is_current' => true,
                'note' => 'Importato dalla versione 1',
            ]);

            $this->note("CV {$code}: ".basename($source));
        }

        // Languages without a CV offer the one of the default language, as v1 did
        if ($cvs !== [] && Language::query()->where('is_active', true)->count() > count($cvs)) {
            $this->settings->set('cv.fallback_to_default', true);
            $this->note('Lingue senza CV: si scarica quello della lingua di default');
        }

        // Old CV URLs (resume/ folder) → download of the current CV
        $count = 0;

        foreach (glob($this->files.'/resume/*') ?: [] as $old) {
            $code = preg_match('/[_ -]EN\b/i', basename($old)) === 1 ? 'en-GB' : $this->default;
            $segment = app(SiteUrls::class)->cvDownloadPath($code);

            Redirect::query()->updateOrCreate(
                ['from_path' => Redirect::normalizePath('/resume/'.basename($old))],
                ['to_path' => $segment, 'status_code' => 301, 'is_active' => true],
            );
            $count++;
        }

        if ($count > 0) {
            $this->note("Redirect dei vecchi CV: {$count}");
        }
    }

    /**
     * From the full v1 address to the city only ("Piazza Tre Torri 2, 20145 Milano" → "Milano"),
     * when the address ends with postal code and city. Otherwise it stays as it is.
     */
    private function city(string $place): string
    {
        if (preg_match('/^(.*),\s*\d{4,5}\s+([^,\d]+)$/u', $place, $match) === 1) {
            // "…, 76131 Germania": the city is the last word before the comma
            if (preg_match('/^(italia|italy|germania|germany|deutschland|francia|france|spagna|spain|svizzera|switzerland)$/iu', trim($match[2])) === 1) {
                $words = preg_split('/\s+/u', trim($match[1])) ?: [];

                return (string) end($words);
            }

            return trim($match[2]);
        }

        if (str_contains($place, ',')) {
            $this->warn("Luogo \"{$place}\": indirizzo completo, da ridurre alla città in admin");
        }

        return $place;
    }

    /**
     * v1 footer texts (copyright, images, GDPR consent) from the languages/{language}.php files,
     * read as text (without executing them).
     */
    private function footer(): void
    {
        $texts = [];

        foreach (glob($this->files.'/languages/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            $lines = [];

            foreach (['FOOTER_COPYRIGHT', 'FOOTER_IMAGES', 'FOOTER_PRIVACY'] as $key) {
                if (preg_match("/'{$key}'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $match) === 1) {
                    $lines[] = stripcslashes($match[1]);
                }
            }

            if ($lines !== []) {
                $texts[basename($file, '.php')] = implode("\n", $lines);
            }
        }

        if ($texts !== [] && $this->settings->translated('footer.gdpr_text', $this->default) === null) {
            $this->settings->set('footer.gdpr_text', $texts);
            $this->note('Testo del footer (copyright e consenso GDPR): '.implode(', ', array_keys($texts)));
        }
    }

    /**
     * Site icon from the v1 favicon (the largest version), if there is not one already.
     */
    private function siteIcon(): void
    {
        $source = collect(['img/favicon/android-chrome-512x512.png', 'img/favicon/android-chrome-192x192.png', 'img/favicon/apple-touch-icon.png'])
            ->first(fn (string $file): bool => is_file($this->files.'/'.$file));

        if ($source === null || $this->settings->get('site.icon') !== null) {
            return;
        }

        $this->settings->set('site.icon', $this->images->store($this->files.'/'.$source, 'icon.png', 'uploads', 'site', 256, 'png'));
        $this->note('Icona del sito: '.basename($source));
    }

    private function organization(string $name, ?string $logo, ?string $website, ?OrganizationType $type = null): ?Organization
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $key = Str::lower($name);
        $type ??= match (true) {
            (bool) preg_match('/\b(università|universita|university|universidad|universität|université|ateneo|politecnico)\b/iu', $name) => OrganizationType::University,
            (bool) preg_match('/\b(liceo|istituto d\'istruzione|iis|itis|scuola)\b/i', $name) => OrganizationType::School,
            (bool) preg_match('/associazion|association/i', $name) => OrganizationType::Association,
            default => OrganizationType::Company,
        };

        $organization = $this->organizations[$key] ??= Organization::query()->whereRaw('lower(name) = ?', [$key])->first()
            ?? Organization::query()->create(['name' => $name, 'type' => $type]);

        $changed = false;

        if ($organization->logo === null && is_string($logo) && $logo !== '' && ($stored = $this->image($logo, 'logos', 240)) !== null) {
            $organization->logo = $stored;
            $changed = true;
        }

        if ($organization->website === null && is_string($website) && str_starts_with($website, 'http')) {
            $organization->website = $website;
            $changed = true;
        }

        if ($changed) {
            $organization->save();
        }

        return $organization;
    }

    private function image(string $relative, string $directory, int $size): ?string
    {
        $source = $this->files.'/'.ltrim(str_replace('\\/', '/', $relative), '/');

        if (! is_file($source)) {
            $this->warn("Immagine mancante: {$relative}");

            return null;
        }

        return $this->images->store($source, basename($source), 'uploads', $directory, $size);
    }

    private function pdf(string $relative): ?string
    {
        $source = $this->files.'/'.$relative;

        if (! is_file($source)) {
            $this->warn("PDF mancante: {$relative}");

            return null;
        }

        $name = Str::slug(pathinfo($source, PATHINFO_FILENAME)) ?: 'certificato';
        $path = "certificates/{$name}.pdf";
        $disk = Storage::disk('uploads');

        for ($n = 2; $disk->exists($path); $n++) {
            $path = "certificates/{$name}-{$n}.pdf";
        }

        $disk->put($path, (string) file_get_contents($source));

        return $path;
    }

    /**
     * @param  Collection<string, stdClass>  $group
     */
    private function certificationType(Collection $group): CertificationType
    {
        $text = Str::lower($group->pluck('type')->implode(' '));

        return match (true) {
            str_contains($text, 'accredit') => CertificationType::Accreditation,
            str_contains($text, 'certif') => CertificationType::Certification,
            str_contains($text, 'percorso'), str_contains($text, 'training') => CertificationType::TrainingPath,
            str_contains($text, 'ondemand'), str_contains($text, 'on demand'), str_contains($text, 'corso'), str_contains($text, 'course') => CertificationType::Course,
            str_contains($text, 'workshop') => CertificationType::Workshop,
            str_contains($text, 'esame'), str_contains($text, 'exam') => CertificationType::Exam,
            str_contains($text, 'badge') => CertificationType::Badge,
            default => CertificationType::Certification,
        };
    }

    /**
     * Rows grouped by language pair (ids 1-2, 3-4, …), each keyed by language.
     * If a pair has two rows in the same language, the rows stay separate.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return Collection<int, Collection<string, stdClass>>
     */
    private function pairs(Collection $rows): Collection
    {
        $groups = collect();

        foreach ($rows->groupBy(fn (stdClass $row): int => $this->pairKey((int) $row->id)) as $key => $group) {
            if ($group->pluck('language')->unique()->count() === $group->count()) {
                $groups[$key] = $group->keyBy('language');

                continue;
            }

            $this->warn("Coppia {$key}: righe della stessa lingua, importate separatamente");

            foreach ($group as $row) {
                $groups[-(int) $row->id] = collect([$row->language => $row]);
            }
        }

        return $groups;
    }

    private function pairKey(int $id): int
    {
        return intdiv($id + 1, 2);
    }

    /**
     * @param  Collection<string, stdClass>  $group
     */
    private function primary(Collection $group): ?stdClass
    {
        return $group[$this->default] ?? $group->first();
    }

    /**
     * Per-language values of a column (or of a function), without the empty ones.
     *
     * @param  Collection<string, stdClass>  $group
     * @param  string|callable(stdClass): string  $column
     * @return array<string, string>
     */
    private function translated(Collection $group, string|callable $column): array
    {
        return $group
            ->map(fn (stdClass $row): string => trim(is_string($column) ? (string) ($row->{$column} ?? '') : $column($row)))
            ->filter(fn (string $value): bool => $value !== '')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function note(string $line): void
    {
        $this->log[] = $line;
    }

    private function warn(string $line): void
    {
        if (! in_array("⚠ {$line}", $this->log, true)) {
            $this->log[] = "⚠ {$line}";
        }
    }
}

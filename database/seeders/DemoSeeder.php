<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\CefrLevel;
use App\Enums\CertificationType;
use App\Enums\ExperienceKind;
use App\Enums\HighlightDisplay;
use App\Enums\HighlightSource;
use App\Enums\OrganizationType;
use App\Enums\PublicationType;
use App\Enums\SocialPlatform;
use App\Models\Article;
use App\Models\Certification;
use App\Models\CvDocument;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Highlight;
use App\Models\Interest;
use App\Models\Language;
use App\Models\LanguageSkill;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialLink;
use App\Support\Media\ImageOptimizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * CV dimostrativo di "John Doe" in italiano e inglese, per provare il progetto subito dopo l'installazione.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        Profile::current()->update([
            'full_name' => 'John Doe',
            'photo' => self::samplePhoto(),
            'photo_alt' => ['it-IT' => 'Foto di John Doe', 'en-GB' => 'Photo of John Doe'],
            'email' => 'john.doe@example.com',
            'phone' => '+39 000 000 0000',
            'country_code' => 'IT',
            'location' => ['it-IT' => 'Milano', 'en-GB' => 'Milan'],
            'headlines' => [
                'it-IT' => "Data Governance\nData Quality\nData Strategy",
                'en-GB' => "Data Governance\nData Quality\nData Strategy",
            ],
            'bio' => [
                'it-IT' => '<p>Consulente con oltre dieci anni di esperienza in progetti di governo e qualità dei dati.</p>',
                'en-GB' => '<p>Consultant with more than ten years of experience in data governance and data quality projects.</p>',
            ],
        ]);

        SocialLink::query()->create(['platform' => SocialPlatform::LinkedIn, 'url' => 'https://www.linkedin.com/in/example', 'sort_order' => 10]);
        SocialLink::query()->create(['platform' => SocialPlatform::GitHub, 'url' => 'https://github.com/example', 'sort_order' => 20]);

        $acme = Organization::query()->create(['name' => 'Acme Consulting', 'type' => OrganizationType::Company, 'website' => 'https://example.com']);
        $globex = Organization::query()->create(['name' => 'Globex Energy', 'type' => OrganizationType::Client]);
        $university = Organization::query()->create(['name' => 'Università di Esempio', 'type' => OrganizationType::University]);
        $certifier = Organization::query()->create(['name' => 'Data Academy', 'type' => OrganizationType::Certifier]);
        $association = Organization::query()->create(['name' => 'Associazione Esempio', 'type' => OrganizationType::Association]);

        $tools = SkillCategory::query()->where('key', 'tools')->firstOrFail();
        $expertise = SkillCategory::query()->where('key', 'expertise')->firstOrFail();
        $soft = SkillCategory::query()->where('key', 'soft-skills')->firstOrFail();

        $governance = Skill::query()->create(['skill_category_id' => $expertise->id, 'name' => ['it-IT' => 'Data Governance', 'en-GB' => 'Data Governance'], 'is_featured' => true]);
        $quality = Skill::query()->create(['skill_category_id' => $expertise->id, 'name' => ['it-IT' => 'Qualità dei dati', 'en-GB' => 'Data Quality'], 'is_featured' => true]);
        $sql = Skill::query()->create(['skill_category_id' => $tools->id, 'name' => ['it-IT' => 'SQL', 'en-GB' => 'SQL']]);
        $teamwork = Skill::query()->create(['skill_category_id' => $soft->id, 'name' => ['it-IT' => 'Lavoro di squadra', 'en-GB' => 'Teamwork']]);

        $current = Experience::query()->create([
            'kind' => ExperienceKind::Work,
            'organization_id' => $acme->id,
            'role' => ['it-IT' => 'Data Governance Manager', 'en-GB' => 'Data Governance Manager'],
            'location' => ['it-IT' => 'Milano', 'en-GB' => 'Milan'],
            'start_date' => '2021-01-01',
            'summary' => [
                'it-IT' => '<p>Guido i progetti di Data Governance per clienti del settore energetico.</p>',
                'en-GB' => '<p>I lead data governance projects for energy-sector clients.</p>',
            ],
        ]);
        $current->skills()->attach([$governance->id, $quality->id, $teamwork->id]);

        $previous = Experience::query()->create([
            'kind' => ExperienceKind::Work,
            'organization_id' => $acme->id,
            'role' => ['it-IT' => 'Sviluppatore BI', 'en-GB' => 'BI Developer'],
            'start_date' => '2016-03-01',
            'end_date' => '2020-12-31',
            'summary' => [
                'it-IT' => '<p>Sviluppo di data warehouse e processi ETL.</p>',
                'en-GB' => '<p>Data warehouse and ETL development.</p>',
            ],
        ]);
        $previous->skills()->attach([$sql->id]);

        Experience::query()->create([
            'kind' => ExperienceKind::Volunteering,
            'organization_id' => $association->id,
            'role' => ['it-IT' => 'Docente volontario', 'en-GB' => 'Volunteer teacher'],
            'start_date' => '2014-01-01',
            'end_date' => '2017-01-31',
            'summary' => [
                'it-IT' => '<p>Corsi di informatica di base per adulti.</p>',
                'en-GB' => '<p>Basic computer courses for adults.</p>',
            ],
        ]);

        foreach ([
            ['Data Catalog e lineage', 'Data catalog and lineage', '2023-01-01', null],
            ['Regole di Data Quality', 'Data quality rules', '2022-01-01', '2022-12-31'],
            ['Data warehouse regolamentare', 'Regulatory data warehouse', '2018-01-01', '2020-06-30'],
        ] as [$it, $en, $start, $end]) {
            $project = Project::query()->create([
                'experience_id' => $start >= '2021-01-01' ? $current->id : $previous->id,
                'client_id' => $globex->id,
                'title' => ['it-IT' => $it, 'en-GB' => $en],
                'role' => ['it-IT' => 'Responsabile di progetto', 'en-GB' => 'Project lead'],
                'sector' => ['it-IT' => 'Energia', 'en-GB' => 'Energy'],
                'start_date' => $start,
                'end_date' => $end,
                'summary' => ['it-IT' => "<p>{$it}: obiettivi, attività e risultati.</p>", 'en-GB' => "<p>{$en}: goals, activities and results.</p>"],
            ]);
            $project->skills()->attach([$governance->id]);
        }

        // Un progetto con il testo di dettaglio: ha una pagina dedicata
        Project::query()->orderBy('id')->firstOrFail()->update([
            'details' => [
                'it-IT' => '<h2>Contesto</h2><p>Catalogo dei dati aziendali con lineage automatico dai sistemi sorgente ai report.</p><h2>Risultati</h2><ul><li>1.200 dataset documentati</li><li>Tempi di analisi d\'impatto ridotti del 60%</li></ul>',
                'en-GB' => '<h2>Context</h2><p>Enterprise data catalogue with automated lineage from source systems to reports.</p><h2>Results</h2><ul><li>1,200 datasets documented</li><li>Impact analysis time cut by 60%</li></ul>',
            ],
        ]);

        Education::query()->create([
            'organization_id' => $university->id,
            'degree' => ['it-IT' => 'Laurea Magistrale', 'en-GB' => 'Master\'s Degree'],
            'field_of_study' => ['it-IT' => 'Informatica', 'en-GB' => 'Computer Science'],
            'start_date' => '2011-10-01',
            'end_date' => '2014-07-15',
            'grade' => '110',
            'grade_scale' => '110',
            'cum_laude' => true,
            'thesis_title' => ['it-IT' => 'Qualità dei dati aperti', 'en-GB' => 'Open data quality'],
            'details' => [
                'it-IT' => '<p>Esami principali: Basi di dati, Data mining, Statistica.</p>',
                'en-GB' => '<p>Main exams: Databases, Data mining, Statistics.</p>',
            ],
        ]);

        Certification::query()->create([
            'organization_id' => $certifier->id,
            'title' => ['it-IT' => 'Data Governance Professional', 'en-GB' => 'Data Governance Professional'],
            'type' => CertificationType::Certification,
            'issued_on' => '2023-05-10',
            'verification_url' => 'https://example.com/verify/123',
            'badge' => self::sampleBadge(320, 320),
        ]);

        $english = Certification::query()->create([
            'organization_id' => $certifier->id,
            'title' => ['it-IT' => 'Certificazione di inglese', 'en-GB' => 'English certificate'],
            'type' => CertificationType::Exam,
            'issued_on' => '2019-06-01',
            'badge' => self::sampleBadge(480, 120), // i badge non sono sempre quadrati
        ]);

        LanguageSkill::query()->create(['language_code' => 'it', 'is_native' => true, 'sort_order' => 10]);
        LanguageSkill::query()->create([
            'language_code' => 'en',
            'oral' => CefrLevel::C1,
            'written' => CefrLevel::C1,
            'comprehension' => CefrLevel::C2,
            'certification_id' => $english->id,
            'note' => ['it-IT' => 'Uso professionale quotidiano', 'en-GB' => 'Daily professional use'],
            'sort_order' => 20,
        ]);

        foreach ([['Fotografia di paesaggio', 'Landscape photography', 'camera'], ['Corsa in montagna', 'Trail running', 'running'], ['Scacchi', 'Chess', 'chess']] as $position => [$it, $en, $icon]) {
            Interest::query()->create(['name' => ['it-IT' => $it, 'en-GB' => $en], 'icon' => $icon, 'sort_order' => ($position + 1) * 10]);
        }

        Highlight::query()->create([
            'source' => HighlightSource::Manual,
            'value' => 460,
            'display' => HighlightDisplay::Plus,
            'label' => ['it-IT' => 'regole di Data Quality migrate', 'en-GB' => 'data quality rules migrated'],
            'icon' => 'chart',
            'sort_order' => 30,
        ]);

        Publication::query()->create([
            'type' => PublicationType::Thesis,
            'title' => ['it-IT' => 'Qualità dei dati aperti', 'en-GB' => 'Open data quality'],
            'venue' => ['it-IT' => 'Università di Esempio', 'en-GB' => 'Example University'],
            'published_on' => '2014-07-15',
            'language_code' => 'it',
        ]);

        Article::query()->create([
            'title' => ['it-IT' => 'Perché la Data Governance conviene', 'en-GB' => 'Why data governance pays off'],
            'excerpt' => ['it-IT' => 'Tre benefici concreti.', 'en-GB' => 'Three concrete benefits.'],
            'body' => [
                'it-IT' => '<p>Un programma di Data Governance ben impostato si ripaga in fretta.</p><h2>1. Meno tempo perso a cercare i dati</h2><p>Un catalogo condiviso dice dove sono i dati, chi li gestisce e come si usano.</p><h2>2. Decisioni basate su numeri affidabili</h2><p>Regole di qualità misurate ogni giorno rendono i report credibili.</p><h2>3. Conformità senza affanno</h2><p>Lineage e responsabilità chiare semplificano audit e richieste del regolatore.</p>',
                'en-GB' => '<p>A well-designed data governance programme pays for itself quickly.</p><h2>1. Less time spent looking for data</h2><p>A shared catalogue tells where data lives, who owns it and how to use it.</p><h2>2. Decisions based on reliable numbers</h2><p>Quality rules measured every day make reports credible.</p><h2>3. Compliance without the rush</h2><p>Clear lineage and ownership make audits and regulator requests simpler.</p>',
            ],
            'cover_image' => self::sampleCover(),
            'cover_alt' => ['it-IT' => 'Illustrazione astratta con barre e linee', 'en-GB' => 'Abstract illustration with bars and lines'],
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        // Un CV PDF di esempio per lingua, come se fosse stato caricato dall'admin
        foreach (Language::query()->whereIn('code', ['it-IT', 'en-GB'])->get() as $language) {
            $path = CvDocument::DIRECTORY.'/'.CvDocument::storageName($language->code);

            Storage::disk(CvDocument::DISK)->put($path, self::samplePdf('John Doe - CV ('.$language->code.')'));

            CvDocument::query()->create([
                'language_id' => $language->id,
                'path' => $path,
                'original_name' => 'cv-john-doe-'.$language->code.'.pdf',
                'is_current' => true,
                'note' => 'Demo',
            ]);
        }
    }

    /**
     * Foto profilo di esempio: una sagoma disegnata con GD, salvata come il resto delle immagini (WebP).
     */
    public static function samplePhoto(): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $size = 600;
        $image = imagecreatetruecolor($size, $size);

        if ($image === false) {
            return null;
        }

        imagefill($image, 0, 0, (int) imagecolorallocate($image, 219, 228, 238));
        $figure = (int) imagecolorallocate($image, 122, 146, 172);
        imagefilledellipse($image, 300, 235, 210, 230, $figure);
        imagefilledellipse($image, 300, 600, 460, 380, $figure);

        $source = tempnam(sys_get_temp_dir(), 'demo-photo');
        imagepng($image, $source);

        try {
            return app(ImageOptimizer::class)->store((string) $source, 'john-doe.png', 'uploads', 'profile', 800);
        } finally {
            @unlink((string) $source);
        }
    }

    /**
     * Copertina di esempio per l'articolo: barre e linee disegnate con GD.
     */
    /**
     * Badge di esempio (forma piena con un bordo), quadrato o rettangolare.
     */
    public static function sampleBadge(int $width, int $height): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return null;
        }

        imagefill($image, 0, 0, (int) imagecolorallocate($image, 29, 79, 134));
        imagefilledrectangle($image, 8, 8, $width - 9, $height - 9, (int) imagecolorallocate($image, 244, 201, 93));
        imagefilledrectangle($image, 20, 20, $width - 21, $height - 21, (int) imagecolorallocate($image, 255, 255, 255));

        $source = tempnam(sys_get_temp_dir(), 'demo-badge');
        imagepng($image, $source);

        try {
            return app(ImageOptimizer::class)->store((string) $source, "badge-{$width}x{$height}.png", 'uploads', 'badges', 480);
        } finally {
            @unlink((string) $source);
        }
    }

    public static function sampleCover(): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor(1200, 630);

        if ($image === false) {
            return null;
        }

        imagefill($image, 0, 0, (int) imagecolorallocate($image, 22, 50, 79));
        $colors = [[244, 201, 93], [123, 167, 214], [219, 228, 238]];

        foreach ([180, 300, 240, 420, 360, 470] as $index => $height) {
            [$red, $green, $blue] = $colors[$index % 3];
            imagefilledrectangle($image, 150 + $index * 150, 560 - $height, 230 + $index * 150, 560, (int) imagecolorallocate($image, $red, $green, $blue));
        }

        imagesetthickness($image, 6);
        imageline($image, 120, 520, 1080, 120, (int) imagecolorallocate($image, 255, 255, 255));

        $source = tempnam(sys_get_temp_dir(), 'demo-cover');
        imagepng($image, $source);

        try {
            return app(ImageOptimizer::class)->store((string) $source, 'data-governance.png', 'uploads', 'articles', 2000);
        } finally {
            @unlink((string) $source);
        }
    }

    /**
     * PDF minimo valido di una pagina A4 con una riga di testo (solo ASCII).
     */
    public static function samplePdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 24 Tf 72 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}

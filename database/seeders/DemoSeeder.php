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
 * CV dimostrativo di "John Doe", sviluppatore web (profilo inventato e generico), in italiano e inglese,
 * per provare il progetto subito dopo l'installazione: tutte le sezioni, un progetto con pagina dedicata, un articolo.
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
            'location' => ['it-IT' => 'Torino', 'en-GB' => 'Turin'],
            'headlines' => [
                'it-IT' => "Sviluppatore full-stack\nTech lead\nAccessibilità web",
                'en-GB' => "Full-stack developer\nTech lead\nWeb accessibility",
            ],
            'bio' => [
                'it-IT' => '<p>Sviluppo applicazioni web da dodici anni, dal database all\'interfaccia. Mi piace costruire servizi veloci e accessibili a tutti e aiutare i colleghi più giovani a crescere.</p>',
                'en-GB' => '<p>I have been building web applications for twelve years, from the database to the interface. I like making services that are fast and accessible to everyone, and helping junior colleagues grow.</p>',
            ],
        ]);

        SocialLink::query()->create(['platform' => SocialPlatform::LinkedIn, 'url' => 'https://www.linkedin.com/in/example', 'sort_order' => 10]);
        SocialLink::query()->create(['platform' => SocialPlatform::GitHub, 'url' => 'https://github.com/example', 'sort_order' => 20]);

        $company = Organization::query()->create(['name' => 'Brightline Software', 'type' => OrganizationType::Company, 'website' => 'https://example.com']);
        $client = Organization::query()->create(['name' => 'Verde Mobilità', 'type' => OrganizationType::Client]);
        $university = Organization::query()->create(['name' => 'Politecnico di Esempio', 'type' => OrganizationType::University]);
        $certifier = Organization::query()->create(['name' => 'Web Skills Academy', 'type' => OrganizationType::Certifier]);
        $association = Organization::query()->create(['name' => 'Associazione Codice Aperto', 'type' => OrganizationType::Association]);

        $tools = SkillCategory::query()->where('key', 'tools')->firstOrFail();
        $expertise = SkillCategory::query()->where('key', 'expertise')->firstOrFail();
        $soft = SkillCategory::query()->where('key', 'soft-skills')->firstOrFail();

        $web = Skill::query()->create(['skill_category_id' => $expertise->id, 'name' => ['it-IT' => 'Sviluppo web', 'en-GB' => 'Web development'], 'is_featured' => true]);
        $accessibility = Skill::query()->create(['skill_category_id' => $expertise->id, 'name' => ['it-IT' => 'Accessibilità', 'en-GB' => 'Accessibility'], 'is_featured' => true]);
        $php = Skill::query()->create(['skill_category_id' => $tools->id, 'name' => ['it-IT' => 'PHP e Laravel', 'en-GB' => 'PHP and Laravel']]);
        $javascript = Skill::query()->create(['skill_category_id' => $tools->id, 'name' => ['it-IT' => 'JavaScript', 'en-GB' => 'JavaScript']]);
        $sql = Skill::query()->create(['skill_category_id' => $tools->id, 'name' => ['it-IT' => 'SQL', 'en-GB' => 'SQL']]);
        $mentoring = Skill::query()->create(['skill_category_id' => $soft->id, 'name' => ['it-IT' => 'Mentoring', 'en-GB' => 'Mentoring']]);

        $current = Experience::query()->create([
            'kind' => ExperienceKind::Work,
            'organization_id' => $company->id,
            'role' => ['it-IT' => 'Tech Lead', 'en-GB' => 'Tech Lead'],
            'location' => ['it-IT' => 'Torino', 'en-GB' => 'Turin'],
            'start_date' => '2021-01-01',
            'summary' => [
                'it-IT' => '<p>Guido un team di cinque persone che sviluppa piattaforme web per aziende di trasporti e turismo.</p>',
                'en-GB' => '<p>I lead a team of five building web platforms for transport and tourism companies.</p>',
            ],
        ]);
        $current->skills()->attach([$web->id, $accessibility->id, $mentoring->id]);

        $previous = Experience::query()->create([
            'kind' => ExperienceKind::Work,
            'organization_id' => $company->id,
            'role' => ['it-IT' => 'Sviluppatore full-stack', 'en-GB' => 'Full-stack developer'],
            'start_date' => '2016-03-01',
            'end_date' => '2020-12-31',
            'summary' => [
                'it-IT' => '<p>Applicazioni web e API con PHP, JavaScript e MySQL.</p>',
                'en-GB' => '<p>Web applications and APIs with PHP, JavaScript and MySQL.</p>',
            ],
        ]);
        $previous->skills()->attach([$php->id, $javascript->id, $sql->id]);

        Experience::query()->create([
            'kind' => ExperienceKind::Volunteering,
            'organization_id' => $association->id,
            'role' => ['it-IT' => 'Mentore volontario', 'en-GB' => 'Volunteer mentor'],
            'start_date' => '2015-01-01',
            'end_date' => '2018-06-30',
            'summary' => [
                'it-IT' => '<p>Laboratori di programmazione per ragazzi delle scuole medie.</p>',
                'en-GB' => '<p>Coding workshops for middle school students.</p>',
            ],
        ]);

        foreach ([
            ['Portale prenotazioni', 'Booking portal', '2023-01-01', null],
            ['App per i biglietti', 'Ticketing app', '2022-01-01', '2022-12-31'],
            ['Nuovo sito accessibile', 'Accessible website redesign', '2018-01-01', '2020-06-30'],
        ] as [$it, $en, $start, $end]) {
            $project = Project::query()->create([
                'experience_id' => $start >= '2021-01-01' ? $current->id : $previous->id,
                'client_id' => $client->id,
                'title' => ['it-IT' => $it, 'en-GB' => $en],
                'role' => ['it-IT' => 'Responsabile tecnico', 'en-GB' => 'Technical lead'],
                'sector' => ['it-IT' => 'Trasporti', 'en-GB' => 'Transport'],
                'start_date' => $start,
                'end_date' => $end,
                'summary' => ['it-IT' => "<p>{$it}: obiettivi, attività e risultati.</p>", 'en-GB' => "<p>{$en}: goals, activities and results.</p>"],
            ]);
            $project->skills()->attach([$web->id, $php->id]);
        }

        // Un progetto con il testo di dettaglio: ha una pagina dedicata
        Project::query()->orderBy('id')->firstOrFail()->update([
            'details' => [
                'it-IT' => '<h2>Contesto</h2><p>Prenotazioni online per una rete di 40 stazioni di noleggio bici, con pagamento e biglietto sul telefono.</p><h2>Risultati</h2><ul><li>300.000 prenotazioni nel primo anno</li><li>Tempo medio per prenotare sceso da 5 a 2 minuti</li><li>Conforme alle WCAG 2.1 livello AA</li></ul>',
                'en-GB' => '<h2>Context</h2><p>Online booking for a network of 40 bike rental stations, with payment and ticket on the phone.</p><h2>Results</h2><ul><li>300,000 bookings in the first year</li><li>Average booking time down from 5 to 2 minutes</li><li>Compliant with WCAG 2.1 level AA</li></ul>',
            ],
        ]);

        Education::query()->create([
            'organization_id' => $university->id,
            'degree' => ['it-IT' => 'Laurea Magistrale', 'en-GB' => 'Master\'s Degree'],
            'field_of_study' => ['it-IT' => 'Ingegneria informatica', 'en-GB' => 'Computer Engineering'],
            'start_date' => '2011-10-01',
            'end_date' => '2014-07-15',
            'grade' => '108',
            'grade_scale' => '110',
            'thesis_title' => ['it-IT' => 'Interfacce web accessibili', 'en-GB' => 'Accessible web interfaces'],
            'details' => [
                'it-IT' => '<p>Esami principali: Basi di dati, Reti, Interazione uomo-macchina.</p>',
                'en-GB' => '<p>Main exams: Databases, Networks, Human-computer interaction.</p>',
            ],
        ]);

        Certification::query()->create([
            'organization_id' => $certifier->id,
            'title' => ['it-IT' => 'Web Accessibility Specialist', 'en-GB' => 'Web Accessibility Specialist'],
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

        foreach ([['Fotografia', 'Photography', 'camera'], ['Bici e cicloturismo', 'Cycling and bike touring', 'cycling'], ['Cucina', 'Cooking', 'cooking']] as $position => [$it, $en, $icon]) {
            Interest::query()->create(['name' => ['it-IT' => $it, 'en-GB' => $en], 'icon' => $icon, 'sort_order' => ($position + 1) * 10]);
        }

        Highlight::query()->create([
            'source' => HighlightSource::Manual,
            'value' => 40,
            'display' => HighlightDisplay::Plus,
            'label' => ['it-IT' => 'siti e applicazioni pubblicati', 'en-GB' => 'websites and apps released'],
            'icon' => 'chart',
            'sort_order' => 30,
        ]);

        Publication::query()->create([
            'type' => PublicationType::Thesis,
            'title' => ['it-IT' => 'Interfacce web accessibili', 'en-GB' => 'Accessible web interfaces'],
            'venue' => ['it-IT' => 'Politecnico di Esempio', 'en-GB' => 'Example Polytechnic'],
            'published_on' => '2014-07-15',
            'language_code' => 'it',
        ]);

        Article::query()->create([
            'title' => ['it-IT' => 'Tre regole per un sito accessibile', 'en-GB' => 'Three rules for an accessible website'],
            'excerpt' => ['it-IT' => 'Piccole scelte che aiutano tutti.', 'en-GB' => 'Small choices that help everyone.'],
            'body' => [
                'it-IT' => '<p>Rendere un sito accessibile non richiede un progetto a parte: bastano alcune abitudini, fin dal primo giorno.</p><h2>1. Testi alternativi che dicono qualcosa</h2><p>Un\'immagine senza descrizione per chi usa un lettore di schermo non esiste.</p><h2>2. Contrasto e dimensioni leggibili</h2><p>Un testo grigio chiaro su bianco è elegante, ma metà dei lettori fatica a leggerlo.</p><h2>3. Tutto si usa anche con la tastiera</h2><p>Menu, moduli e finestre devono funzionare senza mouse, con un indicatore di focus ben visibile.</p>',
                'en-GB' => '<p>Making a website accessible does not need a separate project: a few habits from day one are enough.</p><h2>1. Alternative texts that say something</h2><p>For a screen reader user, an image without a description does not exist.</p><h2>2. Readable contrast and sizes</h2><p>Light grey text on white looks elegant, but half of your readers struggle with it.</p><h2>3. Everything works with the keyboard</h2><p>Menus, forms and dialogs must work without a mouse, with a clearly visible focus indicator.</p>',
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
            return app(ImageOptimizer::class)->store((string) $source, 'accessibility.png', 'uploads', 'articles', 2000);
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

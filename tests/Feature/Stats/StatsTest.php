<?php

use App\Filament\Pages\Statistics;
use App\Models\Article;
use App\Models\Project;
use App\Models\StatAdminIp;
use App\Models\StatVisit;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Stats\Aggregator;
use App\Support\Stats\GeoIp;
use App\Support\Stats\GeoIpUpdater;
use App\Support\Stats\Ip;
use App\Support\Stats\StatsQuery;
use App\Support\Stats\WorldMap;
use App\Support\System\Backup;
use Database\Seeders\DemoStatsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
| Cookieless visit statistics: collection (bots, previews and the administrator excluded, shortened IP), origin
| from the GeoLite databases (fake, in tests/Fixtures/geoip), daily totals, admin page, database download.
*/

const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

beforeEach(function () {
    seedDemo();
    Http::preventStrayRequests();

    $this->storage = sys_get_temp_dir().'/resume-stats-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->storage}/app/private/geoip");
    copy(base_path('tests/Fixtures/geoip/GeoLite2-City-Test.mmdb'), "{$this->storage}/app/private/geoip/GeoLite2-City.mmdb");
    copy(base_path('tests/Fixtures/geoip/GeoLite2-ASN-Test.mmdb'), "{$this->storage}/app/private/geoip/GeoLite2-ASN.mmdb");
    $this->app->instance(GeoIp::class, new GeoIp($this->storage));

    app(Settings::class)->set('stats.enabled', true);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

function pageView(string $path, array $server = []): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => '93.44.12.7', 'HTTP_USER_AGENT' => CHROME, ...$server])->get($path);
}

it('shortens IP addresses', function (string $ip, string $short) {
    expect(Ip::truncate($ip))->toBe($short);
})->with([
    ['93.44.12.7', '93.44.12.x'],
    ['2a01:4f8:c0c:1234::1', '2a01:4f8:c0c::'],
    ['not an ip', 'x'],
]);

it('records a page view without cookies, with source, campaign, device and place', function () {
    $response = pageView('/it-IT?utm_source=linkedin&utm_medium=social&utm_campaign=cv-2026&fbclid=IwAR123', ['HTTP_REFERER' => 'https://www.linkedin.com/feed/']);

    expect($response->headers->getCookies())->toBeEmpty();

    $visit = StatVisit::query()->sole();

    expect($visit->page)->toBe('cv')
        ->and($visit->locale)->toBe('it-IT')
        ->and($visit->referrer_host)->toBe('linkedin.com')
        ->and([$visit->utm_source, $visit->utm_medium, $visit->utm_campaign])->toBe(['linkedin', 'social', 'cv-2026'])
        ->and($visit->click_source)->toBe('facebook')
        ->and([$visit->device, $visit->os, $visit->browser, $visit->browser_version])->toBe(['desktop', 'Windows', 'Chrome', '130'])
        ->and([$visit->country, $visit->region, $visit->city, $visit->as_org, $visit->asn])->toBe(['IT', 'Lombardy', 'Milan', 'Telecom Italia', 3269])
        ->and($visit->ip)->toBe('93.44.12.x')
        ->and(json_encode($visit->toArray()))->not->toContain('IwAR123')->not->toContain('93.44.12.7');
});

it('recognises detail pages, the blog, the privacy page and CV downloads, also from the page cache', function () {
    $project = Project::query()->firstOrFail();
    $article = Article::query()->firstOrFail();

    pageView('/en-GB/projects/'.$project->t('slug', 'en-GB'));
    pageView('/en-GB/projects/'.$project->t('slug', 'en-GB'))->assertHeader('X-Page-Cache', 'HIT');
    pageView('/it-IT/blog/'.$article->t('slug', 'it-IT'), ['HTTP_USER_AGENT' => IPHONE, 'REMOTE_ADDR' => '2a01:4f8:c0c::1']);
    pageView('/it-IT/blog');
    pageView('/it-IT/privacy');
    pageView('/it-IT/cv');

    expect(StatVisit::query()->orderBy('id')->pluck('page')->all())->toBe(['project:'.$project->id, 'project:'.$project->id, 'article:'.$article->id, 'blog', 'privacy', 'cv-download'])
        ->and(StatVisit::query()->where('page', 'article:'.$article->id)->sole())
        ->device->toBe('smartphone')->os->toBe('iOS')->country->toBe('DE')->ip->toBe('2a01:4f8:c0c::');
});

it('does not count bots, previews, errors, other files or visits while statistics are off', function () {
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
    pageView('/it-IT', ['HTTP_SEC_PURPOSE' => 'prefetch']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => '']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'curl/8.5.0']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'python-requests/2.32.3']);
    pageView('/it-IT/pagina-inesistente');
    pageView('/sitemap.xml');
    pageView('/it-IT/feed.xml');
    pageView('/');

    app(Settings::class)->set('stats.enabled', false);
    pageView('/it-IT');

    expect(StatVisit::query()->count())->toBe(0);
});

it('does not count the administrator, from the address used for the admin, for the chosen days', function () {
    $this->actingAs(User::factory()->create())->withServerVariables(['REMOTE_ADDR' => '93.44.12.7'])->get('/admin')->assertOk();
    auth()->logout();

    expect(StatAdminIp::query()->sole()->ip)->toBe('93.44.12.7');

    pageView('/it-IT');
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']);

    expect(StatVisit::query()->pluck('ip')->all())->toBe(['81.2.69.x']);

    $this->travel(15)->days();
    pageView('/it-IT');

    expect(StatVisit::query()->count())->toBe(2);
});

it('sums the visits by day, page and dimension, and keeps the totals after deleting old visits', function () {
    $project = Project::query()->firstOrFail();

    pageView('/it-IT', ['HTTP_REFERER' => 'https://www.google.com/']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => IPHONE, 'REMOTE_ADDR' => '81.2.69.160']);
    pageView('/en-GB/projects/'.$project->t('slug', 'en-GB'), ['REMOTE_ADDR' => '2a01:4f8:c0c::1']);
    pageView('/it-IT/cv');

    app(Aggregator::class)->aggregate(today());
    $query = StatsQuery::forPeriod('30');

    expect($query->totals())->toBe(['views' => 3, 'downloads' => 1, 'countries' => 3, 'pages' => 2])
        ->and($query->pages())->toBe(['cv' => 2, 'project:'.$project->id => 1])
        ->and($query->top('country'))->toEqual(['IT' => 1, 'GB' => 1, 'DE' => 1])
        ->and($query->top('referrer'))->toBe(['google.com' => 1])
        ->and($query->top('device'))->toBe(['desktop' => 2, 'smartphone' => 1])
        ->and(collect($query->cities())->pluck('city')->sort()->values()->all())->toBe(['London', 'Milan', 'Nuremberg'])
        ->and(array_sum(StatsQuery::forPeriod('7', 'cv')->timeline()['views']))->toBe(2)
        ->and(StatsQuery::forPeriod('7', StatsQuery::DOWNLOAD)->totals()['downloads'])->toBe(1);

    // After the retention period the visits are deleted, the totals stay
    app(Settings::class)->set('stats.retention_months', 1);
    $this->travel(40)->days();
    app(Aggregator::class)->run();

    expect(StatVisit::query()->count())->toBe(0)
        ->and(StatsQuery::forPeriod('90')->totals()['views'])->toBe(3);
});

it('draws the world map with the countries and cities of the visits', function () {
    $svg = (string) app(WorldMap::class)->render(['IT' => 10, 'DE' => 2], [['label' => 'Milan (IT)', 'latitude' => 45.46, 'longitude' => 9.19, 'views' => 10]], 'it');

    expect($svg)->toStartWith('<svg class="resume-map"')
        ->toContain('<title>Italia: 10</title>')
        ->toContain('class="resume-map-visited" fill-opacity="1"')
        ->toContain('<title>Milan (IT): 10</title>')
        ->and(substr_count($svg, '<path'))->toBeGreaterThan(150);
});

it('shows the statistics page with charts, map, lists and the latest visits, also for one page', function () {
    $project = Project::query()->firstOrFail();
    pageView('/it-IT');
    pageView('/en-GB/projects/'.$project->t('slug', 'en-GB'), ['HTTP_REFERER' => 'https://news.ycombinator.com/']);

    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    $this->get('/admin/statistics')->assertOk()->assertSee('Statistiche')->assertSee('resume-map-visited', escape: false);

    Livewire::test(Statistics::class)
        ->assertSee('news.ycombinator.com')
        ->assertSee($project->t('title', 'it-IT'))
        ->set('pageFilter', 'cv')
        ->assertDontSee('news.ycombinator.com')
        ->assertCanSeeTableRecords(StatVisit::query()->where('page', 'cv')->get())
        ->assertCanNotSeeTableRecords(StatVisit::query()->where('page', '!=', 'cv')->get());
});

it('turns statistics on from the admin, which also turns on the privacy page', function () {
    app(Settings::class)->set('stats.enabled', false);
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(Statistics::class)
        ->assertSee(__('admin.stats.disabled'))
        ->callAction('settings', data: ['enabled' => true, 'retention_months' => 6, 'admin_ip_days' => 7])
        ->assertNotified(__('admin.messages.saved'));

    expect(app(Settings::class)->get('stats.retention_months'))->toBe(6)
        ->and(app(Settings::class)->get('stats.admin_ip_days'))->toBe(7);

    $this->get('/it-IT/privacy')->assertOk()->assertSee('dopo 6 mesi')->assertSee('GeoLite2');
});

it('never exports the statistics with the content', function () {
    expect(app(Backup::class)->tables())->not->toContain('stat_visits')->not->toContain('stat_daily')->not->toContain('stat_admin_ips');
});

// --- GeoLite databases ----------------------------------------------------------------------------------

/**
 * Archive like the MaxMind ones: GeoLite2-City_20261020/GeoLite2-City.mmdb plus the license files.
 */
function geoliteArchive(string $edition): string
{
    $directory = sys_get_temp_dir().'/geolite-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$directory}/{$edition}_20261020");
    file_put_contents("{$directory}/{$edition}_20261020/LICENSE.txt", str_repeat('licence text ', 50));
    copy(base_path("tests/Fixtures/geoip/{$edition}-Test.mmdb"), "{$directory}/{$edition}_20261020/{$edition}.mmdb");
    exec('cd '.escapeshellarg($directory).' && tar czf archive.tar.gz '.escapeshellarg("{$edition}_20261020"));

    return (string) file_get_contents("{$directory}/archive.tar.gz");
}

function fakeMaxMind(int $status = 200): array
{
    $archives = ['GeoLite2-City' => geoliteArchive('GeoLite2-City'), 'GeoLite2-ASN' => geoliteArchive('GeoLite2-ASN')];
    $calls = new ArrayObject;

    Http::fake(function (Request $request) use ($archives, $status, $calls) {
        $calls->append($request);

        if ($status !== 200) {
            return Http::response('', $status);
        }

        preg_match('#databases/([^/]+)/download#', $request->url(), $match);
        $content = $archives[$match[1]];

        if ($request->method() === 'HEAD') {
            return Http::response('', 200, ['Last-Modified' => 'Tue, 20 Oct 2026 12:00:00 GMT', 'Content-Length' => (string) strlen($content)]);
        }

        preg_match('/bytes=(\d+)-(\d+)/', $request->header('Range')[0] ?? '', $range);

        return Http::response(substr($content, (int) $range[1], (int) $range[2] - (int) $range[1] + 1), 206);
    });

    return [$archives, $calls];
}

it('downloads the GeoLite databases in chunks with the MaxMind account, then uses them', function () {
    File::deleteDirectory("{$this->storage}/app/private/geoip");
    $geoIp = app(GeoIp::class);
    $geoIp->setCredentials('123456', 'license-key');
    [, $calls] = fakeMaxMind();
    $updater = new GeoIpUpdater($geoIp, 1024);

    expect($geoIp->isAvailable())->toBeFalse()
        ->and(collect($updater->check())->every(fn (array $edition): bool => $edition['update']))->toBeTrue();

    foreach (GeoIp::EDITIONS as $edition) {
        do {
            $result = $updater->next($edition);
        } while (! $result['done']);
    }

    expect($geoIp->isAvailable())->toBeTrue()
        ->and($geoIp->lookup('93.44.1.1'))->toMatchArray(['country' => 'IT', 'city' => 'Milan', 'as_org' => 'Telecom Italia'])
        ->and($updater->needsUpdate())->toBeFalse()
        ->and($updater->updateAll())->toBe('up to date')
        ->and($calls[0]->header('Authorization')[0])->toBe('Basic '.base64_encode('123456:license-key'))
        ->and($calls[0]->url())->toBe('https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz');
});

it('explains a wrong MaxMind account', function () {
    app(GeoIp::class)->setCredentials('123456', 'wrong');
    fakeMaxMind(401);

    expect(fn () => app(GeoIpUpdater::class)->check())->toThrow(RuntimeException::class, __('admin.stats.geoip.errors.unauthorized'));
});

it('keeps the MaxMind account encrypted, with the .env taking precedence', function () {
    $geoIp = app(GeoIp::class);
    $geoIp->setCredentials('123456', 'secret-key');

    expect(file_get_contents($geoIp->credentialsFile()))->not->toContain('secret-key')
        ->and($geoIp->credentials())->toBe(['account' => '123456', 'key' => 'secret-key'])
        ->and($geoIp->credentialsSource())->toBe('admin');

    config(['resume.geoip.account_id' => '999', 'resume.geoip.license_key' => 'env-key']);

    expect($geoIp->credentials())->toBe(['account' => '999', 'key' => 'env-key'])->and($geoIp->credentialsSource())->toBe('env');
});

it('updates the GeoLite databases from the cron job', function () {
    File::deleteDirectory("{$this->storage}/app/private/geoip");
    app(GeoIp::class)->setCredentials('123456', 'license-key');
    fakeMaxMind();

    expect(app(GeoIpUpdater::class)->updateAll())->toBe('updated GeoLite2-City, GeoLite2-ASN')
        ->and(app(GeoIp::class)->lookup('2a01:4f8::5')['country'])->toBe('DE');
});

it('fills the demo with three months of sample visits', function () {
    app(Settings::class)->set('stats.enabled', false);
    $this->seed(DemoStatsSeeder::class);

    $totals = StatsQuery::forPeriod('90')->totals();

    expect(app(Settings::class)->bool('stats.enabled'))->toBeTrue()
        ->and($totals['views'])->toBeGreaterThan(1000)
        ->and($totals['downloads'])->toBeGreaterThan(50)
        ->and($totals['countries'])->toBe(12)
        ->and(StatsQuery::forPeriod('90')->top('utm_campaign'))->toHaveKey('new-job-2026');
});

it('tells public addresses from private and local ones', function (string $ip, bool $public) {
    expect(Ip::isPublic($ip))->toBe($public);
})->with([
    ['93.44.12.7', true],
    ['2a01:4f8:c0c::1', true],
    ['10.0.4.17', false],
    ['172.16.0.1', false],
    ['192.168.1.20', false],
    ['127.0.0.1', false],
    ['100.64.12.1', false],
    ['::1', false],
    ['fd00::1', false],
    ['not an ip', false],
]);

it('does not count the administrator signed in to the admin, whatever the address', function () {
    $user = User::factory()->create();
    $session = app('session')->driver();
    $session->setId($id = Str::random(40));
    $session->start();
    $session->put(Auth::guard()->getName(), $user->getAuthIdentifier());
    $session->save();

    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']); // a visitor: no cookies
    $this->withCookie(config('session.cookie'), $id);
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']); // the administrator's browser
    $this->withCookie(config('session.cookie'), Str::random(40));
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']); // a session that is not signed in
    $this->withCookie(config('session.cookie'), 'not encrypted');
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']);

    expect(StatVisit::query()->count())->toBe(3)
        ->and(StatAdminIp::query()->count())->toBe(0);
});

it('recognizes the administrator from the "remember me" cookie', function () {
    $this->withCookie(Auth::guard()->getRecallerName(), '1|token|hash');
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']);

    expect(StatVisit::query()->count())->toBe(0);
});

it('counts the visitors behind a proxy that gives everybody the same private address (GitHub Codespaces)', function () {
    $this->actingAs(User::factory()->create())->withServerVariables(['REMOTE_ADDR' => '10.0.4.17'])->get('/admin')->assertOk();
    auth()->logout();

    pageView('/it-IT', ['REMOTE_ADDR' => '10.0.4.17']);

    expect(StatAdminIp::query()->count())->toBe(0)
        ->and(StatVisit::query()->sole())->ip->toBe('10.0.4.x')->country->toBeNull();
});

it('explains on the statistics page when the visitor address is not available', function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.4.17'])->get('/admin/statistics')
        ->assertOk()->assertSee('Indirizzo dei visitatori non disponibile')->assertSee('10.0.4.17');
    $this->withServerVariables(['REMOTE_ADDR' => '93.44.12.7'])->get('/admin/statistics')
        ->assertOk()->assertDontSee('Indirizzo dei visitatori non disponibile');
    $this->withServerVariables(['REMOTE_ADDR' => '93.44.12.7', 'HTTP_X_FORWARDED_FOR' => '81.2.69.160'])->get('/admin/statistics')
        ->assertOk()->assertSee('RESUME_TRUSTED_PROXIES');
});

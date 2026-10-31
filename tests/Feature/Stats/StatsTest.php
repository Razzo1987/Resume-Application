<?php

use App\Filament\Pages\Statistics;
use App\Filament\Pages\StatsExclusions;
use App\Models\Article;
use App\Models\Project;
use App\Models\Redirect;
use App\Models\StatExclusion;
use App\Models\StatVisit;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Stats\Aggregator;
use App\Support\Stats\Channels;
use App\Support\Stats\Exclusions;
use App\Support\Stats\GeoIp;
use App\Support\Stats\GeoIpUpdater;
use App\Support\Stats\Ip;
use App\Support\Stats\Networks;
use App\Support\Stats\StatsQuery;
use App\Support\Stats\Tracker;
use App\Support\Stats\WorldMap;
use App\Support\System\Backup;
use Database\Seeders\DemoStatsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    expect(StatExclusion::query()->sole())->type->toBe('ip')->status->toBe(StatExclusion::AUTO)
        ->and(StatExclusion::query()->sole()->value)->toBe('93.44.12.7');

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

    app(Aggregator::class)->aggregate(StatsQuery::today()); // the day of the visits is a day of the site time zone
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

    // the administrator from another address and browser (the same ones would delete these visits as their own)
    $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160', 'HTTP_USER_AGENT' => IPHONE])->get('/admin/statistics')
        ->assertOk()->assertSee('Statistiche')->assertSee('resume-map-visited', escape: false);

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
    expect(app(Backup::class)->tables())->not->toContain('stat_visits')->not->toContain('stat_daily')->not->toContain('stat_salts')
        ->not->toContain('stat_exclusions')->not->toContain('stat_bots')->not->toContain('stat_not_found');
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
        ->and(StatExclusion::query()->count())->toBe(0);
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

    expect(StatExclusion::query()->count())->toBe(0)
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
    // the hosting already resolved the visitor's address and passes the header with the same one: no note
    $this->withServerVariables(['REMOTE_ADDR' => '93.44.12.7', 'HTTP_X_FORWARDED_FOR' => '93.44.12.7'])->get('/admin/statistics')
        ->assertOk()->assertDontSee('RESUME_TRUSTED_PROXIES');
});

it('filters the statistics by campaign, referrer, IP and the other details, also combined', function () {
    $project = Project::query()->firstOrFail();
    $projectPath = '/en-GB/projects/'.$project->t('slug', 'en-GB');

    pageView('/it-IT?utm_source=linkedin&utm_medium=social&utm_campaign=autunno', ['REMOTE_ADDR' => '81.2.69.160']);
    pageView($projectPath.'?utm_source=linkedin&utm_campaign=autunno', ['REMOTE_ADDR' => '81.2.69.160']);
    pageView('/it-IT?utm_campaign=newsletter', ['REMOTE_ADDR' => '93.44.12.7', 'HTTP_USER_AGENT' => IPHONE]);
    pageView('/it-IT', ['REMOTE_ADDR' => '2a01:4f8:c0c::1', 'HTTP_REFERER' => 'https://www.google.com/']);

    $campaign = StatsQuery::forPeriod('7', null, ['utm_campaign' => 'autunno', 'unknown' => 'x', 'ip' => '']);

    expect($campaign->filters)->toBe(['utm_campaign' => 'autunno'])
        ->and($campaign->isDetailed())->toBeTrue()
        ->and($campaign->totals()['views'])->toBe(2)
        ->and($campaign->pages())->toBe(['cv' => 1, 'project:'.$project->id => 1])
        ->and($campaign->top('utm_medium'))->toBe(['social' => 1])
        ->and($campaign->top('ip'))->toBe(['81.2.69.x' => 2])
        ->and(StatsQuery::forPeriod('7', 'cv', ['utm_campaign' => 'autunno'])->totals()['views'])->toBe(1)
        ->and(StatsQuery::forPeriod('7', null, ['utm_source' => 'linkedin', 'ip' => '93.44.12.x'])->totals()['views'])->toBe(0)
        ->and(StatsQuery::forPeriod('7', null, ['referrer' => 'google.com'])->top('ip'))->toBe(['2a01:4f8:c0c::' => 1])
        ->and(StatsQuery::forPeriod('7', null, ['device' => 'smartphone'])->top('utm_campaign'))->toBe(['newsletter' => 1])
        ->and(StatsQuery::forPeriod('7')->options('utm_campaign'))->toBe(['autunno' => 2, 'newsletter' => 1]);
});

it('filters the statistics page from the menus, the rankings and the IP of a visit', function () {
    pageView('/it-IT?utm_campaign=autunno', ['REMOTE_ADDR' => '81.2.69.160']);
    pageView('/en-GB?utm_campaign=newsletter', ['REMOTE_ADDR' => '93.44.12.7', 'HTTP_REFERER' => 'https://news.ycombinator.com/']);
    $autumn = StatVisit::query()->where('utm_campaign', 'autunno')->get();
    $newsletter = StatVisit::query()->where('utm_campaign', 'newsletter')->get();

    app()->setLocale('it'); // the public pages change the locale of the application
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(Statistics::class)
        ->assertSee('Altri filtri')
        ->call('addFilter', 'utm_campaign', 'autunno')
        ->assertSet('filters', ['utm_campaign' => 'autunno'])
        ->assertSee('Campagna (utm_campaign): autunno')
        ->assertViewHas('lists', fn (array $lists): bool => $lists['referrer'] === [] && array_column($lists['ip'], 'key') === ['81.2.69.x'])
        ->assertViewHas('detailed', true)
        ->assertCanSeeTableRecords($autumn)
        ->assertCanNotSeeTableRecords($newsletter)
        ->call('removeFilter', 'utm_campaign')
        ->assertCanSeeTableRecords($newsletter)
        ->set('filters.ip', '93.44.12.x')
        ->assertCanSeeTableRecords($newsletter)
        ->assertCanNotSeeTableRecords($autumn)
        ->call('addFilter', 'not-a-filter', 'x')
        ->assertSet('filters', ['ip' => '93.44.12.x'])
        ->call('clearFilters')
        ->assertSet('filters', [])
        ->callTableColumnAction('ip', $autumn->first())
        ->assertSet('filters', ['ip' => '81.2.69.x']);

    $this->get('/admin/statistics?filter[utm_campaign]=newsletter')->assertOk()->assertSee('Campagna (utm_campaign): newsletter');
});

it('deletes the selected visits and recalculates the totals of their days', function () {
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']);
    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.7']);
    app(Aggregator::class)->aggregate(StatsQuery::today());
    $mine = StatVisit::query()->where('ip', '93.44.12.x')->get();

    expect(StatsQuery::forPeriod('7')->totals()['views'])->toBe(2);

    app()->setLocale('it');
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(Statistics::class)
        ->callTableBulkAction('delete', $mine)
        ->assertNotified('Eliminata 1 visita');

    expect(StatVisit::query()->pluck('ip')->all())->toBe(['81.2.69.x'])
        ->and(StatsQuery::forPeriod('7')->totals()['views'])->toBe(1);
});

// --- Visitors and sessions (24 hours, no cookies) ----------------------------------------------------------

it('recognizes the same visitor for 24 hours at most, with sessions of 30 minutes, also across midnight', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-02 23:40:00', 'Europe/Rome'));
    pageView('/it-IT');
    $this->travel(10)->minutes();
    pageView('/it-IT/blog');                                   // same session
    $this->travel(15)->minutes();                              // 00:05 of the next day: new salt, same visitor
    pageView('/it-IT/privacy');
    $this->travel(40)->minutes();
    pageView('/it-IT');                                        // same visitor, new session
    pageView('/it-IT', ['HTTP_USER_AGENT' => IPHONE]);         // same address, another browser: another person

    $visits = StatVisit::query()->orderBy('id')->get();

    expect($visits->take(4)->pluck('visitor_id')->unique())->toHaveCount(1)
        ->and($visits->take(3)->pluck('session_id')->unique())->toHaveCount(1)
        ->and($visits[3]->session_id)->not->toBe($visits[2]->session_id)
        ->and($visits[4]->visitor_id)->not->toBe($visits[0]->visitor_id)
        ->and($visits[2]->day->toDateString())->toBe('2026-11-03')
        ->and(DB::table('stat_salts')->count())->toBe(2)
        ->and($visits->pluck('visitor_hash')->filter()->every(fn (string $hash): bool => ! str_contains($hash, '93.44')))->toBeTrue();

    // 25 hours after the first visit: nobody is recognized any more
    $this->travelTo(Carbon\Carbon::parse('2026-11-04 00:45:00', 'Europe/Rome'));
    pageView('/it-IT');

    expect(StatVisit::query()->latest('id')->first()->visitor_id)->not->toBe($visits[0]->visitor_id);
});

it('removes the hashes after 24 hours and keeps only the salts of today and yesterday', function () {
    pageView('/it-IT');
    $this->travel(2)->days();
    app(Aggregator::class)->run();

    expect(StatVisit::query()->sole())->ip_hash->toBeNull()->visitor_hash->toBeNull()->visitor_id->not->toBeNull()
        ->and(DB::table('stat_salts')->count())->toBe(0);

    pageView('/it-IT');

    expect(DB::table('stat_salts')->count())->toBe(1)
        ->and(StatVisit::query()->latest('id')->first()->visitor_hash)->toHaveLength(32);
});

it('counts visitors, visits, pages per visit, duration, bounces, entry and exit pages', function () {
    $project = Project::query()->firstOrFail();
    $projectPath = '/en-GB/projects/'.$project->t('slug', 'en-GB');

    pageView('/it-IT');                                                    // visit 1: three pages in 4 minutes
    $this->travel(2)->minutes();
    pageView($projectPath);
    $this->travel(2)->minutes();
    pageView('/it-IT/cv');
    pageView('/it-IT/blog', ['REMOTE_ADDR' => '81.2.69.160']);             // visit 2: one page
    pageView($projectPath, ['REMOTE_ADDR' => '2a01:4f8:c0c::1', 'HTTP_REFERER' => 'https://www.linkedin.com/']); // visit 3

    app(Aggregator::class)->aggregate(StatsQuery::today());
    $kpis = StatsQuery::forPeriod('7')->kpis();

    expect($kpis)->toMatchArray(['visitors' => 3, 'sessions' => 3, 'pages_per_session' => 1.7, 'avg_duration' => 80, 'bounce_rate' => 66.7])
        ->and(StatsQuery::forPeriod('7')->sessionPages('entry'))->toEqual(['cv' => 1, 'blog' => 1, 'project:'.$project->id => 1])
        ->and(StatsQuery::forPeriod('7')->sessionPages('exit'))->toHaveKey('cv-download', 1)
        ->and(StatsQuery::forPeriod('7', 'project:'.$project->id)->kpis())->toMatchArray(['visitors' => 2, 'entries' => 1, 'exits' => 1, 'sessions' => null])
        // with a filter the same numbers come from the single visits
        ->and(StatsQuery::forPeriod('7', null, ['channel' => 'social'])->kpis())->toMatchArray(['visitors' => 1, 'sessions' => 1, 'bounce_rate' => 100.0])
        ->and(StatsQuery::forPeriod('7', null, ['country' => 'IT'])->kpis())->toMatchArray(['visitors' => 1, 'sessions' => 1, 'avg_duration' => 240]);
});

it('tells the channel of a visit', function (?string $referrer, ?string $utm, ?string $click, string $channel) {
    expect(Channels::of($referrer, $utm, null, null, $click))->toBe($channel);
})->with([
    [null, null, null, 'direct'],
    ['google.it', null, null, 'search'],
    ['google.co.uk', null, null, 'search'],
    ['duckduckgo.com', null, null, 'search'],
    ['gemini.google.com', null, null, 'ai'],
    ['chatgpt.com', null, null, 'ai'],
    ['perplexity.ai', null, null, 'ai'],
    ['mail.google.com', null, null, 'email'],
    ['it.linkedin.com', null, null, 'social'],
    ['l.facebook.com', null, null, 'social'],
    ['github.com', null, null, 'sites'],
    ['google.com', 'newsletter', null, 'campaign'],
    [null, null, 'google-ads', 'campaign'],
    [null, null, 'facebook', 'social'],
]);

it('shows the days and hours of the visits in the time zone of the site', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-04 09:30:00', 'Europe/Rome')); // a Wednesday
    pageView('/it-IT');
    pageView('/it-IT/blog', ['REMOTE_ADDR' => '81.2.69.160']);
    app(Aggregator::class)->aggregate(StatsQuery::today());

    expect(StatVisit::query()->first()->hour)->toBe(9)
        ->and(StatsQuery::forPeriod('7')->heatmap()[3][9])->toBe(2)
        ->and(StatsQuery::forPeriod('7', null, ['ip' => '81.2.69.x'])->heatmap()[3][9])->toBe(1);
});

// --- Exclusions ----------------------------------------------------------------------------------------------

it('deletes the visits the administrator made just before signing in, from the same browser', function () {
    pageView('/it-IT');                                        // the administrator, not yet signed in
    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.8']);       // somebody else, same network
    $this->travel(5)->minutes();

    $this->actingAs(User::factory()->create())->withServerVariables(['REMOTE_ADDR' => '93.44.12.7'])->get('/admin')->assertOk(); // same browser

    expect(StatVisit::query()->pluck('ip')->all())->toBe(['93.44.12.x'])
        ->and(StatExclusion::query()->sole()->value)->toBe('93.44.12.7')
        ->and(StatExclusion::query()->sole()->status)->toBe(StatExclusion::AUTO);
});

it('excludes networks, addresses and providers, deleting the visits already recorded, and counts again when removed', function () {
    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.20']);
    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.21']);
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']);
    pageView('/it-IT', ['REMOTE_ADDR' => '2a01:4f8:c0c::1']);
    $exclusions = app(Exclusions::class);

    expect($exclusions->count('network', '93.44.12.0/24'))->toBe(2)
        ->and($exclusions->add('network', '93.44.12.x')['deleted'])->toBe(2)
        ->and(StatVisit::query()->count())->toBe(2);

    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.99']);      // not counted

    // an IP address: only the last 24 hours can be recognized
    $this->travel(2)->days();
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']);
    expect($exclusions->add('ip', '81.2.69.160')['deleted'])->toBe(1)
        ->and(StatVisit::query()->where('ip', '81.2.69.x')->count())->toBe(1);

    // a provider (ASN of the test database)
    $asn = (string) StatVisit::query()->where('country', 'DE')->value('asn');
    expect($exclusions->add('asn', 'AS'.$asn)['deleted'])->toBe(1);

    // removed: the count starts again, nothing comes back
    StatExclusion::query()->where('type', 'network')->delete();
    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.99']);

    expect(StatVisit::query()->pluck('ip')->all())->toBe(['81.2.69.x', '93.44.12.x'])
        ->and(Exclusions::normalize('network', '2a01:4f8:c0c:1234::1'))->toBe('2a01:4f8:c0c::')
        ->and(Exclusions::normalize('ip', 'nope'))->toBeNull()
        ->and(Exclusions::normalize('asn', 'AS 3269'))->toBe('3269');
});

it('always counts an address marked as such, also when the admin is used from there', function () {
    app(Exclusions::class)->add('ip', '93.44.12.7', StatExclusion::ALWAYS);
    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.7', 'HTTP_USER_AGENT' => IPHONE]); // a colleague in the same office

    $this->actingAs(User::factory()->create())->withServerVariables(['REMOTE_ADDR' => '93.44.12.7', 'HTTP_USER_AGENT' => CHROME])->get('/admin')->assertOk();
    auth()->logout();
    pageView('/it-IT', ['REMOTE_ADDR' => '93.44.12.7', 'HTTP_USER_AGENT' => IPHONE]);

    expect(StatVisit::query()->count())->toBe(2)
        ->and(StatExclusion::query()->sole()->status)->toBe(StatExclusion::ALWAYS);
});

it('manages the exclusions from the admin', function () {
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160']);
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    $this->withServerVariables(['REMOTE_ADDR' => '93.44.12.7'])->get('/admin/statistics/exclusions')->assertOk()->assertSee('93.44.12.7');

    Livewire::test(StatsExclusions::class)
        ->callAction('add', data: ['type' => 'network', 'value' => '81.2.69.x', 'status' => StatExclusion::EXCLUDED, 'note' => 'test'])
        ->assertHasNoActionErrors()
        ->assertNotified(trans_choice('admin.stats.exclusions.added', 1, ['count' => 1]))
        ->callAction('add', data: ['type' => 'ip', 'value' => 'not-an-ip', 'status' => StatExclusion::EXCLUDED])
        ->assertHasActionErrors(['value']);

    $entry = StatExclusion::query()->where('value', '81.2.69.x')->sole();

    Livewire::test(StatsExclusions::class)
        ->assertCanSeeTableRecords([$entry])
        ->callTableAction('always', $entry)
        ->callTableAction('remove', $entry->fresh());

    // the administrator's own address stays, as an automatic entry
    expect(StatVisit::query()->count())->toBe(0)->and(StatExclusion::query()->pluck('status')->all())->toBe([StatExclusion::AUTO]);

    // from the latest visits: the network of a visit
    pageView('/it-IT', ['REMOTE_ADDR' => '81.2.69.160', 'HTTP_USER_AGENT' => IPHONE]);
    Livewire::test(Statistics::class)->callTableAction('excludeNetwork', StatVisit::query()->sole());

    expect(StatVisit::query()->count())->toBe(0)
        ->and(StatExclusion::query()->where('type', 'network')->sole()->value)->toBe('81.2.69.x');
});

// --- Bots, pages not found, networks, map --------------------------------------------------------------------

it('counts bots, AI crawlers and programs by name, without addresses and without counting them as visits', function () {
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
    pageView('/it-IT/blog', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.2; +https://openai.com/gptbot']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'curl/8.5.0']);
    pageView('/sitemap.xml', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)']);

    $bots = StatsQuery::forPeriod('7')->bots();

    expect(StatVisit::query()->count())->toBe(0)
        ->and($bots['total'])->toBe(6)
        ->and($bots['categories'])->toMatchArray(['search' => 3, 'ai' => 2, 'tool' => 1])
        ->and(collect($bots['bots'])->firstWhere('name', 'Googlebot')['hits'])->toBe(2)
        ->and(collect($bots['bots'])->pluck('name'))->toContain('GPTBot')->toContain('ClaudeBot')
        ->and(json_encode(DB::table('stat_bots')->get()))->not->toContain('93.44');
});

it('lists the pages not found, apart from the attack attempts, and turns them into redirects', function () {
    pageView('/it-IT/progetti/vecchio-progetto', ['HTTP_REFERER' => 'https://www.linkedin.com/in/someone']);
    pageView('/it-IT/progetti/vecchio-progetto', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
    pageView('/wp-login.php');
    pageView('/.env');

    $notFound = StatsQuery::forPeriod('7')->notFound();

    expect($notFound['pages'])->toHaveCount(1)
        ->and($notFound['pages'][0])->path->toBe('/it-IT/progetti/vecchio-progetto')->hits->toBe(2)->human_hits->toBe(1)->last_referrer_host->toBe('linkedin.com')
        ->and($notFound['probes'])->toBe(2)
        ->and(Tracker::isProbe('/.well-known/security.txt'))->toBeFalse()
        ->and(Tracker::isProbe('/backup.zip'))->toBeTrue();

    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(Statistics::class)
        ->assertSee('/it-IT/progetti/vecchio-progetto')
        ->callAction('createRedirect', data: ['to_path' => '/it-IT', 'status_code' => 301], arguments: ['path' => '/it-IT/progetti/vecchio-progetto'])
        ->assertHasNoActionErrors();

    expect(Redirect::query()->sole())->from_path->toBe('/it-IT/progetti/vecchio-progetto')->to_path->toBe('/it-IT')
        ->and(StatsQuery::forPeriod('7')->notFound()['pages'])->toBeEmpty();

    $this->get('/it-IT/progetti/vecchio-progetto')->assertRedirect();
});

it('tells organizations from connection providers and data centers, and lets the administrator correct it', function () {
    $networks = app(Networks::class);

    expect($networks->kind('Telecom Italia'))->toBe(Networks::PROVIDER)
        ->and($networks->kind('Deutsche Telekom AG'))->toBe(Networks::PROVIDER)
        ->and($networks->kind('Amazon.com, Inc.'))->toBe(Networks::HOSTING)
        ->and($networks->kind('Universita degli Studi di Milano'))->toBe(Networks::ORGANIZATION)
        ->and($networks->kind('Simeone Logistics'))->toBe(Networks::ORGANIZATION);

    $networks->mark('Acme Telecom', Networks::PROVIDER);
    $networks->mark('Telecom Italia', Networks::ORGANIZATION);

    expect($networks->kind('Acme Telecom'))->toBe(Networks::PROVIDER)
        ->and($networks->kind('Telecom Italia'))->toBe(Networks::ORGANIZATION);
});

it('zooms the map in on a country, with clickable countries and cities', function () {
    app()->setLocale('it');
    $map = app(WorldMap::class);
    $world = (string) $map->render(['IT' => 3, 'FR' => 1], [['label' => 'Milan (IT)', 'latitude' => 45.46, 'longitude' => 9.19, 'views' => 3, 'key' => 'IT|Milan']], 'it');
    $italy = (string) $map->render(['IT' => 3], [['label' => 'Milan (IT)', 'latitude' => 45.46, 'longitude' => 9.19, 'views' => 3, 'key' => 'IT|Milan']], 'it', 'IT', 'IT|Milan');

    expect($world)->toContain('viewBox="0 0 1000 442"')
        ->toContain("wire:click.prevent=\"addFilter('country', 'IT')\"")
        ->toContain("addFilter('city', &quot;IT|Milan&quot;)")
        ->and($italy)->toContain('resume-map-zoomed')->toContain('resume-map-city-active')
        ->not->toContain("addFilter('country', 'IT')")
        ->and(substr_count($italy, '<path'))->toBeLessThan(40)
        ->and($map->viewBox('IT')[2])->toBeLessThan(100)
        ->and($map->viewBox('US')[0])->toBeGreaterThan(150); // without Alaska
});

it('shows the new sections of the statistics page', function () {
    pageView('/it-IT', ['HTTP_REFERER' => 'https://chatgpt.com/']);
    pageView('/it-IT', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
    pageView('/it-IT/pagina-inesistente');

    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');
    app()->setLocale('it');

    Livewire::test(Statistics::class)
        ->assertSee(__('admin.stats.visitors'))
        ->assertSee(__('admin.stats.heatmap'))
        ->assertSee(__('admin.stats.channels.ai'))
        ->assertSee('Googlebot')
        ->assertSee('/it-IT/pagina-inesistente')
        ->call('addFilter', 'country', 'IT')
        ->assertSee('resume-map-zoomed', escape: false)
        ->assertSee(__('admin.stats.world'))
        ->call('addFilter', 'city', 'IT|Milan')
        ->call('removeFilter', 'country')
        ->assertSet('filters', [])
        ->call('markNetwork', 'Telecom Italia', 'organization');

    expect(app(Networks::class)->kind('Telecom Italia'))->toBe(Networks::ORGANIZATION);
});

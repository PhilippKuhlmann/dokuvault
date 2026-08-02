<?php

use App\Http\Middleware\RecordDemoUsage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(fn () => File::deleteDirectory(RecordDemoUsage::verzeichnis()));
afterEach(fn () => File::deleteDirectory(RecordDemoUsage::verzeichnis()));

function usageZeilen(): array
{
    $datei = RecordDemoUsage::pfad(now()->format('Y-m'));

    return file_exists($datei)
        ? array_map(fn ($z) => json_decode($z, true), file($datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        : [];
}

test('ohne DEMO_MODE wird nichts aufgezeichnet', function () {
    config(['app.demo' => false]);

    $this->get('/login')->assertStatus(200);

    expect(File::exists(RecordDemoUsage::verzeichnis()))->toBeFalse();
});

test('mit DEMO_MODE wird je Seitenaufruf eine Zeile geschrieben', function () {
    config(['app.demo' => true]);

    $this->get('/login')->assertStatus(200);
    $this->get('/login')->assertStatus(200);

    expect(usageZeilen())->toHaveCount(2);
});

test('der User-Agent wird nie aufgezeichnet', function () {
    config(['app.demo' => true]);

    $this->withHeaders(['User-Agent' => 'NeugierigerBrowser/1.0'])->get('/login');

    expect(file_get_contents(RecordDemoUsage::pfad(now()->format('Y-m'))))
        ->not->toContain('NeugierigerBrowser');
});

test('mit demo_ip_logging=aus steht keine Adresse in der Datei', function () {
    config(['app.demo' => true, 'custom.demo_ip_logging' => 'aus']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get('/login');

    expect(array_keys(usageZeilen()[0]))->toEqualCanonicalizing(['t', 'v']);
    expect(file_get_contents(RecordDemoUsage::pfad(now()->format('Y-m'))))->not->toContain('203.0.113');
});

test('mit demo_ip_logging=anonym wird IPv4 auf das /24-Netz gekürzt', function () {
    config(['app.demo' => true, 'custom.demo_ip_logging' => 'anonym']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get('/login');

    expect(usageZeilen()[0]['ip'])->toBe('203.0.113.0');
    // Der letzte Block ist der Punkt der Uebung - er darf nirgends stehen.
    expect(file_get_contents(RecordDemoUsage::pfad(now()->format('Y-m'))))->not->toContain('203.0.113.7');
});

test('mit demo_ip_logging=anonym wird IPv6 auf das /48-Netz gekürzt', function () {
    config(['app.demo' => true, 'custom.demo_ip_logging' => 'anonym']);

    // Kurzschreibweise mit "::" - als Text liesse sich das nicht abschneiden.
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:abcd:1234::42'])->get('/login');

    expect(usageZeilen()[0]['ip'])->toBe('2001:db8:abcd::');
});

test('hinter einem vertrauten Proxy wird die Adresse des Besuchers aufgezeichnet', function () {
    config([
        'app.demo' => true,
        'custom.demo_ip_logging' => 'anonym',
        'custom.trusted_proxies' => '192.0.2.1',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('/login');

    expect(usageZeilen()[0]['ip'])->toBe('203.0.113.0');
});

test('ohne vertrauten Proxy wird die Adresse des Proxys aufgezeichnet', function () {
    config([
        'app.demo' => true,
        'custom.demo_ip_logging' => 'anonym',
        'custom.trusted_proxies' => '10.0.254.2',
    ]);

    // Genau der Fall, den demo:stats meldet: alle Besuche scheinbar aus einem Netz.
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('/login');

    expect(usageZeilen()[0]['ip'])->toBe('192.0.2.0');
});

test('trusted_proxies akzeptiert mehrere Einträge und CIDR', function () {
    config([
        'app.demo' => true,
        'custom.demo_ip_logging' => 'anonym',
        'custom.trusted_proxies' => '127.0.0.1, 172.18.0.0/16',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('/login');

    expect(usageZeilen()[0]['ip'])->toBe('203.0.113.0');
});

test('mit demo_ip_logging=voll wird die ganze Adresse aufgezeichnet', function () {
    config(['app.demo' => true, 'custom.demo_ip_logging' => 'voll']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get('/login');

    expect(usageZeilen()[0]['ip'])->toBe('203.0.113.7');
});

test('die Rolle des angemeldeten Nutzers wird festgehalten', function () {
    config(['app.demo' => true]);
    $nutzer = userWithPermissions([]);

    $this->actingAs($nutzer)->get('/login');

    expect(usageZeilen()[0]['r'])->toBe($nutzer->role->name);
});

test('ein Besuch behält über mehrere Aufrufe dieselbe Kennung', function () {
    config(['app.demo' => true]);

    $this->get('/login');
    $this->get('/login');

    $kennungen = array_unique(array_column(usageZeilen(), 'v'));
    expect($kennungen)->toHaveCount(1);
});

test('demo:stats wertet die aufgezeichneten Zeilen aus', function () {
    config(['app.demo' => true]);
    File::ensureDirectoryExists(RecordDemoUsage::verzeichnis());

    // Zwei Besuche: einer mit drei Seiten über zwei Minuten, einer mit einer Seite
    $zeilen = [
        ['t' => '2026-08-01T09:00:00+00:00', 'v' => 'aaa', 'r' => 'Admin', 'ip' => '203.0.113.0'],
        ['t' => '2026-08-01T09:01:00+00:00', 'v' => 'aaa', 'r' => 'Admin', 'ip' => '203.0.113.0'],
        ['t' => '2026-08-01T09:02:00+00:00', 'v' => 'aaa', 'r' => 'Admin', 'ip' => '203.0.113.0'],
        ['t' => '2026-08-01T14:30:00+00:00', 'v' => 'bbb', 'r' => null, 'ip' => '198.51.100.0'],
    ];
    file_put_contents(
        RecordDemoUsage::pfad('2026-08'),
        implode("\n", array_map('json_encode', $zeilen))."\n"
    );

    $this->artisan('demo:stats', ['--month' => '2026-08'])
        ->expectsOutputToContain('Gesamt')
        ->expectsOutputToContain('Besuche je Rolle')
        ->expectsOutputToContain('Besuche je Herkunft')
        ->expectsOutputToContain('203.0.113.0')
        ->assertExitCode(0);
});

test('demo:stats zählt Besuche je Netz, nicht Seitenaufrufe', function () {
    config(['app.demo' => true]);
    File::ensureDirectoryExists(RecordDemoUsage::verzeichnis());

    // Ein Besuch mit drei Seiten aus einem Netz, zwei Besuche aus einem anderen.
    $zeilen = [
        ['t' => '2026-08-01T09:00:00+00:00', 'v' => 'aaa', 'ip' => '203.0.113.0'],
        ['t' => '2026-08-01T09:01:00+00:00', 'v' => 'aaa', 'ip' => '203.0.113.0'],
        ['t' => '2026-08-01T09:02:00+00:00', 'v' => 'aaa', 'ip' => '203.0.113.0'],
        ['t' => '2026-08-01T10:00:00+00:00', 'v' => 'bbb', 'ip' => '198.51.100.0'],
        ['t' => '2026-08-01T11:00:00+00:00', 'v' => 'ccc', 'ip' => '198.51.100.0'],
    ];
    file_put_contents(
        RecordDemoUsage::pfad('2026-08'),
        implode("\n", array_map('json_encode', $zeilen))."\n"
    );

    expect(Artisan::call('demo:stats', ['--month' => '2026-08']))->toBe(0);
    $ausgabe = Artisan::output();

    // 198.51.100.0 hat zwei Besuche, 203.0.113.0 nur einen - trotz dreier Seiten.
    $herkunft = substr($ausgabe, strpos($ausgabe, 'Besuche je Herkunft'));
    expect(strpos($herkunft, '198.51.100.0'))->toBeLessThan(strpos($herkunft, '203.0.113.0'));
});

test('demo:stats meldet sich verständlich, wenn noch nichts aufgezeichnet wurde', function () {
    $this->artisan('demo:stats')
        ->expectsOutputToContain('Noch keine Aufrufe aufgezeichnet')
        ->assertExitCode(0);
});

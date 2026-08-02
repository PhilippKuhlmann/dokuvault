<?php

use App\Http\Middleware\RecordDemoUsage;
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

test('aufgezeichnet werden nur Zeitpunkt, Besuchskennung und Rolle', function () {
    config(['app.demo' => true]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeaders(['User-Agent' => 'NeugierigerBrowser/1.0'])
        ->get('/login');

    $zeile = usageZeilen()[0];

    // Keine Personendaten: das ist die Zusage an Demo-Besucher.
    expect(array_keys($zeile))->toEqualCanonicalizing(['t', 'v', 'r']);

    $inhalt = file_get_contents(RecordDemoUsage::pfad(now()->format('Y-m')));
    expect($inhalt)->not->toContain('203.0.113.7');
    expect($inhalt)->not->toContain('NeugierigerBrowser');
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
        ['t' => '2026-08-01T09:00:00+00:00', 'v' => 'aaa', 'r' => 'Admin'],
        ['t' => '2026-08-01T09:01:00+00:00', 'v' => 'aaa', 'r' => 'Admin'],
        ['t' => '2026-08-01T09:02:00+00:00', 'v' => 'aaa', 'r' => 'Admin'],
        ['t' => '2026-08-01T14:30:00+00:00', 'v' => 'bbb', 'r' => null],
    ];
    file_put_contents(
        RecordDemoUsage::pfad('2026-08'),
        implode("\n", array_map('json_encode', $zeilen))."\n"
    );

    $this->artisan('demo:stats', ['--month' => '2026-08'])
        ->expectsOutputToContain('Gesamt')
        ->expectsOutputToContain('Besuche je Rolle')
        ->assertExitCode(0);
});

test('demo:stats meldet sich verständlich, wenn noch nichts aufgezeichnet wurde', function () {
    $this->artisan('demo:stats')
        ->expectsOutputToContain('Noch keine Aufrufe aufgezeichnet')
        ->assertExitCode(0);
});

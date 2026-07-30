<?php

use App\Models\Customer;
use App\Models\Site;
use Illuminate\Support\Facades\Schema;

/**
 * Regression: ein gewählter Standort erzeugte auf Listen ohne site_id-Spalte
 * (Dateien, Windows-Lizenzen) ein "where site_id = ?" -> HTTP 500 unter MySQL.
 *
 * WICHTIG: Diese Tests prüfen bewusst NICHT nur den HTTP-Status. Die Testdatenbank
 * ist SQLite (siehe phpunit.xml); dort wird ein unbekannter, doppelt gequoteter
 * Bezeichner als String-Literal ausgewertet ("site_id" = 1 -> immer falsch), sodass
 * die Abfrage still 0 Treffer liefert statt zu scheitern. Ein reiner
 * assertStatus(200)-Test wäre also auch bei kaputtem Code grün gewesen.
 * Geprüft wird deshalb die erzeugte SQL-Form bzw. die Schema-Invariante - das ist
 * dialektunabhängig und fängt genau die Fehlerklasse.
 */

/** Modelle, die auf Index-Seiten gelistet werden und KEINEN Standortbezug haben. */
const SITELESS_MODELS = [
    \App\Models\File::class,
    \App\Models\LicenseWindows::class,
];

/** Kontrollgruppe: Modelle MIT Standortbezug, hier muss der Filter greifen. */
const SITE_SCOPED_MODELS = [
    \App\Models\Server::class,
    \App\Models\Printer::class,
    \App\Models\Network::class,
];

/** Ruft das geschützte getFilteredQuery des Basis-Controllers auf. */
function filteredQueryFor(string $model, Customer $customer)
{
    $controller = new class extends \App\Http\Controllers\Controller
    {
        public function call($model, $customer)
        {
            return $this->getFilteredQuery($model, $customer);
        }
    };

    return $controller->call($model, $customer);
}

test('Modelle ohne site_id bekommen auch bei gewähltem Standort kein site_id-Filter', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    session()->put('site', $site->id);

    foreach (SITELESS_MODELS as $model) {
        $table = (new $model)->getTable();

        // Vorbedingung: dieses Modell hat wirklich keine site_id-Spalte
        expect(Schema::hasColumn($table, 'site_id'))->toBeFalse(
            "$table hat unerwartet eine site_id-Spalte - Testannahme veraltet"
        );

        $sql = filteredQueryFor($model, $customer)->toSql();

        // Hinweis: toContain() nimmt bei Strings kein Meldungs-Argument - ein zweites
        // Argument waere ein weiterer Suchbegriff. Daher Meldung ueber ->toBeFalse/True.
        expect(str_contains($sql, 'site_id'))->toBeFalse(
            "getFilteredQuery erzeugt site_id-Filter für $table (Spalte existiert dort nicht -> HTTP 500 unter MySQL). SQL: $sql"
        );
        expect(str_contains($sql, 'customer_id'))->toBeTrue("Kundenfilter fehlt für $table");
    }
});

test('Modelle mit site_id bekommen bei gewähltem Standort weiterhin den Filter', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    session()->put('site', $site->id);

    foreach (SITE_SCOPED_MODELS as $model) {
        $table = (new $model)->getTable();

        expect(Schema::hasColumn($table, 'site_id'))->toBeTrue(
            "$table sollte eine site_id-Spalte haben - Testannahme veraltet"
        );

        $sql = filteredQueryFor($model, $customer)->toSql();

        expect(str_contains($sql, 'site_id'))->toBeTrue("Standortfilter fehlt für $table. SQL: $sql");
        expect(str_contains($sql, 'customer_id'))->toBeTrue("Kundenfilter fehlt für $table");
    }
});

test('ohne gewählten Standort filtert niemand nach site_id', function () {
    $customer = Customer::factory()->create();
    session()->put('site', 'all');

    foreach ([...SITELESS_MODELS, ...SITE_SCOPED_MODELS] as $model) {
        $sql = filteredQueryFor($model, $customer)->toSql();
        expect(str_contains($sql, 'site_id'))->toBeFalse("unerwarteter Standortfilter. SQL: $sql");
        expect(str_contains($sql, 'customer_id'))->toBeTrue("Kundenfilter fehlt. SQL: $sql");
    }
});

test('jeder getFilteredQuery-Aufruf trifft ein Modell mit site_id-Spalte', function () {
    // Strukturelle Invariante: fängt künftige Fehlanwendungen des Helpers ab,
    // ohne dass jede Liste einzeln getestet werden muss.
    $offenders = [];

    foreach (glob(app_path('Http/Controllers/*.php')) as $file) {
        $code = file_get_contents($file);

        if (! preg_match_all('/getFilteredQuery\(\s*([A-Za-z_\\\\]+)::class/', $code, $matches)) {
            continue;
        }

        foreach ($matches[1] as $shortName) {
            // Klassennamen über die use-Statements der Datei auflösen
            $fqcn = null;
            if (preg_match('/^use\s+([A-Za-z0-9_\\\\]*\\\\'.preg_quote(ltrim($shortName, '\\'), '/').');/m', $code, $m)) {
                $fqcn = $m[1];
            } elseif (class_exists('\\App\\Models\\'.$shortName)) {
                $fqcn = '\\App\\Models\\'.$shortName;
            }

            if (! $fqcn || ! class_exists($fqcn)) {
                continue;
            }

            $table = (new $fqcn)->getTable();
            if (! Schema::hasColumn($table, 'site_id')) {
                $offenders[] = basename($file).' -> '.$shortName." (Tabelle $table ohne site_id)";
            }
        }
    }

    expect($offenders)->toBe([],
        "Diese Controller filtern nach Standort auf Modellen ohne site_id-Spalte:\n  ".implode("\n  ", $offenders)
    );
});

<?php

use App\Models\Customer;
use App\Models\LicenseWindows;
use App\Models\OperatingSystem;
use App\Models\Printer;
use App\Models\Site;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Spalten mit einem Crypt-Accessor - die also verschlüsselt in der Datenbank
 * liegen.
 *
 * @return array<int, string>
 */
function verschluesselteSpaltenNamen(): array
{
    $treffer = [];

    foreach (glob(app_path('Models/*.php')) as $datei) {
        $klasse = 'App\\Models\\'.basename($datei, '.php');

        if (! class_exists($klasse) || ! is_subclass_of($klasse, Model::class)) {
            continue;
        }

        $spiegel = new ReflectionClass($klasse);
        $zeilen = file($datei);

        foreach ($spiegel->getMethods(ReflectionMethod::IS_PROTECTED) as $methode) {
            if ($methode->getDeclaringClass()->getName() !== $klasse
                || (string) $methode->getReturnType() !== Attribute::class) {
                continue;
            }

            $koerper = implode('', array_slice($zeilen, $methode->getStartLine() - 1,
                $methode->getEndLine() - $methode->getStartLine() + 1));

            if (! str_contains($koerper, 'Crypt::encryptString')) {
                continue;
            }

            $tabelle = (new $klasse)->getTable();
            $spalten = Schema::getColumnListing($tabelle);
            $spalte = in_array($methode->getName(), $spalten) ? $methode->getName() : Str::snake($methode->getName());

            $treffer[] = $tabelle.'.'.$spalte;
        }
    }

    return array_values(array_unique($treffer));
}

test('was nach einem Geheimnis klingt, liegt verschlüsselt oder steht mit Grund hier', function () {
    // Der Abgleich, der Postfach- und Druckerkennwort sowie drei
    // Lizenzschlüssel im Klartext gefunden hat. Wer eine Spalte anlegt, deren
    // Name auf ein Geheimnis hindeutet, muss sie verschlüsseln - oder hier
    // eintragen, warum nicht. Beides ist eine Entscheidung; keine ist es nicht.
    $begruendet = [
        // Hashes, keine Chiffrate - sie werden nie zurückgelesen.
        'users.password' => 'bcrypt-Hash',
        'agent_tokens.token' => 'gehasht, siehe AgentToken::hashToken()',
        'password_resets.token' => 'gehasht vom Broker',
        'personal_access_tokens.token' => 'gehasht von Sanctum',

        // Sitzungsmerkmale, keine Geheimnisse im eigentlichen Sinn.
        'users.remember_token' => 'wechselt bei jeder Anmeldung',

        // Ein Lizenzschlüssel ist ein Geheimnis - aber auch ein Merkmal, nach
        // dem gesucht wird: Er steht in den Suchfeldern aller drei Lizenzarten,
        // bei den Windows-Lizenzen als einziges. Über eine verschlüsselte
        // Spalte lässt sich nicht suchen, jedes Chiffrat ist anders. Der
        // Versuch wurde gemacht und zurückgenommen, statt die Suche zu
        // opfern - im Gegensatz zu einem Kennwort, nach dem niemand sucht.
        'license_accesses.key' => 'wird gesucht, siehe suchfelder in config/forms.php',
        'license_software.key' => 'wird gesucht, siehe suchfelder in config/forms.php',
        'license_windows.key' => 'wird gesucht - einziges Suchfeld dieser Liste',

        // Tragen "key" oder "credential" im Namen, meinen aber etwas anderes.
        'settings.key' => 'Name einer Einstellung',
        'device_models.manufacturer_key' => 'normalisierter Suchschlüssel',
        'device_models.model_key' => 'normalisierter Suchschlüssel',
        'login_generals.key_type' => 'Verfahren, etwa ed25519',
        'login_generals.public_key' => 'der öffentliche Teil - er darf öffentlich sein',
        'credential_links.credentialable_id' => 'polymorphe Verknüpfung',
        'credential_links.credentialable_type' => 'polymorphe Verknüpfung',
        'personal_access_tokens.tokenable_id' => 'polymorphe Verknüpfung',
        'personal_access_tokens.tokenable_type' => 'polymorphe Verknüpfung',
    ];

    $verschluesselt = verschluesselteSpaltenNamen();

    $verdaechtig = [];
    foreach (Schema::getTables() as $tabelle) {
        foreach (Schema::getColumnListing($tabelle['name']) as $spalte) {
            if (preg_match('/pass|secret|_pin|pin_|token|key|passphrase|credential/i', $spalte)) {
                $verdaechtig[] = $tabelle['name'].'.'.$spalte;
            }
        }
    }

    $offen = array_values(array_diff(
        array_unique($verdaechtig),
        $verschluesselt,
        array_keys($begruendet),
    ));

    sort($offen);

    expect($offen)->toBe([], 'Klingt nach einem Geheimnis, liegt aber im Klartext: '.implode(', ', $offen));
});

test('die neu verschlüsselten Spalten sind groß genug', function () {
    // Ein Chiffrat misst ab etwa 32 Zeichen Klartext mehr als 255 Zeichen. Auf
    // MySQL wäre das ein "Data too long", auf SQLite ein stilles Abschneiden.
    foreach ([['mailboxes', 'password'], ['printers', 'password']] as [$tabelle, $spalte]) {
        $typ = collect(Schema::getColumns($tabelle))->firstWhere('name', $spalte);

        expect(strtolower($typ['type'] ?? ''))->not->toContain('varchar', "{$tabelle}.{$spalte}");
    }
});

test('ein Lizenzschlüssel bleibt durchsuchbar', function () {
    // Die Gegenprobe zur Entscheidung oben: Verschlüsselt wäre diese Abfrage
    // ergebnislos, denn jedes Chiffrat sieht anders aus.
    $lizenz = LicenseWindows::create([
        'customer_id' => Customer::factory()->create()->id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Windows Server 2022'])->id,
        'name' => 'Windows Server 2022',
        'key' => 'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE',
    ]);

    expect(LicenseWindows::where('key', 'like', '%CCCCC%')->pluck('id'))->toContain($lizenz->id);
});

test('ein Druckerkennwort geht verschlüsselt in die Datenbank und kommt lesbar zurück', function () {
    $kunde = Customer::factory()->create();

    $drucker = Printer::create([
        'customer_id' => $kunde->id,
        'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
        'name' => 'Kopierer EG',
        'password' => 'Geheim-2026',
    ]);

    $roh = DB::table('printers')->where('id', $drucker->id)->value('password');

    expect($roh)->not->toBe('Geheim-2026')
        ->and($drucker->fresh()->password)->toBe('Geheim-2026');
});

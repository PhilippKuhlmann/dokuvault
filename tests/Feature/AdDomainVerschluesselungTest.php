<?php

use App\Models\ADDomain;
use App\Models\Customer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

test('das DSRM-Kennwort steht verschluesselt in der Datenbank', function () {
    $customer = Customer::factory()->create();

    $domaene = ADDomain::factory()->create([
        'customer_id' => $customer->id,
        'dsrmpassword' => 'Geheim123!',
    ]);

    // Roh gelesen, am Model vorbei: So sieht es der, der die Datenbank hat.
    $roh = DB::table('ad_domains')->where('id', $domaene->id)->value('dsrmpassword');

    expect($roh)->not->toBe('Geheim123!');
    expect(Crypt::decryptString($roh))->toBe('Geheim123!');

    // Ueber das Model kommt weiterhin der Klartext zurueck - Formular, PDF und
    // Anzeige merken von der Verschluesselung nichts.
    expect($domaene->fresh()->dsrmpassword)->toBe('Geheim123!');
});

test('ein leeres Kennwort wird nicht verschluesselt', function () {
    $customer = Customer::factory()->create();

    // Sonst stuende in der Datenbank ein Chiffrat, das einen Leerstring
    // enthaelt - und die Anzeige haette etwas zu zeigen, wo nichts ist.
    $domaene = ADDomain::factory()->create(['customer_id' => $customer->id, 'dsrmpassword' => '']);

    expect(DB::table('ad_domains')->where('id', $domaene->id)->value('dsrmpassword'))->toBe('');
    expect($domaene->fresh()->dsrmpassword)->toBeNull();
});

test('die Spalte fasst auch ein langes Kennwort', function () {
    $customer = Customer::factory()->create();

    // 255 Zeichen sind erlaubt (ADDomainRequest), verschluesselt sind das ueber
    // 600 - in varchar(255) waere das still abgeschnitten und unlesbar.
    $lang = str_repeat('a1B!', 63).'xyz';

    $domaene = ADDomain::factory()->create([
        'customer_id' => $customer->id,
        'dsrmpassword' => $lang,
    ]);

    expect(mb_strlen($lang))->toBe(255);
    expect($domaene->fresh()->dsrmpassword)->toBe($lang);
});

test('die Migration verschluesselt vorhandene Klartext-Kennwoerter', function () {
    $customer = Customer::factory()->create();

    // Bestand nachstellen: Am Model vorbei geschrieben, also im Klartext -
    // genau so lagen die Kennwoerter vor dieser Aenderung in der Datenbank.
    $id = DB::table('ad_domains')->insertGetId([
        'customer_id' => $customer->id,
        'domain' => 'ad.altbestand.de',
        'netbios' => 'ALTBESTAND',
        'dsrmpassword' => 'AltesKlartextKennwort',
        'hidden' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_16_120000_encrypt_dsrmpassword.php');
    $migration->up();

    $roh = DB::table('ad_domains')->where('id', $id)->value('dsrmpassword');

    expect($roh)->not->toBe('AltesKlartextKennwort');
    expect(Crypt::decryptString($roh))->toBe('AltesKlartextKennwort');
    expect(ADDomain::find($id)->dsrmpassword)->toBe('AltesKlartextKennwort');
});

test('ein zweiter Lauf der Migration verpackt das Chiffrat nicht noch einmal', function () {
    $customer = Customer::factory()->create();

    $domaene = ADDomain::factory()->create([
        'customer_id' => $customer->id,
        'dsrmpassword' => 'Schon verschluesselt',
    ]);

    // Migrationen laufen in der Praxis oefter als einmal - etwa wenn ein
    // Deploy abbricht und wiederholt wird.
    $migration = require database_path('migrations/2026_08_16_120000_encrypt_dsrmpassword.php');
    $migration->up();
    $migration->up();

    expect($domaene->fresh()->dsrmpassword)->toBe('Schon verschluesselt');
});

test('down macht die Verschluesselung rueckgaengig', function () {
    $customer = Customer::factory()->create();

    $domaene = ADDomain::factory()->create([
        'customer_id' => $customer->id,
        'dsrmpassword' => 'Zurueck im Klartext',
    ]);

    $migration = require database_path('migrations/2026_08_16_120000_encrypt_dsrmpassword.php');
    $migration->down();

    expect(DB::table('ad_domains')->where('id', $domaene->id)->value('dsrmpassword'))
        ->toBe('Zurueck im Klartext');
});

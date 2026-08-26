<?php

use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\SshKey;
use App\Support\SshKeyGenerator;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

/**
 * Der Erzeuger ruft ssh-keygen auf. Die Tests pruefen deshalb nicht nur, dass
 * etwas herauskommt, sondern dass ssh-keygen selbst das Ergebnis wieder
 * annimmt - ein nur beinahe richtiger Schluessel faellt sonst erst auf, wenn
 * ihn nachts ein Server ablehnt.
 */
beforeEach(function () {
    if ((new Process(['which', 'ssh-keygen']))->run() !== 0) {
        $this->markTestSkipped('ssh-keygen steht auf diesem System nicht zur Verfügung.');
    }
});

/** Prueft ein Paar, indem ssh-keygen den oeffentlichen Teil neu ableitet. */
function ableitbar(string $privat, string $passphrase = ''): string
{
    $pfad = tempnam(sys_get_temp_dir(), 'pruef');
    file_put_contents($pfad, $privat);
    chmod($pfad, 0600);

    $prozess = new Process(['ssh-keygen', '-y', '-P', $passphrase, '-f', $pfad]);
    $prozess->run();
    unlink($pfad);

    expect($prozess->isSuccessful())->toBeTrue('ssh-keygen nimmt den erzeugten Schlüssel nicht an: '.$prozess->getErrorOutput());

    return trim($prozess->getOutput());
}

test('ein erzeugtes Paar gehoert zusammen', function () {
    $werte = (new SshKeyGenerator)->erzeugen([
        'key_type' => 'ed25519', 'username' => 'root', 'name' => 'Admin ed25519',
    ]);

    expect($werte['public_key'])->toStartWith('ssh-ed25519 ');
    expect($werte['private_key'])->toContain('BEGIN OPENSSH PRIVATE KEY');

    // Der abgeleitete oeffentliche Teil muss dem gelieferten entsprechen -
    // ohne Kommentar, den fuehrt die Ableitung nicht mit.
    $abgeleitet = ableitbar($werte['private_key']);
    expect($werte['public_key'])->toStartWith($abgeleitet);
});

test('der Kommentar sagt, wofuer der Schluessel da ist', function () {
    $werte = (new SshKeyGenerator)->erzeugen([
        'key_type' => 'ed25519', 'username' => 'deploy', 'name' => 'CI Ausrollen',
    ]);

    expect($werte['public_key'])->toEndWith(' deploy@ci-ausrollen');
});

test('mit Passphrase ist der private Teil verschluesselt', function () {
    $werte = (new SshKeyGenerator)->erzeugen([
        'key_type' => 'ed25519', 'username' => 'root', 'name' => 'Admin',
        'password' => 'sehr geheim 42',
    ]);

    // Ohne Passphrase steht "none" als Verfahren im Kopf des Schluessels.
    expect(str_contains($werte['private_key'], 'b3BlbnNzaC1rZXktdjEAAAAABG5vbmU'))
        ->toBeFalse('Der private Teil ist trotz Passphrase unverschlüsselt.');

    ableitbar($werte['private_key'], 'sehr geheim 42');
})->group('langsam');

test('RSA und ECDSA gehen auch', function (string $verfahren, string $vorsatz) {
    $werte = (new SshKeyGenerator)->erzeugen([
        'key_type' => $verfahren, 'username' => 'root', 'name' => 'Test',
    ]);

    expect($werte['public_key'])->toStartWith($vorsatz);
    ableitbar($werte['private_key']);
})->with([
    ['rsa', 'ssh-rsa '],
    ['ecdsa', 'ecdsa-sha2-nistp521 '],
])->group('langsam');

test('ohne Verfahren wird nichts erzeugt', function () {
    expect(fn () => (new SshKeyGenerator)->erzeugen(['key_type' => '']))
        ->toThrow(RuntimeException::class);

    // Auch nichts Ausgedachtes: Das wandert sonst ungeprueft in den Aufruf.
    expect(fn () => (new SshKeyGenerator)->erzeugen(['key_type' => 'dsa']))
        ->toThrow(RuntimeException::class);
});

test('es bleibt kein privater Schluessel im Temp-Verzeichnis liegen', function () {
    $vorher = glob(sys_get_temp_dir().'/dokuvault-ssh-*');

    (new SshKeyGenerator)->erzeugen(['key_type' => 'ed25519', 'username' => 'root', 'name' => 'Admin']);

    expect(glob(sys_get_temp_dir().'/dokuvault-ssh-*'))->toBe($vorher);
});

test('der Knopf steht im Modal - beim Anlegen ohne Nachfrage, beim Bearbeiten mit', function () {
    $this->actingAs(userWithPermissions(['sshkey_create', 'sshkey_update', 'sshkey_viewAny']));
    $customer = Customer::factory()->create();

    // Der Test unten ruft erzeugen() direkt auf und liefe auch dann gruen,
    // wenn der Knopf gar nicht gerendert waere - genau das war einmal der Fall.
    $anlegen = Livewire::test(ObjektFormular::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->call('neu')->html();

    expect($anlegen)->toContain('Schlüsselpaar erzeugen');
    expect(str_contains($anlegen, '<x-input.button'))
        ->toBeFalse('Blade hat den Komponenten-Tag nicht übersetzt.');
    expect(str_contains($anlegen, 'wire:confirm'))
        ->toBeFalse('Beim Anlegen gibt es nichts zu überschreiben.');

    $schluessel = SshKey::create([
        'customer_id' => $customer->id, 'name' => 'Admin', 'key_type' => 'ed25519',
    ]);

    $bearbeiten = Livewire::test(ObjektFormular::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->call('bearbeiten', 'sshkey', $schluessel->id)->html();

    expect($bearbeiten)->toContain('wire:confirm');
});

test('der Knopf im Modal fuellt beide Felder', function () {
    $this->actingAs(userWithPermissions(['sshkey_create', 'sshkey_viewAny']));
    $customer = Customer::factory()->create();

    $formular = Livewire::test(ObjektFormular::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->call('neu', 'sshkey')
        ->set('form.name', 'Deploy')
        ->set('form.username', 'deploy')
        ->set('form.key_type', 'ed25519')
        ->call('erzeugen')
        ->assertHasNoErrors();

    expect($formular->get('form')['public_key'])->toStartWith('ssh-ed25519 ');

    // Erzeugen speichert nicht - erst der Speichern-Knopf legt an.
    expect(SshKey::where('customer_id', $customer->id)->count())->toBe(0);

    $formular->call('speichern')->assertHasNoErrors();
    expect(SshKey::where('customer_id', $customer->id)->firstOrFail()->public_key)->toStartWith('ssh-ed25519 ');
});

test('das Modal bringt ein Verfahren schon mit', function () {
    $this->actingAs(userWithPermissions(['sshkey_create', 'sshkey_viewAny']));
    $customer = Customer::factory()->create();

    // Feste Optionslisten sind vorbelegt - der Knopf laeuft also nie ins Leere,
    // nur weil noch nichts gewaehlt wurde.
    $formular = Livewire::test(ObjektFormular::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->call('neu', 'sshkey');

    expect($formular->get('form')['key_type'])->toBe('ed25519');
});

test('ein untergeschobenes Verfahren meldet sich am Feld, nicht als Serverfehler', function () {
    $this->actingAs(userWithPermissions(['sshkey_create', 'sshkey_viewAny']));
    $customer = Customer::factory()->create();

    Livewire::test(ObjektFormular::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->call('neu', 'sshkey')
        ->set('form.key_type', 'dsa')
        ->call('erzeugen')
        ->assertHasErrors('form.key_type');
});

test('ein Typ ohne Erzeuger hat auch keinen Knopf', function () {
    $this->actingAs(userWithPermissions(['logingeneral_create', 'logingeneral_viewAny']));
    $customer = Customer::factory()->create();

    Livewire::test(ObjektFormular::class, ['typ' => 'logingeneral', 'customer' => $customer])
        ->call('neu', 'logingeneral')
        ->call('erzeugen')
        ->assertStatus(404);
});

test('der berechnete Fingerprint ist der, den ssh-keygen nennt', function (string $verfahren) {
    $werte = (new SshKeyGenerator)->erzeugen([
        'key_type' => $verfahren, 'username' => 'root', 'name' => 'Test',
    ]);

    $pfad = tempnam(sys_get_temp_dir(), 'fp').'.pub';
    file_put_contents($pfad, $werte['public_key']);

    $prozess = new Process(['ssh-keygen', '-lf', $pfad]);
    $prozess->run();
    unlink($pfad);

    // Ausgabe: "256 SHA256:... kommentar (ED25519)" - der zweite Teil zaehlt.
    $erwartet = preg_split('/\s+/', trim($prozess->getOutput()))[1];

    expect(SshKey::fingerprintVon($werte['public_key']))->toBe($erwartet);
})->with(['ed25519', 'rsa', 'ecdsa'])->group('langsam');

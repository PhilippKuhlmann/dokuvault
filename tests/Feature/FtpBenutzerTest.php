<?php

use App\Livewire\FtpBenutzer;
use App\Models\Customer;
use App\Models\FTPServer;
use App\Models\FTPUser;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('ein Server traegt mehrere Zugaenge', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['ftpserver_update']));
    $server = FTPServer::factory()->create(['customer_id' => $customer->id, 'host' => 'ftp.mustermann.de']);

    // Genau der Fall: drei Zugaenge auf einem Host, statt dreimal derselbe
    // Host in drei Zeilen.
    foreach (['steuerberater', 'webseite', 'backup'] as $name) {
        Livewire::test(FtpBenutzer::class, ['model' => $server, 'customer' => $customer])
            ->set('username', $name)
            ->set('password', 'geheim-'.$name)
            ->call('hinzufuegen')
            ->assertHasNoErrors();
    }

    expect($server->users()->count())->toBe(3);
    expect(FTPServer::where('customer_id', $customer->id)->count())->toBe(1);
});

test('das Kennwort eines Zugangs steht verschluesselt in der Datenbank', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['ftpserver_update']));
    $server = FTPServer::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(FtpBenutzer::class, ['model' => $server, 'customer' => $customer])
        ->set('username', 'sb')
        ->set('password', 'Kennwort123')
        ->call('hinzufuegen');

    $roh = DB::table('ftp_users')->where('username', 'sb')->value('password');

    expect($roh)->not->toContain('Kennwort123');
    expect($server->users()->first()->password)->toBe('Kennwort123');
});

test('ohne Benutzername kein Zugang', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['ftpserver_update']));
    $server = FTPServer::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(FtpBenutzer::class, ['model' => $server, 'customer' => $customer])
        ->set('password', 'nur-ein-kennwort')
        ->call('hinzufuegen')
        ->assertHasErrors('username');

    expect($server->users()->count())->toBe(0);
});

test('ein Zugang laesst sich entfernen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['ftpserver_update']));
    $server = FTPServer::factory()->create(['customer_id' => $customer->id]);
    $benutzer = FTPUser::factory()->create(['customer_id' => $customer->id, 'ftp_server_id' => $server->id]);

    Livewire::test(FtpBenutzer::class, ['model' => $server, 'customer' => $customer])
        ->call('entfernen', $benutzer->id);

    expect($server->users()->count())->toBe(0);
});

test('der Zugang eines fremden Servers laesst sich nicht entfernen (IDOR)', function () {
    $customer = Customer::factory()->create();
    $fremder = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['ftpserver_update']));

    $server = FTPServer::factory()->create(['customer_id' => $customer->id]);
    $fremderServer = FTPServer::factory()->create(['customer_id' => $fremder->id]);
    $fremderZugang = FTPUser::factory()->create([
        'customer_id' => $fremder->id, 'ftp_server_id' => $fremderServer->id,
    ]);

    Livewire::test(FtpBenutzer::class, ['model' => $server, 'customer' => $customer])
        ->call('entfernen', $fremderZugang->id)
        ->assertStatus(404);

    expect(FTPUser::find($fremderZugang->id))->not->toBeNull();
});

test('ohne das Recht ftpserver_update geht nichts', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['ftpserver_viewAny']));
    $server = FTPServer::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(FtpBenutzer::class, ['model' => $server, 'customer' => $customer])
        ->set('username', 'fremd')
        ->call('hinzufuegen')
        ->assertStatus(403);
});

test('die Liste zeigt den Server einmal, mit seinen Zugaengen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['ftpserver_viewAny']));
    $server = FTPServer::factory()->create(['customer_id' => $customer->id, 'host' => 'ftp.mustermann.de']);
    FTPUser::factory()->create(['customer_id' => $customer->id, 'ftp_server_id' => $server->id, 'username' => 'steuerberater']);
    FTPUser::factory()->create(['customer_id' => $customer->id, 'ftp_server_id' => $server->id, 'username' => 'webseite']);

    $this->get("/{$customer->slug}/ftpserver")
        ->assertSee('ftp.mustermann.de')
        ->assertSee('steuerberater')
        ->assertSee('webseite');
});

test('die Migration zieht bestehende Zeilen verlustfrei um und fasst gleiche Hosts zusammen', function () {
    $customer = Customer::factory()->create();

    // Der Zustand vor der Umstellung nachgebaut: Spalten am Server, drei
    // Zeilen mit demselben Host.
    Schema::table('ftp_servers', function ($table) {
        $table->string('username')->nullable();
        $table->text('password')->nullable();
    });

    $anlegen = function (string $host, string $benutzer, string $kennwort, ?string $beschreibung) use ($customer) {
        DB::table('ftp_servers')->insert([
            'customer_id' => $customer->id, 'host' => $host, 'description' => $beschreibung,
            'username' => $benutzer, 'password' => Crypt::encryptString($kennwort),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };

    $anlegen('ftp.mustermann.de', 'steuerberater', 'geheim1', 'Datenaustausch');
    $anlegen('ftp.mustermann.de', 'webseite', 'geheim2', 'Deploy');
    $anlegen('ftp.extern.de', 'backup', 'geheim3', 'Backup extern');

    DB::table('ftp_users')->delete();
    $migration = require database_path('migrations/2026_08_25_120000_split_ftp_users_from_servers.php');

    // Die Migration legt die Tabelle selbst an - hier ist sie schon da.
    Schema::dropIfExists('ftp_users');
    $migration->up();

    // Zwei Server statt drei Zeilen: gleicher Host wird ein Server.
    $server = FTPServer::where('customer_id', $customer->id)->with('users')->get();
    expect($server)->toHaveCount(2);

    $mustermann = $server->firstWhere('host', 'ftp.mustermann.de');
    expect($mustermann->users)->toHaveCount(2);
    expect($mustermann->users->pluck('username')->sort()->values()->all())
        ->toBe(['steuerberater', 'webseite']);

    // Die Kennwoerter kommen unveraendert an.
    expect($mustermann->users->firstWhere('username', 'steuerberater')->password)->toBe('geheim1');

    // Die abweichende Beschreibung der zusammengefassten Zeile geht nicht
    // verloren - sie steht in der Notiz des Zugangs.
    expect($mustermann->users->firstWhere('username', 'webseite')->note)->toBe('Deploy');
});

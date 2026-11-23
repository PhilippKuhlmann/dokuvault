<?php

use App\Livewire\CopyPasswordButton;
use App\Livewire\PdfExportStatus;
use App\Livewire\SecretField;
use App\Models\Customer;
use App\Models\PdfExport;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Support\CustomerAccess;
use App\Support\PasswordReveal;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * The level "read without passwords": the documentation, but no password,
 * key or PIN in plaintext - not revealed, not copied, not in the PDF, not
 * handed to the remote tool. "read" keeps all of that.
 */

function secretsWorld(string $level): array
{
    $own = Customer::factory()->create(['name' => 'Eigene Firma']);
    $site = Site::factory()->create(['customer_id' => $own->id]);
    $server = Server::forceCreate([
        'customer_id' => $own->id, 'site_id' => $site->id, 'name' => 'SRV-GF', 'form_factor' => 'tower',
        'bmcPassword' => 'Bmc-Geheim-1', 'remoteID' => '123456789', 'remotePassword' => 'Fern-Geheim-1',
    ]);

    $team = Team::create(['name' => 'Technik']);
    $own->teams()->attach($team->id, ['level' => $level]);

    $user = userWithPermissions(['server_viewAny', 'logingeneral_viewAny', 'create_pdf']);
    $team->members()->attach($user->id);

    return [$own, $server, $user];
}

test('without passwords: the service refuses, whatever button was clicked', function () {
    [, $server, $user] = secretsWorld(CustomerAccess::READ_NO_SECRETS);
    $this->actingAs($user);

    expect(fn () => app(PasswordReveal::class)->fromField(Server::class, $server->id, 'bmcPassword', $user))
        ->toThrow(HttpException::class);
});

test('without passwords: the field shows a lock instead of eye and copy', function () {
    [, $server, $user] = secretsWorld(CustomerAccess::READ_NO_SECRETS);
    $this->actingAs($user);

    Livewire::test(SecretField::class, ['modell' => Server::class, 'id' => $server->id, 'feld' => 'bmcPassword'])
        ->assertSet('mayReveal', false)
        ->assertSee('Kennwörter sind für dein Team bei diesem Kunden verborgen.')
        ->assertDontSee('wire:click="show"', false)
        ->assertDontSee('Bmc-Geheim-1');

    Livewire::test(CopyPasswordButton::class, ['device' => $server])->assertSet('links', []);
});

test('without passwords: no PDF and no remote link', function () {
    [$own, $server, $user] = secretsWorld(CustomerAccess::READ_NO_SECRETS);
    $this->actingAs($user);

    $this->post(route('customer.view-pdf', $own))->assertForbidden();
    Livewire::test(PdfExportStatus::class, ['customer' => $own])->call('start')->assertForbidden();
    $this->get(route('remote.connect', ['server', $server->id]))->assertForbidden();
    $this->get(route('server.index', $own))->assertOk()
        ->assertSee('Nur lesen, ohne Kennwörter')
        ->assertDontSee(route('remote.connect', ['server', $server->id]));
});

test('read: passwords may be revealed and a PDF ordered - nothing else written', function () {
    [$own, $server, $user] = secretsWorld(CustomerAccess::READ);
    $this->actingAs($user);

    expect(app(PasswordReveal::class)->fromField(Server::class, $server->id, 'bmcPassword', $user))->toBe('Bmc-Geheim-1');

    Livewire::test(PdfExportStatus::class, ['customer' => $own])->call('start')->assertOk();
    expect(PdfExport::where('customer_id', $own->id)->exists())->toBeTrue();
});

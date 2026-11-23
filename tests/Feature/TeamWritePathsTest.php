<?php

use App\Livewire\AdminTrash;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\LicenseAccess;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Support\CustomerAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Write paths that pass neither a {customer} in the route nor the model
 * events: mass deletes, pivots, files written before the row. Found in a
 * review - for the level "read" every one of them must stop, before it
 * has touched anything.
 */

/** A customer the user's team only reads, a server on it with an IP address. */
function readWorld(array $rights): array
{
    $customer = Customer::factory()->create(['name' => 'Eigene Firma']);
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $server = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-GF', 'form_factor' => 'tower']);
    $ip = IpAddress::forceCreate(['customer_id' => $customer->id, 'address' => '10.0.0.5', 'ipable_type' => $server->getMorphClass(), 'ipable_id' => $server->id]);

    $team = Team::create(['name' => 'Technik']);
    $customer->teams()->attach($team->id, ['level' => CustomerAccess::READ]);
    $user = userWithPermissions($rights);
    $team->members()->attach($user->id);

    return [$customer, $server, $ip, $user];
}

test('mobile app: no IP address removed or added on a customer the team only reads', function () {
    [, $server, $ip, $user] = readWorld(['server_viewAny', 'server_update']);
    $token = $user->createToken('App', ['mobil'], now()->addDay())->plainTextToken;

    $this->withToken($token)->deleteJson("/api/mobile/objects/server/{$server->id}/ip-addresses/{$ip->id}")->assertForbidden();
    $this->withToken($token)->postJson("/api/mobile/objects/server/{$server->id}/ip-addresses", ['address' => '10.0.0.9'])->assertForbidden();

    expect(IpAddress::whereKey($ip->id)->exists())->toBeTrue()
        ->and(IpAddress::where('address', '10.0.0.9')->exists())->toBeFalse();
});

test('mobile app: an upload does not replace the stored file first', function () {
    Storage::fake('local');
    [$customer, , , $user] = readWorld(['licenseaccess_viewAny', 'licenseaccess_update']);
    Storage::disk('local')->put($customer->slug.'/licenseaccess/alt.pdf', 'original');
    $licence = LicenseAccess::withoutEvents(fn () => LicenseAccess::forceCreate([
        'customer_id' => $customer->id, 'name' => 'Lizenz', 'key' => 'ABC-123', 'file_path' => $customer->slug.'/licenseaccess/alt.pdf',
    ]));
    $token = $user->createToken('App', ['mobil'], now()->addDay())->plainTextToken;

    $this->withToken($token)->post("/api/mobile/objects/licenseaccess/{$licence->id}", [
        '_method' => 'PUT', 'name' => 'Lizenz', 'datei' => UploadedFile::fake()->create('neu.pdf', 10),
    ], ['Accept' => 'application/json'])->assertForbidden();

    Storage::disk('local')->assertExists($customer->slug.'/licenseaccess/alt.pdf');
    expect(Storage::disk('local')->allFiles($customer->slug))->toBe([$customer->slug.'/licenseaccess/alt.pdf']);
});

test('trash: "delete for good" leaves a read customer untouched, not half deleted', function () {
    [$customer, $server, $ip] = readWorld([]);
    $admin = userWithPermissions(['admin_trash']);
    Team::firstWhere('name', 'Technik')->members()->attach($admin->id);
    Server::withoutEvents(fn () => $server->delete());
    $this->actingAs($admin);

    Livewire::test(AdminTrash::class)->call('delete', 'server', $server->id)->assertForbidden();
    Livewire::test(AdminTrash::class)->call('deleteAll');

    expect(Server::withTrashed()->whereKey($server->id)->exists())->toBeTrue()
        ->and(IpAddress::whereKey($ip->id)->exists())->toBeTrue();
});

test('the public API keeps teams and levels when it creates a customer', function () {
    $user = userWithPermissions(['admin_customer', 'admin_team']);
    $team = Team::create(['name' => 'Chef']);
    $team->members()->attach($user->id);
    $this->actingAs($user);

    $this->postJson('/api/customers', ['name' => 'API-Kunde', 'teams' => [$team->id], 'levels' => [$team->id => CustomerAccess::FULL]])
        ->assertCreated();

    expect(Customer::firstWhere('name', 'API-Kunde')->teams()->pluck('name')->all())->toBe(['Chef']);
});

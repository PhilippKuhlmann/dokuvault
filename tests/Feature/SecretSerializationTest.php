<?php

use App\Http\Controllers\API\ServerController;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\Server;
use App\Models\Site;
use App\Models\Wifi;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * No secret leaves a model through toArray() or JSON. The public API
 * returns models as they are; only the server listed its passwords in
 * $hidden. Found here by itself, like VerschluesselteSpaltenTest: every
 * column encrypted by an accessor or an "encrypted" cast.
 */

/** @return list<array{0: class-string<Model>, 1: string}> */
function encryptedColumnsOfAllModels(): array
{
    $found = [];

    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');
        if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $model = new $class;
        $columns = Schema::hasTable($model->getTable()) ? Schema::getColumnListing($model->getTable()) : [];
        $lines = file($file);

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || (string) $method->getReturnType() !== Attribute::class) {
                continue;
            }
            $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
            if (str_contains($body, 'Crypt::encryptString')) {
                $found[] = [$class, in_array($method->getName(), $columns, true) ? $method->getName() : Str::snake($method->getName())];
            }
        }

        foreach ($model->getCasts() as $column => $cast) {
            if (str_starts_with((string) $cast, 'encrypted')) {
                $found[] = [$class, $column];
            }
        }
    }

    return $found;
}

test('every encrypted column is hidden from toArray and JSON', function () {
    $all = encryptedColumnsOfAllModels();
    // The search must find them - an empty list would pass without a look.
    expect(count($all))->toBeGreaterThan(20);

    $open = collect($all)
        ->reject(fn ($pair) => in_array($pair[1], (new $pair[0])->getHidden(), true))
        ->map(fn ($pair) => class_basename($pair[0]).'.'.$pair[1])
        ->values()->all();

    expect($open)->toBe([]);
});

test('a firewall, a Wi-Fi and a server go out as JSON without their secrets', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $firewall = Firewall::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'FW', 'usc_pin' => '4711-geheim', 'cloud_backup_password' => 'Cloud-Geheim']);
    $wifi = Wifi::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'ssid' => 'Gast', 'encryption' => 'WPA2', 'password' => 'WLAN-Geheim']);

    $json = $firewall->toJson().$wifi->toJson();

    expect($json)->not->toContain('4711-geheim')->not->toContain('Cloud-Geheim')->not->toContain('WLAN-Geheim')
        ->and($firewall->usc_pin)->toBe('4711-geheim');
});

test('the public API returns a server without its passwords', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $server = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV', 'form_factor' => 'tower', 'bmcPassword' => 'Bmc-Geheim', 'remotePassword' => 'Fern-Geheim']);
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $body = json_encode(app(ServerController::class)->show($server));

    expect($body)->toContain('SRV')->not->toContain('Bmc-Geheim')->not->toContain('Fern-Geheim');
});

<?php

use App\Http\Controllers\ObjectListController;
use App\Models\Customer;
use App\Models\Printer;
use App\Models\Server;
use App\Models\VM;
use App\Policies\ResourcePolicy;
use Illuminate\Support\Facades\Gate;

/**
 * One controller, one view and one policy class for all the kinds.
 *
 * Before, each kind had a controller, a view and a generated policy of its
 * own - 123 files that said the same thing with a different name. What these
 * tests hold on to is what those files guaranteed one by one.
 */
test('every kind without a page of its own has its list page', function () {
    $customer = Customer::factory()->create();

    // In one test and not as a dataset: the kinds come from config/forms.php,
    // and a dataset is resolved before the application - and its config - is
    // up. One user for all of them: a second user in the same session is
    // signed out by the password-hash check and lands on the login page.
    $types = ObjectListController::types();
    $this->actingAs(userWithPermissions(array_map(fn (string $type) => $type.'_viewAny', $types)));

    foreach ($types as $type) {
        $this->get(route($type.'.index', $customer))
            ->assertOk()
            ->assertViewIs('object.index')
            ->assertViewHas('type', $type)
            ->assertSeeLivewire('object-list');
    }
});

test('a list page needs the right to list exactly that kind', function () {
    $customer = Customer::factory()->create();

    // The right for printers opens the printers, not the servers.
    $this->actingAs(userWithPermissions(['printer_viewAny']));

    $this->get(route('printer.index', $customer))->assertOk();
    $this->get(route('server.index', $customer))->assertForbidden();
});

test('the kinds with a page of their own are not listed twice', function () {
    foreach (ObjectListController::OWN_PAGE as $type) {
        expect(config('forms'))->toHaveKey($type)
            ->and(ObjectListController::types())->not->toContain($type)
            ->and(Route::has($type.'.index'))->toBeTrue("{$type} has no list page at all.");
    }

    // 38 at the time of writing; a handful would mean config/forms.php was not read.
    expect(count(ObjectListController::types()))->toBeGreaterThan(30);
});

test('every kind has its four gates and its policy', function () {
    foreach (config('custom.permissions') as $resource) {
        $name = strtolower($resource);
        $model = 'App\\Models\\'.$resource;

        expect(class_exists($model))->toBeTrue("{$model} does not exist.")
            ->and(Gate::getPolicyFor($model))->toBeInstanceOf(ResourcePolicy::class);

        foreach (['viewAny', 'create', 'update', 'delete'] as $ability) {
            expect(Gate::has("{$name}_{$ability}"))->toBeTrue("Gate {$name}_{$ability} is missing.");
        }
    }
});

test('gate and policy answer the same, and only for the kind asked about', function () {
    $user = userWithPermissions(['server_viewAny', 'server_update', 'printer_delete']);
    $gate = Gate::forUser($user);

    $expected = [
        'server' => ['viewAny' => true, 'create' => false, 'update' => true, 'delete' => false],
        'printer' => ['viewAny' => false, 'create' => false, 'update' => false, 'delete' => true],
        'vm' => ['viewAny' => false, 'create' => false, 'update' => false, 'delete' => false],
    ];
    $models = ['server' => Server::class, 'printer' => Printer::class, 'vm' => VM::class];

    foreach ($expected as $name => $abilities) {
        foreach ($abilities as $ability => $allowed) {
            expect($gate->allows("{$name}_{$ability}"))->toBe($allowed, "{$name}_{$ability}")
                ->and($gate->allows($ability, $models[$name]))->toBe($allowed, "{$ability} on the class {$name}")
                ->and($gate->allows($ability, new $models[$name]))->toBe($allowed, "{$ability} on an instance of {$name}");
        }
    }
});

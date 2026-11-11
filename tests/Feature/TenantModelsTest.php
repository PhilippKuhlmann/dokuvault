<?php

use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Concerns\OwnedByCustomer;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Scopes\OwnCustomerScope;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant separation as a property of the models, not of the queries.
 *
 * Every list filters by customer itself - 150 times `where('customer_id')`,
 * each written by hand. These tests hold the net below that: a model with a
 * customer_id carries OwnedByCustomer, and with it a customer login reads
 * rows of its own customer only, whatever the query says.
 */

/** Every Eloquent model in app/Models, as class names. */
function allModels(): array
{
    return collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file) => 'App\\Models\\'.basename($file, '.php'))
        ->filter(fn (string $class) => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract())
        ->values()
        ->all();
}

/** The models whose table has a customer_id - without User, see below. */
function tenantModels(): array
{
    return array_values(array_filter(
        allModels(),
        fn (string $class) => $class !== User::class && Schema::hasColumn((new $class)->getTable(), 'customer_id'),
    ));
}

function customerLogin(Customer $customer): User
{
    $user = userWithPermissions(['server_viewAny']);
    $user->forceFill(['customer_id' => $customer->id])->save();

    return $user;
}

/** A server of this customer - with the site and operating system its table asks for. */
function tenantServer(Customer $customer, string $name): Server
{
    return Server::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'OS of '.$name])->id,
        'name' => $name,
    ]);
}

test('every model with a customer_id carries OwnedByCustomer', function () {
    $missing = array_filter(
        tenantModels(),
        fn (string $class) => ! in_array(OwnedByCustomer::class, class_uses_recursive($class), true),
    );

    // More than a handful, or the filter above found nothing and the test
    // would pass for the wrong reason.
    expect(count(tenantModels()))->toBeGreaterThan(50)
        ->and(array_values($missing))->toBe([], 'Without OwnedByCustomer: '.implode(', ', $missing));
});

test('no model without a customer_id carries OwnedByCustomer', function () {
    // The other direction: on a table without the column the scope would
    // break every query of a customer login with "unknown column".
    $wrong = array_filter(
        allModels(),
        fn (string $class) => in_array(OwnedByCustomer::class, class_uses_recursive($class), true)
            && ! Schema::hasColumn((new $class)->getTable(), 'customer_id'),
    );

    expect(array_values($wrong))->toBe([]);
});

test('User is not scoped - the scope reads the signed-in user through it', function () {
    // users.customer_id says which customer a login is bound to, not whom a
    // row belongs to. And a scope on User would run while the guard is still
    // loading the user it asks for.
    expect(in_array(OwnedByCustomer::class, class_uses_recursive(User::class), true))->toBeFalse();
});

test('a customer login reads no row of another customer from any model', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();

    // One row per model for the other customer, written past Eloquent: not
    // every model has a factory, and the point here is the reading.
    $seeded = [];

    foreach (tenantModels() as $class) {
        $table = (new $class)->getTable();
        $row = ['customer_id' => $other->id];

        foreach (Schema::getColumns($table) as $column) {
            if ($column['name'] === 'customer_id' || $column['nullable'] || $column['default'] !== null || $column['auto_increment']) {
                continue;
            }

            $row[$column['name']] = match (true) {
                $column['name'] === 'site_id' => Site::factory()->create(['customer_id' => $other->id])->id,
                str_ends_with($column['name'], '_id') => 1,
                str_contains($column['type_name'], 'int'), str_contains($column['type_name'], 'bool') => 1,
                str_contains($column['type_name'], 'date'), str_contains($column['type_name'], 'time') => now(),
                default => 'x',
            };
        }

        try {
            Schema::disableForeignKeyConstraints();
            DB::table($table)->insert($row);
            $seeded[] = $class;
        } catch (Throwable) {
            // A table with a constraint this generic row does not satisfy.
            // The count below makes sure these stay the exception.
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    // Without a user - console, queue, agent API - the rows are there. Only
    // what is readable now can prove anything below: SshKey shares its table
    // with the logins and does not see a row of the kind "password" anyway.
    $readable = array_values(array_filter(
        $seeded,
        fn (string $class) => $class::query()->where('customer_id', $other->id)->exists(),
    ));

    expect(count($readable))->toBeGreaterThan(45);

    $this->actingAs(customerLogin($own));

    foreach ($readable as $class) {
        // The query "forgets" the customer on purpose.
        expect($class::query()->where('customer_id', $other->id)->exists())
            ->toBeFalse("{$class} hands a customer login a row of another customer.");
    }
});

test('technicians and admins are not scoped', function () {
    $other = Customer::factory()->create();
    $server = tenantServer($other, 'SRV-OTHER');

    $this->actingAs(userWithPermissions(['server_viewAny']));

    expect(Server::find($server->id))->not->toBeNull();
});

test('a customer login cannot change or delete a row of another customer through a query', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();
    $server = tenantServer($other, 'SRV-OTHER');

    $this->actingAs(customerLogin($own));

    expect(Server::whereKey($server->id)->update(['name' => 'TAKEN']))->toBe(0)
        ->and(Server::whereKey($server->id)->delete())->toBe(0);

    $fresh = acrossCustomers(Server::class)->find($server->id);

    expect($fresh->name)->toBe('SRV-OTHER')->and($fresh->trashed())->toBeFalse();
});

test('shared defaults without a customer stay visible to a customer login', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();

    $template = ChecklistTemplate::query()->first()
        ?? ChecklistTemplate::create(['kind' => 'onboarding', 'name' => 'Onboarding']);

    $base = ['checklist_template_id' => $template->id, 'section' => 'x', 'sort' => 1];
    $standard = ChecklistTemplateItem::create($base + ['title' => 'STANDARD', 'customer_id' => null]);
    $mine = ChecklistTemplateItem::create($base + ['title' => 'MINE', 'customer_id' => $own->id]);
    $theirs = ChecklistTemplateItem::create($base + ['title' => 'THEIRS', 'customer_id' => $other->id]);

    $this->actingAs(customerLogin($own));

    $visible = ChecklistTemplateItem::whereKey([$standard->id, $mine->id, $theirs->id])->pluck('title')->sort()->values()->all();

    expect($visible)->toBe(['MINE', 'STANDARD']);
});

test('forCustomer filters by customer, given a model or an id', function () {
    $a = Customer::factory()->create();
    $b = Customer::factory()->create();
    tenantServer($a, 'A');
    tenantServer($b, 'B');

    expect(Server::forCustomer($a)->pluck('name')->all())->toBe(['A'])
        ->and(Server::forCustomer($b->id)->pluck('name')->all())->toBe(['B']);
});

test('the scope has a name of its own so a query can lift it deliberately', function () {
    expect((new Server)->getGlobalScopes())->toHaveKey(OwnCustomerScope::class);
});

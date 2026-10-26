<?php

use App\Livewire\IpBereiche;
use App\Livewire\OnboardingForm;
use App\Models\Customer;
use App\Models\IpRange;
use App\Models\Network;
use App\Models\Site;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

// Security audit finding: IpBereiche checked the customer in mount() only.
// customerId and networkId stayed writable from the browser, so a customer
// user could switch them to another customer's network after mounting and
// read, create and delete its IP ranges.

function lockedTestNetwork(Customer $customer, string $network): Network
{
    return Network::create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'description' => 'Clients', 'vlanId' => 10,
        'network' => $network, 'subnetmask' => '255.255.255.0', 'cidr' => 24,
    ]);
}

test('IpBereiche cannot be pointed at another customer after mounting', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();
    $ownNetwork = lockedTestNetwork($own, '10.1.1.0');
    $otherNetwork = lockedTestNetwork($other, '10.2.2.0');
    $otherRange = IpRange::create([
        'customer_id' => $other->id, 'network_id' => $otherNetwork->id,
        'from_ip' => '10.2.2.10', 'to_ip' => '10.2.2.20', 'label' => 'FREMD-GEHEIM',
    ]);

    $user = userWithPermissions(['network_viewAny', 'network_update']);
    $user->update(['customer_id' => $own->id]);
    $this->actingAs($user);

    $component = Livewire::test(IpBereiche::class, ['customer' => $own, 'network' => $ownNetwork])
        ->assertDontSee('FREMD-GEHEIM');

    expect(fn () => $component->set('customerId', $other->id))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $component->set('networkId', $otherNetwork->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // A foreign range id on the own network deletes nothing.
    $component->call('loeschen', $otherRange->id);
    expect(IpRange::find($otherRange->id))->not->toBeNull();
});

test('the onboarding form cannot be pointed at another customer', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();

    $user = userWithPermissions(['checklist_request']);
    $user->update(['customer_id' => $own->id]);
    $this->actingAs($user);

    $component = Livewire::test(OnboardingForm::class, ['customer' => $own]);

    expect(fn () => $component->set('customerId', $other->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('every id a Livewire component takes from mount() is locked', function () {
    // Guard for components to come: a public "int $somethingId" is the
    // context a component was opened for - customer, network, rack, run.
    // Writable from the browser, it moves every later action to another
    // record past the check in mount(). Form fields that the user picks
    // are "?int" or untyped and validated, so they are not caught here.
    $unlocked = [];

    foreach (Finder::create()->files()->in(app_path('Livewire'))->name('*.php') as $file) {
        $class = 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
            continue;
        }

        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();

            if ($property->getDeclaringClass()->getName() !== $class
                || ! str_ends_with($property->getName(), 'Id')
                || ! $type instanceof ReflectionNamedType
                || $type->getName() !== 'int'
                || $type->allowsNull()) {
                continue;
            }

            if ($property->getAttributes(Locked::class) === []) {
                $unlocked[] = class_basename($class).'::$'.$property->getName();
            }
        }
    }

    expect($unlocked)->toBe([], 'Not #[Locked]: '.implode(', ', $unlocked));
});

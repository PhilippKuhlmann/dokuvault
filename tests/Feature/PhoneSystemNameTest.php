<?php

use App\Models\Customer;
use App\Models\PhoneSystem;
use App\Models\SipAccount;
use App\Models\Site;

test('a phone system gets a name through the modal and shows it on the card', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['phonesystem_create', 'phonesystem_viewAny']));

    imModal('phonesystem', $customer, [
        'site_id' => $site->id,
        'name' => 'TK-Zentrale',
        'manufacturer' => 'Auerswald',
        'model' => 'COMpact 5500',
    ])->assertHasNoErrors();

    expect(PhoneSystem::where('name', 'TK-Zentrale')->value('manufacturer'))->toBe('Auerswald');

    $this->get(route('phonesystem.index', $customer))
        ->assertOk()
        ->assertSee('TK-Zentrale')
        ->assertSee('Auerswald COMpact 5500');
});

test('the SIP account names its phone system by name', function () {
    $customer = Customer::factory()->create();
    $anlage = PhoneSystem::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'name' => 'TK-Filiale',
    ]);
    $sip = SipAccount::factory()->create(['customer_id' => $customer->id, 'phone_system_id' => $anlage->id]);

    expect($sip->phoneSystemLabel())->toBe('TK-Filiale');
});

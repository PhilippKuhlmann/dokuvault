<?php

use App\Models\Customer;
use App\Models\PhoneSystem;
use App\Models\SipAccount;
use App\Models\Site;

test('a SIP trunk is saved through the modal', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $anlage = PhoneSystem::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);
    $this->actingAs(userWithPermissions(['sipaccount_create']));

    imModal('sipaccount', $customer, [
        'provider' => 'Deutsche Telekom',
        'account_type' => 'trunk',
        'site_id' => $site->id,
        'main_number' => '040 123456',
        'number_range' => '0-99',
        'channels' => '8',
        'phone_system_id' => $anlage->id,
    ])->assertHasNoErrors();

    $anschluss = SipAccount::where('provider', 'Deutsche Telekom')->first();
    expect($anschluss->customer_id)->toBe($customer->id);
    expect($anschluss->isTrunk())->toBeTrue();
    expect($anschluss->numbersSummary())->toBe('040 123456 (0-99)');
    expect($anschluss->phone_system_id)->toBe($anlage->id);
});

test('single numbers are listed one per line', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['sipaccount_create']));

    imModal('sipaccount', $customer, [
        'provider' => 'sipgate',
        'account_type' => 'single',
        'numbers' => "040 1111\n\n040 2222\n",
    ])->assertHasNoErrors();

    expect(SipAccount::where('provider', 'sipgate')->first()->numbersSummary())->toBe('040 1111, 040 2222');
});

test('a new SIP account does not claim an account type', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['sipaccount_create']));

    imModal('sipaccount', $customer, ['provider' => 'easybell'])->assertHasNoErrors();

    expect(SipAccount::where('provider', 'easybell')->first()->account_type)->toBeNull();
});

test('a phone system of another customer is rejected', function () {
    $customer = Customer::factory()->create();
    $fremd = Customer::factory()->create();
    $fremdeAnlage = PhoneSystem::factory()->create([
        'customer_id' => $fremd->id,
        'site_id' => Site::factory()->create(['customer_id' => $fremd->id])->id,
    ]);
    $this->actingAs(userWithPermissions(['sipaccount_create']));

    imModal('sipaccount', $customer, [
        'provider' => 'Placetel', 'phone_system_id' => $fremdeAnlage->id,
    ])->assertHasErrors('form.phone_system_id');
});

test('the list page shows the SIP account as a card', function () {
    $customer = Customer::factory()->create();
    SipAccount::factory()->create(['customer_id' => $customer->id, 'provider' => 'Telekom SIP-Trunk']);
    $this->actingAs(userWithPermissions(['sipaccount_viewAny']));

    $this->get(route('sipaccount.index', $customer))
        ->assertOk()
        ->assertSee('Telekom SIP-Trunk');
});

<?php

use App\Livewire\GeheimFeld;
use App\Models\Customer;
use App\Models\Mailbox;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// Cards that showed a password as plain text in the HTML (mailbox, licence,
// backup, PPPoE, remote support) now fetch it on click like every other
// secret - with an entry in the log.

test('the mailbox card does not deliver the password, the click fetches and logs it', function () {
    $customer = Customer::factory()->create();
    $mailbox = Mailbox::factory()->create(['customer_id' => $customer->id, 'password' => 'Mb-4kR2z']);
    $this->actingAs($nutzer = userWithPermissions(['mailbox_viewAny']));

    $this->get(route('mailbox.index', $customer))
        ->assertOk()
        ->assertSee('geheim-feld', false)
        ->assertDontSee('Mb-4kR2z', false);

    Livewire::test(GeheimFeld::class, ['modell' => Mailbox::class, 'id' => $mailbox->id, 'feld' => 'password'])
        ->call('zeigen')
        ->assertSet('wert', 'Mb-4kR2z');

    expect(Activity::where('event', 'kennwort_angesehen')->where('causer_id', $nutzer->id)
        ->where('subject_id', $mailbox->id)->exists())->toBeTrue();
});

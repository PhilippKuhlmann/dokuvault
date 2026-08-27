<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Site;
use App\Models\VM;

test('VM mit im Papierkorb liegendem Betriebssystem: Liste und Bearbeiten laden ohne Fehler', function () {
    $this->actingAs(userWithPermissions(['vm_viewAny', 'vm_update']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);

    $vm = VM::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'operating_system_id' => $os->id,
    ]);

    // OS in den Papierkorb -> $vm->operatingSystem liefert null (Trashed ausgeschlossen).
    // Vorher stürzten beide Seiten beim Zugriff auf null->name / null->id ab.
    $os->delete();

    $this->get("/{$customer->slug}/vm")->assertStatus(200)->assertSee($vm->name);
    expect(modalHtml('vm', $customer, $vm->id))->not->toBe('');
});

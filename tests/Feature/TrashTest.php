<?php

use App\Models\Camera;
use App\Models\Customer;
use App\Models\Site;

function trashedCamera(Customer $customer): Camera
{
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $camera = Camera::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'CAM-Geloescht',
    ]);
    $camera->delete();

    return $camera;
}

test('gelöschtes Objekt erscheint im Papierkorb', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'camera_viewAny', 'camera_delete']));

    $customer = Customer::factory()->create();
    trashedCamera($customer);

    $this->get("/{$customer->slug}/trash")
        ->assertStatus(200)
        ->assertSee('CAM-Geloescht')
        ->assertSee('Kamera');
});

test('Wiederherstellen stellt das Objekt wieder her', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'camera_viewAny', 'camera_delete']));

    $customer = Customer::factory()->create();
    $camera = trashedCamera($customer);

    $this->post("/{$customer->slug}/trash/camera/{$camera->id}/restore")
        ->assertRedirect("/{$customer->slug}/trash");

    expect($camera->fresh()->deleted_at)->toBeNull();
});

test('Restore eines fremden Kunden-Objekts wird verweigert (IDOR)', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'camera_viewAny', 'camera_delete']));

    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();
    $cameraB = trashedCamera($customerB);

    // Versuch, Kamera von Kunde B über Kunde A wiederherzustellen
    $this->post("/{$customerA->slug}/trash/camera/{$cameraB->id}/restore")
        ->assertStatus(404);

    expect($cameraB->fresh()->deleted_at)->not->toBeNull();
});

test('Typ außerhalb der Whitelist ergibt 404', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));

    $customer = Customer::factory()->create();

    $this->post("/{$customer->slug}/trash/user/1/restore")->assertStatus(404);
});

test('ohne see_hidden kein Zugriff auf den Papierkorb', function () {
    $this->actingAs(userWithPermissions([]));

    $customer = Customer::factory()->create();

    $this->get("/{$customer->slug}/trash")->assertStatus(403);
});

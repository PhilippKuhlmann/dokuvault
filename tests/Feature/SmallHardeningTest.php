<?php

use App\Models\Customer;
use App\Support\Dateiname;
use App\Support\DomainCheck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

// Security audit hardening notes, small ones: the RDAP URL took the stored
// domain raw, uploaded files kept the browser's extension raw, and the
// customer slug - storage folder and URL segment - could be set from a form.

function rdapLookup(string $domain): array
{
    $check = app(DomainCheck::class);
    $method = new ReflectionMethod($check, 'rdap');

    return $method->invoke($check, $domain);
}

test('a stored domain with path characters does not change the RDAP address', function () {
    Http::fake([
        DomainCheck::BOOTSTRAP_URL => Http::response(['services' => [[['de', 'com'], ['https://rdap.test/']]]]),
        'rdap.test/*' => Http::response(['events' => []]),
    ]);

    // Ends in .de, so a server is found - only the name check stops it.
    expect(rdapLookup('kunde.de/../../fremd.de'))->toHaveKey('error');

    Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://rdap.test/'));
});

test('umlaut domains are asked in their ASCII form', function () {
    Http::fake([
        DomainCheck::BOOTSTRAP_URL => Http::response(['services' => [[['de', 'com'], ['https://rdap.test/']]]]),
        'rdap.test/*' => Http::response(['events' => []]),
    ]);

    rdapLookup('müller.de');

    Http::assertSent(fn ($request) => $request->url() === 'https://rdap.test/domain/xn--mller-kva.de');
});

test('the stored extension of an upload is cleaned like the name', function () {
    $file = UploadedFile::fake()->create("vertrag.PD\"F\r\nX-Header: 1", 4);

    expect(Dateiname::extension($file))->toMatch('/^[a-z0-9_-]+$/')
        ->and(Dateiname::extension(UploadedFile::fake()->create('Rechnung.PDF', 4)))->toBe('pdf');
});

test('the customer slug cannot be set from the form', function () {
    $customer = Customer::factory()->create(['name' => 'Muster GmbH']);
    $slug = $customer->fresh()->slug;
    $this->actingAs(adminUser());

    $this->patch(route('admin.customer.update', $customer), [
        'name' => 'Muster GmbH', 'slug' => 'fremder-kunde',
    ]);

    expect($customer->fresh()->slug)->toBe($slug);
});

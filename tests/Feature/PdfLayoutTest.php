<?php

use App\Models\Customer;
use App\Models\InternetConnection;
use App\Models\Site;
use Illuminate\Support\Facades\View;

// Guards the layout the sheet was rebuilt on. Every one of these stood in a
// rendered PDF before: labels without a value, text printed across its
// neighbour, and headings that no longer told the job which page they are on.

function layoutHtml(Customer $customer): string
{
    View::share('pdfOwner', userWithPermissions([
        'internetconnection_viewAny', 'site_viewAny',
    ]));

    try {
        return view('pdf.customer', [
            'customer' => $customer,
            'svgDir' => sys_get_temp_dir(),
            'toc' => [],
        ])->render();
    } finally {
        View::share('pdfOwner', null);
    }
}

function layoutCustomer(): Customer
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    InternetConnection::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'provider' => 'Deutsche Telekom',
        'product' => 'DeutschlandLAN Glasfaser 1000',
        // PPPoE and the routed subnet stay empty on purpose: those two groups
        // have to disappear, not stand there as bare labels.
        'subnet' => null,
        'pppoe_user' => null,
    ]);

    return $customer;
}

test('a field without a value is left out instead of printed as a bare label', function () {
    $html = layoutHtml(layoutCustomer());

    expect($html)->toContain('Anbieter')
        ->and($html)->not->toContain('PPPoE')
        ->and($html)->not->toContain('Geroutetes Netz');
});

test('an empty group does not stay behind as a placeholder', function () {
    // DomPDF collapses a float without content to zero width, which pushed
    // every group after it into the wrong column. The groups are laid out in
    // a table since, two to a row.
    $html = layoutHtml(layoutCustomer());

    expect($html)->toContain('class="groups"')
        ->and(substr_count($html, 'group-cell'))->toBeGreaterThan(0);
});

test('a card without a single value says so', function () {
    $customer = Customer::factory()->create();
    Site::factory()->create([
        'customer_id' => $customer->id,
        'street' => null, 'house_number' => null, 'zip' => null, 'city' => null,
    ]);

    expect(layoutHtml($customer))->toContain('keine Angaben');
});

test('every section heading carries the mark the page numbers are read from', function () {
    // Without data-toc the job finds no section, and both the table of
    // contents and the running head stay empty - silently.
    $html = layoutHtml(layoutCustomer());

    $ueberschriften = substr_count($html, 'class="heading"');

    expect($ueberschriften)->toBeGreaterThan(0)
        ->and(substr_count($html, 'data-toc='))->toBe($ueberschriften);
});

test('without a table of contents the sheet has no contents page', function () {
    expect(layoutHtml(layoutCustomer()))->not->toContain('class="toc"');
});

test('the cover says the document holds credentials', function () {
    expect(layoutHtml(layoutCustomer()))->toContain('Zugangsdaten und Kennwörter');
});

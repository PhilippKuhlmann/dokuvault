<?php

use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\View;

// Security audit finding: the customer PDF contained the passwords of every
// kind of object, whatever the person who ordered it may see in the browser.

function pdfHtml(Customer $customer, ?User $owner): string
{
    View::share('pdfOwner', $owner);

    try {
        return view('pdf.customer', ['customer' => $customer, 'svgDir' => sys_get_temp_dir()])->render();
    } finally {
        View::share('pdfOwner', null);
    }
}

function pdfCustomer(): Customer
{
    $customer = Customer::factory()->create();
    rackTestServer($customer, Site::factory()->create(['customer_id' => $customer->id]))
        ->update(['bmcPassword' => 'BMC-GEHEIM']);
    LoginGeneral::create([
        'customer_id' => $customer->id, 'description' => 'Domain-Admin',
        'username' => 'administrator', 'password' => 'LOGIN-GEHEIM',
    ]);

    return $customer;
}

test('the PDF leaves out the kinds of objects the orderer may not see', function () {
    $owner = userWithPermissions(['create_pdf', 'server_viewAny']);

    $html = pdfHtml(pdfCustomer(), $owner);

    expect($html)->toContain('BMC-GEHEIM')
        ->not->toContain('LOGIN-GEHEIM')
        ->not->toContain('Domain-Admin');
});

test('with the right to see logins they are in the PDF', function () {
    $owner = userWithPermissions(['create_pdf', 'server_viewAny', 'logingeneral_viewAny']);

    expect(pdfHtml(pdfCustomer(), $owner))->toContain('LOGIN-GEHEIM');
});

test('without an orderer the PDF shows no section content', function () {
    $html = pdfHtml(pdfCustomer(), null);

    expect($html)->not->toContain('BMC-GEHEIM')->not->toContain('LOGIN-GEHEIM');
});

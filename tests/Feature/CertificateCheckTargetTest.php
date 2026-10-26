<?php

use App\Livewire\ObjektListe;
use App\Models\Certificate;
use App\Models\Customer;
use App\Support\CertificateCheck;
use Livewire\Livewire;

// Security audit finding: "check now" on a certificate let a customer user
// make the server connect to any internal host:port and read whether it
// answers and what certificate it serves.

/** Records whether a connection would have been opened - never opens one. */
function recordingCertificateCheck(): CertificateCheck
{
    $check = new class extends CertificateCheck
    {
        public array $connections = [];

        protected function peerCertificate(string $host, int $port): array
        {
            $this->connections[] = "$host:$port";

            return ['subject' => ['CN' => $host], 'issuer' => ['O' => 'Test'], 'validTo_time_t' => now()->addYear()->getTimestamp()];
        }
    };
    app()->instance(CertificateCheck::class, $check);

    return $check;
}

dataset('internal targets', [
    'loopback' => ['127.0.0.1'],
    'private' => ['10.10.250.91'],
    'cloud metadata' => ['169.254.169.254'],
    'carrier-grade NAT' => ['100.64.1.1'],
    'IPv6 loopback' => ['::1'],
    'IPv4-mapped loopback' => ['::ffff:127.0.0.1'],
    'localhost' => ['localhost'],
]);

test('public-only mode refuses internal targets before any connection', function (string $host) {
    $check = recordingCertificateCheck();

    expect(fn () => $check->check($host, 443, publicOnly: true))->toThrow(RuntimeException::class);
    expect($check->connections)->toBe([]);
})->with('internal targets');

test('a customer user\'s "check now" does not reach an internal host', function () {
    $check = recordingCertificateCheck();
    $customer = Customer::factory()->create();
    $user = userWithPermissions(['certificate_viewAny', 'certificate_update']);
    $user->update(['customer_id' => $customer->id]);
    $this->actingAs($user);
    $certificate = Certificate::factory()->create(['customer_id' => $customer->id, 'check_host' => '10.0.0.5', 'check_port' => 8443]);

    Livewire::test(ObjektListe::class, ['typ' => 'certificate', 'customer' => $customer])
        ->call('check', $certificate->id);

    expect($check->connections)->toBe([])
        ->and($certificate->fresh()->check_error)->toContain('interne Adresse');
});

test('staff still check internal certificates', function () {
    $check = recordingCertificateCheck();
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['certificate_viewAny', 'certificate_update']));
    $certificate = Certificate::factory()->create(['customer_id' => $customer->id, 'check_host' => '10.0.0.5', 'check_port' => 8443]);

    Livewire::test(ObjektListe::class, ['typ' => 'certificate', 'customer' => $customer])
        ->call('check', $certificate->id);

    expect($check->connections)->toBe(['10.0.0.5:8443']);
});

test('a customer user cannot save an internal check target', function () {
    $customer = Customer::factory()->create();
    $user = userWithPermissions(['certificate_viewAny', 'certificate_create']);
    $user->update(['customer_id' => $customer->id]);
    $this->actingAs($user);

    imModal('certificate', $customer, ['name' => 'Intern', 'check_host' => '192.168.1.10'])
        ->assertHasErrors('form.check_host');

    imModal('certificate', $customer, ['name' => 'Intern', 'common_name' => '127.0.0.1'])
        ->assertHasErrors('form.common_name');

    expect(Certificate::where('customer_id', $customer->id)->count())->toBe(0);
});

test('public names and IPs stay allowed', function () {
    expect(CertificateCheck::isPublicTarget('8.8.8.8'))->toBeTrue()
        ->and(CertificateCheck::isPublicTarget('2001:4860:4860::8888'))->toBeTrue()
        // A name that does not resolve has nothing internal behind it.
        ->and(CertificateCheck::isPublicTarget('gibt-es-nicht.invalid'))->toBeTrue()
        ->and(CertificateCheck::isPublicTarget('10.1.2.3'))->toBeFalse();
});

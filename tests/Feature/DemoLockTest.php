<?php

use App\Livewire\AdminBackupSettings;
use App\Livewire\AdminGeneral;
use App\Livewire\AdminSecurity;
use App\Models\Setting;
use App\Providers\AppServiceProvider;
use App\Support\CertificateCheck;
use Livewire\Livewire;

/*
 * The public demo: everyone signs in with the same admin account. Settings
 * that send mail, reach the network the demo runs in, hand out its .env or
 * spoil it for every other visitor are shown, but cannot be changed.
 */

beforeEach(function () {
    config(['app.demo' => true]);
    $this->actingAs(adminUser());
});

test('mail, directory, remote pattern and backup download are refused in the demo', function () {
    $this->patch(route('admin.mail.update'), ['mail_host' => 'smtp.fremd.example'])->assertForbidden();
    $this->post(route('admin.mail.test'), ['test_an' => 'x@example.com'])->assertForbidden();
    $this->patch(route('admin.directory.update'), ['encryption' => 'none', 'host' => '10.0.0.1'])->assertForbidden();
    $this->post(route('admin.directory.test'))->assertForbidden();
    $this->patch(route('admin.setting.update'), ['remote_tool' => 'custom', 'remote_pattern' => 'x://{id}'])->assertForbidden();
    $this->get(route('admin.backup.download', ['path' => 'x.zip']))->assertForbidden();

    expect(Setting::wert(Setting::MAIL_HOST))->toBeNull()
        ->and(Setting::wert(Setting::REMOTE_PATTERN))->toBeNull();
});

test('the pages stay visible, with the fields disabled and a note', function () {
    foreach (['admin.mail.index', 'admin.directory.index', 'admin.setting.index'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('<fieldset disabled', false);
    }

    Livewire::test(AdminGeneral::class)->assertSee('<fieldset disabled', false);
});

test('the general, security and backup settings refuse every change in the demo', function () {
    Livewire::test(AdminGeneral::class)->set('name', 'Gehackt')->assertForbidden();
    Livewire::test(AdminSecurity::class)->set('versuche', 1)->assertForbidden();
    Livewire::test(AdminBackupSettings::class)->call('jetztSichern')->assertForbidden();

    expect(Setting::wert(Setting::APP_NAME))->not->toBe('Gehackt');
});

test('the certificate check reaches no internal address in the demo, staff or not', function () {
    // Records whether a connection would have been opened - never opens one.
    $check = new class extends CertificateCheck
    {
        public array $connections = [];

        protected function peerCertificate(string $host, int $port): array
        {
            $this->connections[] = "$host:$port";

            return [];
        }
    };

    expect(fn () => $check->check('10.0.0.5', 443))->toThrow(RuntimeException::class);
    expect($check->connections)->toBe([]);
});

test('the demo sends no mail, whatever server is stored', function () {
    Setting::setzen(Setting::MAIL_HOST, 'smtp.fremd.example');

    (new ReflectionMethod(AppServiceProvider::class, 'applyMailSettings'))->invoke(app()->getProvider(AppServiceProvider::class));

    expect(config('mail.default'))->toBe('log');
});

test('outside the demo nothing is locked', function () {
    config(['app.demo' => false]);

    $this->get(route('admin.mail.index'))->assertOk()->assertDontSee('<fieldset disabled', false);
    Livewire::test(AdminGeneral::class)->set('name', 'Firma')->assertOk();
});

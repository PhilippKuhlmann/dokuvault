<?php

use App\Livewire\AdminSicherheit;
use App\Models\Customer;
use App\Models\NAS;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

/** Die Regel so anwenden, wie es alle vier Stellen im Code tun. */
function kennwortPasst(string $kennwort): bool
{
    return Validator::make(['p' => $kennwort], ['p' => ['required', Password::defaults()]])->passes();
}

// --- Die Einstellung selbst -------------------------------------------------

test('ohne Einstellung gilt, was bisher stillschweigend galt', function () {
    // Acht Zeichen, sonst nichts - Laravels Vorgabe. Sie stand nirgends.
    expect(Setting::kennwortMindestlaenge())->toBe(8)
        ->and(kennwortPasst('abcdefgh'))->toBeTrue()
        ->and(kennwortPasst('abcdefg'))->toBeFalse();
});

test('die Mindestlänge lässt sich setzen und wirkt sofort', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminSicherheit::class)->set('pwMin', 12)->assertHasNoErrors();

    expect(kennwortPasst('elfzeichen1'))->toBeFalse()
        ->and(kennwortPasst('zwoelfzeiche'))->toBeTrue();
});

test('unter acht Zeichen geht nicht', function () {
    // Weniger wäre schlechter als der Standard, den es zu ersetzen gilt.
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminSicherheit::class)->set('pwMin', 4)->assertHasErrors('pwMin');
});

test('über 64 Zeichen auch nicht', function () {
    // bcrypt schneidet bei 72 Byte ab - eine Mindestlänge darüber wäre eine
    // Zahl ohne Wirkung.
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminSicherheit::class)->set('pwMin', 100)->assertHasErrors('pwMin');
});

test('jede Komplexitätsregel wirkt einzeln', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));
    $seite = Livewire::test(AdminSicherheit::class);

    $seite->set('pwMixed', true);
    expect(kennwortPasst('nurkleinbuchstaben'))->toBeFalse()
        ->and(kennwortPasst('MitGrossUndKlein'))->toBeTrue();

    $seite->set('pwNumbers', true);
    expect(kennwortPasst('MitGrossUndKlein'))->toBeFalse()
        ->and(kennwortPasst('MitGrossUndKlein1'))->toBeTrue();

    $seite->set('pwSymbols', true);
    expect(kennwortPasst('MitGrossUndKlein1'))->toBeFalse()
        ->and(kennwortPasst('MitGrossUndKlein1!'))->toBeTrue();
});

test('ein Häkchen lässt sich auch wieder abwählen', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminSicherheit::class)
        ->set('pwSymbols', true)
        ->set('pwSymbols', false);

    expect(kennwortPasst('ohnesonderzeichen'))->toBeTrue();
});

test('ohne das Recht admin_setting kommt niemand auf die Seite', function () {
    $this->actingAs(userWithPermissions([]));

    $this->get(route('admin.security.index'))->assertForbidden();
});

// --- Der Satz, den die Benutzer lesen ---------------------------------------

test('der Hinweis nennt, was verlangt wird', function () {
    Setting::setzen(Setting::PW_MIN, 12);
    Setting::setzen(Setting::PW_MIXED, 1);
    Setting::setzen(Setting::PW_SYMBOLS, 1);

    expect(Setting::kennwortHinweis())
        ->toContain('12')
        ->toContain('Groß- und Kleinbuchstaben')
        ->toContain('ein Sonderzeichen');
});

test('der Hinweis steht unter jedem Kennwortfeld', function () {
    // Wer ein Sonderzeichen verlangt, ohne es hinzuschreiben, lässt raten.
    Setting::setzen(Setting::PW_MIN, 14);

    $nutzer = userWithPermissions(['admin_user']);
    $this->actingAs($nutzer);

    // Eigenes Profil und Anlegen durch einen Administrator
    $this->get(route('profile.edit'))->assertSee('Mindestens 14 Zeichen');
    $this->get(route('admin.user.create'))->assertSee('Mindestens 14 Zeichen');

    // Einladung einlösen und Kennwort zurücksetzen
    nutzerWechseln(userWithPermissions([]));
    $this->post('/logout');

    $this->get('/einladung/probe-token')->assertSee('Mindestens 14 Zeichen');
    $this->get('/reset-password/probe-token')->assertSee('Mindestens 14 Zeichen');
});

// --- Die Wirkung an allen vier Stellen --------------------------------------

test('die Regel gilt beim Kennwortwechsel im eigenen Profil', function () {
    Setting::setzen(Setting::PW_MIN, 16);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make('Altes-Kennwort-2026')])->save();

    $this->actingAs($nutzer->fresh())
        ->withSession(['password_hash_web' => $nutzer->fresh()->password])
        ->put('/password', [
            'current_password' => 'Altes-Kennwort-2026',
            'password' => 'ZuKurz-2026',
            'password_confirmation' => 'ZuKurz-2026',
        ])
        ->assertSessionHasErrors('password', null, 'updatePassword');

    expect(Hash::check('ZuKurz-2026', $nutzer->fresh()->password))->toBeFalse();
});

test('die Regel gilt, wenn ein Administrator einen Benutzer anlegt', function () {
    Setting::setzen(Setting::PW_MIN, 16);

    $this->actingAs(userWithPermissions(['admin_user']));

    $this->post(route('admin.user.store'), [
        'name' => 'Neue Kollegin',
        'username' => 'neue.kollegin',
        'password' => 'ZuKurz-2026',
        'email' => null,
        'role_id' => Role::first()->id,
        'customer_id' => null,
    ])->assertSessionHasErrors('password');

    expect(User::where('username', 'neue.kollegin')->exists())->toBeFalse();
});

test('die Regel gilt beim Einlösen einer Einladung', function () {
    Setting::setzen(Setting::PW_MIN, 16);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => 'eingeladen@example.test'])->save();

    $token = PasswordBroker::broker('einladung')->createToken($nutzer->fresh());

    $this->post(route('einladung.speichern'), [
        'token' => $token,
        'username' => $nutzer->username,
        'password' => 'ZuKurz-2026',
        'password_confirmation' => 'ZuKurz-2026',
    ])->assertSessionHasErrors('password');
});

test('die Regel gilt beim Zurücksetzen eines vergessenen Kennworts', function () {
    Setting::setzen(Setting::PW_MIN, 16);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => 'vergessen@example.test'])->save();

    $token = PasswordBroker::broker()->createToken($nutzer->fresh());

    $this->post('/reset-password', [
        'token' => $token,
        'username' => $nutzer->username,
        'password' => 'ZuKurz-2026',
        'password_confirmation' => 'ZuKurz-2026',
    ])->assertSessionHasErrors('password');
});

// --- Was die Regel ausdrücklich nicht betrifft ------------------------------

test('dokumentierte Kundenkennwörter bleiben unberührt', function () {
    // Dort wird festgehalten, was ist, nicht was sein soll. Ein Kunde mit
    // einem schwachen Kennwort muss dokumentierbar bleiben.
    Setting::setzen(Setting::PW_MIN, 20);
    Setting::setzen(Setting::PW_SYMBOLS, 1);

    $this->actingAs(userWithPermissions(['nas_create']));

    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);

    imModal('nas', $kunde, [
        'site_id' => $standort->id,
        'name' => 'NAS1',
        'ip1' => '10.0.0.1',
        'username' => 'admin',
        'password' => 'admin',
    ])->assertHasNoErrors();

    expect(NAS::where('name', 'NAS1')->first()->password)->toBe('admin');
});

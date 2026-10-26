<?php

use App\Models\ADUser;
use App\Models\Customer;
use App\Models\Domain;
use App\Support\ChecklistAdScript;
use App\Support\ChecklistBuilder;

// Security audit finding: a name like "Max ##>> ... <#" ended the comment
// block at the top of the elevated AD script, and the rest of the name ran
// as PowerShell. The checks look at the generated text the way PowerShell
// reads it: block comments end at the first "#>", and the JSON data sits in
// a here-string.

/** What PowerShell would parse as code: the script without comments and data. */
function adScriptCode(string $script): string
{
    $script = preg_replace("/@'\\r?\\n.*?\\r?\\n'@/s", '', $script);

    return preg_replace('/<#.*?#>/s', '', $script);
}

function injectionCustomer(): Customer
{
    $customer = Customer::factory()->create();
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'kunde.de']);

    return $customer;
}

function onboardingScript(string $firstName, string $lastName = 'Muster'): string
{
    $checklist = app(ChecklistBuilder::class)->startOnboarding(injectionCustomer(), [
        'first_name' => $firstName, 'last_name' => $lastName,
        'reference_date' => '2026-11-16', 'drives' => [], 'options' => [],
    ], null);

    return ChecklistAdScript::render($checklist, 'TOKEN');
}

function offboardingScript(string $firstName, string $lastName = 'Muster'): string
{
    $customer = injectionCustomer();
    // Offboarding names come from the AD import - an agent token or AD-user
    // edit rights are enough to set them.
    $adUser = ADUser::factory()->create([
        'customer_id' => $customer->id, 'firstName' => $firstName, 'lastName' => $lastName,
        'username' => 'max.muster',
    ]);

    $checklist = app(ChecklistBuilder::class)->startOffboarding($customer, $adUser, '2026-10-31', null);

    return ChecklistAdScript::render($checklist, 'TOKEN');
}

dataset('comment breakouts', [
    'doubled delimiter' => ['Max ##>> AUDITMARKER <#'],
    'line feed inside' => ["Max #\n> AUDITMARKER <#"],
    'carriage return inside' => ["Max #\r> AUDITMARKER <#"],
    'repeated' => ['Max #>#> AUDITMARKER'],
    'close and reopen' => ['#><# AUDITMARKER #>'],
    'nested' => ['Max ###>>> AUDITMARKER <<##'],
]);

test('no name escapes the comment of the onboarding script', function (string $name) {
    expect(adScriptCode(onboardingScript($name)))->not->toContain('AUDITMARKER')
        ->and(adScriptCode(onboardingScript('Max', $name)))->not->toContain('AUDITMARKER');
})->with('comment breakouts');

test('no name escapes the comment of the offboarding script', function (string $name) {
    expect(adScriptCode(offboardingScript($name)))->not->toContain('AUDITMARKER')
        ->and(adScriptCode(offboardingScript('Max', $name)))->not->toContain('AUDITMARKER');
})->with('comment breakouts');

test('real names stay readable in the script head', function () {
    $script = onboardingScript('José', "Müller-Lüdenscheid O'Brien");

    expect($script)->toContain("DokuVault - Onboarding José Müller-Lüdenscheid O'Brien");
});

test('the name still reaches the script unchanged as data', function () {
    // In the JSON nothing is cut off: there it is a value, not code.
    $script = onboardingScript('Max ##>> AUDITMARKER <#');

    expect($script)->toContain('"firstName": "Max ##>> AUDITMARKER <#"');
});

test('a value that changes the comment structure stops the download', function () {
    $check = new ReflectionMethod(ChecklistAdScript::class, 'assertStructure');

    expect(fn () => $check->invoke(null, "<#\n  __TITLE__\n#>", "<#\n  x #> code <# \n#>"))
        ->toThrow(RuntimeException::class);
});

test('only known-safe placeholders stand in a comment block of the templates', function () {
    // __TITLE__ is cleaned by commentText(), __EXPIRES__ is a formatted date.
    // A new placeholder in a comment needs the same treatment - this fails
    // until it gets it.
    $safe = ['__TITLE__', '__EXPIRES__'];

    foreach (glob(resource_path('agents/checklist-*.ps1')) as $template) {
        preg_match_all('/<#.*?#>/s', file_get_contents($template), $comments);
        preg_match_all('/__[A-Z_]+__/', implode("\n", $comments[0]), $placeholders);

        expect(array_values(array_diff(array_unique($placeholders[0]), $safe)))
            ->toBe([], basename($template).' has an unchecked placeholder in a comment');
    }
});

<?php

use App\Models\ADUser;
use App\Models\Customer;
use App\Models\Domain;
use App\Support\ChecklistBuilder;

// Security audit finding: the script key of one checklist could overwrite
// the documented password, email and groups of any AD user of the customer,
// by naming them (username) or their objectGUID (identifier) in the report.

function reportSetup(): array
{
    $customer = Customer::factory()->create();
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'kunde.de']);

    $checklist = app(ChecklistBuilder::class)->startOnboarding($customer, [
        'first_name' => 'Sophie', 'last_name' => 'Krämer',
        'reference_date' => '2026-11-16', 'drives' => [], 'options' => [],
    ], null);

    $boss = ADUser::factory()->create([
        'customer_id' => $customer->id, 'username' => 'chef.admin',
        'email' => 'chef@kunde.de', 'agent_identifier' => 'GUID-CHEF', 'password' => 'Chef-Kennwort',
    ]);

    return [$checklist, $boss, $checklist->issueScriptToken(userWithPermissions(['checklist_manage']))];
}

test('a report for another user of the customer is refused', function () {
    [$checklist, $boss, $token] = reportSetup();

    $this->withToken($token)->postJson('/api/checklist-report', [
        'username' => 'chef.admin', 'email' => 'angreifer@example.test', 'password' => 'Uebernommen',
    ])->assertStatus(422);

    $boss->refresh();
    expect($boss->email)->toBe('chef@kunde.de')
        ->and($boss->password)->toBe('Chef-Kennwort')
        ->and($checklist->fresh()->ad_user_id)->toBeNull();
});

test('an identifier of another user does not redirect the write', function () {
    [$checklist, $boss, $token] = reportSetup();

    $this->withToken($token)->postJson('/api/checklist-report', [
        'username' => $checklist->username, 'identifier' => 'GUID-CHEF', 'password' => 'Start-123',
    ])->assertOk();

    expect($boss->fresh()->password)->toBe('Chef-Kennwort');

    $created = ADUser::find($checklist->fresh()->ad_user_id);
    expect($created->username)->toBe($checklist->username)
        ->and($created->id)->not->toBe($boss->id);
});

test('the own user is documented, whatever spelling AD sends back', function () {
    [$checklist, , $token] = reportSetup();

    $this->withToken($token)->postJson('/api/checklist-report', [
        'username' => strtoupper($checklist->username), 'password' => 'Start-123',
    ])->assertOk();

    expect(ADUser::find($checklist->fresh()->ad_user_id)->password)->toBe('Start-123');
});

test('a script key reports once - a second report is refused', function () {
    [$checklist, , $token] = reportSetup();

    $this->withToken($token)->postJson('/api/checklist-report', [
        'username' => $checklist->username, 'password' => 'Start-123',
    ])->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/checklist-report', [
        'username' => $checklist->username, 'password' => 'Ueberschrieben', 'email' => 'angreifer@example.test',
    ])->assertUnauthorized();

    $adUser = ADUser::find($checklist->fresh()->ad_user_id);
    expect($adUser->password)->toBe('Start-123')
        ->and($adUser->email)->not->toBe('angreifer@example.test')
        ->and($checklist->fresh()->script_token_hash)->toBeNull();
});

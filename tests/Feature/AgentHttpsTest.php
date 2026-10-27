<?php

use App\Models\Customer;

// Security audit candidate: agents run as SYSTEM/root and replace their exe
// and scripts with what the server sends - over plain http anyone on the
// network path could send their own. Agents now refuse http (except the
// machine itself); the agent page says so before anyone installs one.

test('the agent page warns when DokuVault has no https address', function () {
    config(['app.url' => 'http://doku.lan']);
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->get(route('agent.index', Customer::factory()->create()))
        ->assertOk()
        ->assertSee('ohne https erreichbar');
});

test('no warning with an https address', function () {
    config(['app.url' => 'https://doku.example.test']);
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->get(route('agent.index', Customer::factory()->create()))
        ->assertOk()
        ->assertDontSee('ohne https erreichbar');
});

test('the Linux installer refuses a plain-http address before installing anything', function () {
    $lines = file(resource_path('agents/install/linux-agent.sh'), FILE_IGNORE_NEW_LINES);
    $firstLine = function (string $needle) use ($lines): int {
        foreach ($lines as $number => $line) {
            if (! str_starts_with(ltrim($line), '#') && str_contains($line, $needle)) {
                return $number;
            }
        }

        return PHP_INT_MAX;
    };

    // The check sits before the first command that changes the system.
    $check = $firstLine('Unsichere Adresse');
    expect($check)->toBeLessThan(PHP_INT_MAX)
        ->and($check)->toBeLessThan($firstLine('systemctl'))
        ->and($check)->toBeLessThan($firstLine('install -d'));
});

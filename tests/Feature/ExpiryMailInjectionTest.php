<?php

use App\Notifications\ExpiryNotice;
use App\Support\ExpiringItems;

// Security audit finding: a blank line in a stored name or an agent hostname
// ended the HTML block of the expiry mail, and the rest was parsed as
// markdown - a phishing link or a tracking pixel in a genuine mail.

function injectedItem(array $overrides = []): array
{
    return array_merge([
        'kind' => 'agent',
        'label' => 'Agent',
        'name' => "SRV-01\n\n[Jetzt anmelden](https://phish.example)\n\n![](https://track.example/p.gif)",
        'customer' => "Muster GmbH\n\n**Wichtig**",
        'date' => now()->startOfDay(),
        'seen' => now()->subDays(3),
        'days' => -3,
        'stage' => ExpiringItems::SILENT,
        'url' => 'https://doku.test/kunde/agent',
    ], $overrides);
}

test('names with blank lines stay text in the expiry mail', function () {
    $html = (string) (new ExpiryNotice(collect([injectedItem()])))
        ->toMail(adminUser())
        ->render();

    expect($html)->not->toContain('href="https://phish.example"')
        ->not->toContain('src="https://track.example/p.gif"')
        ->not->toContain('<strong>Wichtig</strong>')
        // Still readable, on one line.
        ->toContain('SRV-01 [Jetzt anmelden](https://phish.example)');
});

test('ordinary names come through unchanged', function () {
    $html = (string) (new ExpiryNotice(collect([injectedItem(['name' => 'SRV-01', 'customer' => 'Muster GmbH'])])))
        ->toMail(adminUser())
        ->render();

    expect($html)->toContain('SRV-01')->toContain('Muster GmbH');
});

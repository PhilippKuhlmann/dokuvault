<?php

/**
 * A sidebar button needs a sidebar.
 *
 * layouts/empty (customer search, global search, profile, changelog) has no
 * sidebar, but its navigation carried the button that opens one. Flowbite
 * looked for the element on every page load and logged "Drawer with id
 * logo-sidebar not found" - and on a phone the button did nothing.
 */
test('pages without a sidebar carry no sidebar button', function (string $route) {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $content = $this->get(route($route))->assertOk()->getContent();

    expect($content)
        ->not->toContain('id="logo-sidebar"')
        ->not->toContain('data-drawer-target');
})->with(['customer.search', 'search.global', 'profile.edit', 'changelog']);

test('every sidebar button in the layouts points at a sidebar of the same layout', function () {
    $pairs = [
        'layouts/navigation.blade.php' => 'layouts/aside.blade.php',
        'layouts/admin/navigation.blade.php' => 'layouts/admin/aside.blade.php',
    ];

    foreach ($pairs as $navigation => $aside) {
        preg_match('/data-drawer-target="([^"]+)"/', file_get_contents(resource_path('views/'.$navigation)), $match);

        expect($match[1] ?? null)->not->toBeNull()
            ->and(file_get_contents(resource_path('views/'.$aside)))->toContain('id="'.$match[1].'"');
    }

    expect(file_get_contents(resource_path('views/layouts/navigation-simple.blade.php')))
        ->not->toContain('data-drawer-target=');
});

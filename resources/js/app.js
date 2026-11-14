import './bootstrap';

import 'flowbite';

// Copy text to the clipboard - with a fallback for insecure contexts
// (z. B. lokale .test-Domain über http, wo navigator.clipboard fehlt).
window.copyText = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    const area = document.createElement('textarea');
    area.value = text;
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.focus();
    area.select();
    try { document.execCommand('copy'); } catch (e) { /* ignore */ }
    document.body.removeChild(area);
    return Promise.resolve();
};

// Menus built from <details data-menu> (the "⋯" of an agent, say): a
// <details> stays open until its own summary is clicked again. A click
// anywhere else or Escape closes it, as one expects of a menu.
document.addEventListener('click', (event) => {
    document.querySelectorAll('details[data-menu][open]').forEach((menu) => {
        if (!menu.contains(event.target)) {
            menu.removeAttribute('open');
        }
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('details[data-menu][open]').forEach((menu) => menu.removeAttribute('open'));
    }
});

// Livewire 3 brings its own Alpine instance and starts it itself as part of
// Livewire.start(). An additional `import Alpine from 'alpinejs'; Alpine.start()`
// in front of that created two competing Alpine/Livewire instances on every page
// (console warnings "Detected multiple instances of ..." and a crash on the second
// registration of Alpine magics such as $persist), so wire:click/wire:model partly stopped firing.
//
// The CSP build: Alpine evaluates its expressions with its own parser instead
// of new Function, so the Content-Security-Policy needs no 'unsafe-eval'. An
// expression in the HTML is then one JavaScript expression - whatever needs
// more is registered below (resources/js/alpine/) and only called there.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.csp.esm';
import { hilfenRegistrieren } from './alpine/hilfen';
import { komponentenRegistrieren, rackRegistrieren, auswahlRegistrieren } from './alpine/komponenten';

window.Alpine = Alpine;

hilfenRegistrieren(Alpine);
komponentenRegistrieren(Alpine);
rackRegistrieren(Alpine);
auswahlRegistrieren(Alpine);

// Catch an expired page (HTTP 419). Livewire otherwise shows a blocking
// confirm() dialog "This page has expired." - a dead end that came up
// especially in the wizard, when a page left open for a while sends its first
// Livewire call (a wire:model.live while typing, say) with a stale CSRF token.
//
// Instead of the dialog we reload the page once and get a fresh token and a
// valid session that way. The wizard's progress lies on the server in the
// DocumentationRun, so at most a half-typed field is lost.
//
// Guard against an endless loop: if the session cannot exist at all (blocked
// cookies, say), a reload would return 419 again at once. If that happens twice in
// quick succession we leave Livewire its dialog again rather than trapping the user in reloads.
Livewire.hook('request', ({ fail }) => {
    fail(({ status, preventDefault }) => {
        if (status !== 419) {
            return;
        }

        const jetzt = Date.now();
        let letzter = 0;
        try { letzter = parseInt(sessionStorage.getItem('seiteAbgelaufenReload') || '0', 10); } catch (e) { /* ignore */ }

        if (jetzt - letzter < 10000) {
            return; // gerade erst neu geladen und schon wieder 419 - Livewire uebernimmt
        }

        preventDefault();
        try { sessionStorage.setItem('seiteAbgelaufenReload', String(jetzt)); } catch (e) { /* ignore */ }
        window.location.reload();
    });
});

// The semicolon is mandatory, not taste: without it a following line starting
// with ( or [ would be read as a continuation of this expression - from
// Livewire.start() followed by (function(){...})() comes
// Livewire.start()(function(){...})().
Livewire.start();



// The switch sits in the navigation bars only. On pages without navigation -
// the sign-in page, for one - button and icons are missing. Accessed unchecked,
// that broke the execution of this file at this point, and everything after it
// stopped running there. Hence wrapped with an early exit.
(function () {

const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
const themeToggleBtn = document.getElementById('theme-toggle');

if (! themeToggleBtn || ! themeToggleDarkIcon || ! themeToggleLightIcon) {
    return;
}

// Change the icons inside the button based on previous settings
if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    themeToggleLightIcon.classList.remove('hidden');
} else {
    themeToggleDarkIcon.classList.remove('hidden');
}

themeToggleBtn.addEventListener('click', function() {

    // toggle icons inside button
    themeToggleDarkIcon.classList.toggle('hidden');
    themeToggleLightIcon.classList.toggle('hidden');

    // if set via local storage previously
    if (localStorage.getItem('color-theme')) {
        if (localStorage.getItem('color-theme') === 'light') {
            document.documentElement.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
        }

    // if NOT set via local storage previously
    } else {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
        }
    }

});

})();

import './bootstrap';

import 'flowbite';

// Text in die Zwischenablage kopieren – mit Fallback für unsichere Kontexte
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

// Livewire 3 bringt seine eigene Alpine-Instanz mit und startet sie selbst als Teil von
// Livewire.start(). Ein zusätzlicher eigener `import Alpine from 'alpinejs'; Alpine.start()`
// davor erzeugte zwei konkurrierende Alpine/Livewire-Instanzen auf jeder Seite (Konsolen-
// warnungen "Detected multiple instances of ..." und ein Crash bei der zweiten Registrierung
// von Alpine-Magics wie $persist), wodurch wire:click/wire:model teils gar nicht mehr feuerten.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;

// Das Semikolon ist Pflicht, nicht Geschmack: Ohne es wuerde eine folgende
// Zeile, die mit ( oder [ beginnt, als Fortsetzung dieses Ausdrucks gelesen -
// aus Livewire.start() gefolgt von (function(){...})() wird dann
// Livewire.start()(function(){...})().
Livewire.start();



// Der Umschalter steht nur in den Navigationsleisten. Auf Seiten ohne Navigation –
// der Anmeldeseite etwa – fehlen Knopf und Icons. Ungeprüft zugegriffen brach das
// die Ausführung dieser Datei an dieser Stelle ab, und alles Nachfolgende lief dort
// nicht mehr. Deshalb gekapselt mit frühem Ausstieg.
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

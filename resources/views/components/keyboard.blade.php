{{--
    "/" springt in die Suche der aktuellen Seite (fehlt eine, öffnet ersatzweise
    die Befehlspalette). "?" öffnet die Übersicht aller Tastenkürzel. Beides
    greift nur, wenn man nicht gerade in einem Eingabefeld tippt - sonst landete
    das Zeichen im Feld.

    Rein clientseitig (Alpine), kein Livewire. Neuer Code englisch, sichtbare
    Texte deutsch mit en.json-Übersetzung.
--}}
<div x-data="{
        helpOpen: false,
        typing(e) {
            const t = e.target;
            return t instanceof Element && (t.closest('input, textarea, select') !== null || t.isContentEditable);
        },
        onKey(e) {
            if (e.metaKey || e.ctrlKey || e.altKey || this.typing(e)) return;
            if (e.key === '/') {
                // In die Suche der aktuellen Seite springen; hat die Seite keine
                // (z. B. Dashboard), ersatzweise die Befehlspalette öffnen.
                e.preventDefault();
                const feld = document.querySelector('input[type=search]');
                if (feld) { feld.focus(); }
                else { window.dispatchEvent(new CustomEvent('palette-open')); }
            } else if (e.key === '?') {
                e.preventDefault();
                this.helpOpen = true;
            }
        },
    }"
    x-on:keydown.window="onKey($event)">

    @php
        // Symbol(e) links, Bedeutung rechts. Die Zeichen selbst bleiben, wie sie
        // sind; nur die Beschreibung wird übersetzt.
        $kuerzel = [
            ['Cmd / Strg + K', __('Befehlspalette öffnen')],
            ['/', __('In die Suche springen')],
            ['↑ ↓', __('Auswählen')],
            ['↵', __('Öffnen / Hinzufügen')],
            ['Cmd / Strg + ↵', __('Speichern')],
            ['Esc', __('Schließen')],
            ['?', __('Diese Übersicht')],
        ];
    @endphp

    <template x-if="helpOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="helpOpen = false"
            x-on:click.self="helpOpen = false">

            <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white px-5 py-5 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                    {{ __('Tastaturkürzel') }}
                </div>

                <dl class="space-y-2">
                    @foreach ($kuerzel as [$taste, $bedeutung])
                        <div class="flex items-center justify-between gap-4">
                            <dt class="shrink-0">
                                <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ $taste }}</kbd>
                            </dt>
                            <dd class="min-w-0 text-right text-sm text-gray-600 dark:text-gray-300">{{ $bedeutung }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-5 text-right">
                    <button type="button" x-on:click="helpOpen = false"
                        class="rounded-lg bg-cerulean-600 px-4 py-2 text-sm font-DINPro-bold text-white transition-colors hover:bg-cerulean-700 focus:outline-hidden focus:ring-2 focus:ring-cerulean-500">
                        {{ __('Schließen') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

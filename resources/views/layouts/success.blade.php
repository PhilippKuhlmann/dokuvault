{{--
    Erfolgsmeldung unten rechts.

    Zwei Quellen: session('success') nach einem gewoehnlichen Request, und das
    Browser-Ereignis "hinweis" aus Livewire-Komponenten. Letzteres brauchte es,
    seit Aktionen ohne Seitenwechsel laufen (VLAN anlegen, bearbeiten, loeschen
    im Modal) - eine Session-Meldung kaeme dort erst beim naechsten Laden an.

    Deshalb steht das Element immer im DOM und wartet auf das Ereignis, statt
    nur bei vorhandener Session-Meldung gerendert zu werden.
--}}
<div x-data="{
        show: @js((bool) session('success')),
        text: @js((string) session('success')),
        timer: null,
        zeigen(nachricht) {
            this.text = nachricht;
            this.show = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.show = false, 4000);
        },
    }"
    x-init="if (show) timer = setTimeout(() => show = false, 4000)"
    x-on:hinweis.window="zeigen($event.detail.text)"
    x-show="show" x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-end="opacity-0 translate-y-2"
    class="z-50 fixed bottom-4 right-4 flex items-center p-4 w-full max-w-xs text-gray-700 bg-white border border-gray-100 rounded-xl shadow-lg dark:text-gray-200 dark:bg-gray-800 dark:border-gray-700"
    role="status" aria-live="polite">

    <div class="inline-flex flex-shrink-0 justify-center items-center w-8 h-8 text-green-500 bg-green-100 rounded-lg dark:bg-green-800 dark:text-green-200">
        <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clip-rule="evenodd"></path>
        </svg>
    </div>

    <div class="ml-3 text-sm font-normal" x-text="text"></div>

    <button type="button" x-on:click="show = false"
        class="ml-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700">
        <span class="sr-only">{{ __('Close') }}</span>
        <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"></path>
        </svg>
    </button>
</div>

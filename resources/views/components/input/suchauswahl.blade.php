{{--
    Ein Auswahlfeld mit vorgeschaltetem Suchfeld - fuer Listen, die zu lang
    geworden sind, um sie zu ueberfliegen (der Betriebssystem-Katalog fuehrt
    ueber fuenfzig Eintraege).

    Das native <select> bleibt bestehen und behaelt wire:model, die vollstaendige
    Optionsliste und die Fehlerdarstellung; es wird nur versteckt. Darueber liegt
    die Bedienung. Damit bleibt die Livewire-Bindung unveraendert, und ohne
    JavaScript bleibt ein benutzbares Select uebrig.

    Warum nicht das Type-Ahead des Browsers: Das matcht nur vom Wortanfang.
    "Windows Server 2022 Standard" findet man damit nur ueber "Windows", nicht
    ueber "2022" - und genau danach sucht man.

    Die Liste haengt am Viewport, nicht am Feld: Das Modal hat einen eigenen
    Scrollrahmen (overflow-y-auto), der ein absolut positioniertes Fenster an
    seiner Unterkante abschneidet. position: fixed entkommt dem - dieselbe
    Loesung wie beim Hover-Fenster in x-hovertext.
--}}
@props(['name', 'feld' => null])

@php
    $fehler = ($feld ?? $name) && $errors->has($feld ?? $name);

    // Der gebundene Eigenschaftsname, etwa 'form.operating_system_id'. Die
    // Anzeige haengt daran und nicht an einem beim Init gemerkten Wert: Das
    // Modal wird fuer "Neu" und "Bearbeiten" wiederverwendet, Livewire tauscht
    // dabei nur den Inhalt aus. Alpines init() liefe kein zweites Mal - der
    // Auslöser zeigte dann "bitte wählen", waehrend laengst ein Wert geladen
    // war.
    $modell = $attributes->wire('model')->value();
@endphp

<div class="relative mt-1"
    x-data="{
        offen: false,
        suche: '',
        markiert: -1,
        optionen: [],
        x: 0,
        y: 0,
        breite: 0,
        nachOben: false,
        init() {
            this.optionen = Array.from(this.$refs.select.options).map(o => ({
                wert: o.value,
                text: o.text,
            }));
        },
        get wert() {
            return String($wire.get(@js($modell)) ?? '');
        },
        get gefiltert() {
            const suche = this.suche.trim().toLowerCase();
            if (! suche) {
                return this.optionen;
            }
            return this.optionen.filter(o => o.text.toLowerCase().includes(suche));
        },
        get beschriftung() {
            return this.optionen.find(o => o.wert === this.wert)?.text ?? '';
        },
        platzieren() {
            const r = this.$refs.ausloeser.getBoundingClientRect();
            const platzUnten = window.innerHeight - r.bottom;
            this.x = r.left;
            this.breite = r.width;
            {{-- Nach oben klappen, wenn unten kein Platz mehr ist - sonst
                 haengt die Liste unter dem Bildschirmrand. --}}
            this.nachOben = platzUnten < 300 && r.top > platzUnten;
            this.y = this.nachOben ? window.innerHeight - r.top : r.bottom;
        },
        oeffnen() {
            this.offen = true;
            this.suche = '';
            this.markiert = this.gefiltert.findIndex(o => o.wert === this.wert);
            this.platzieren();
            this.$nextTick(() => this.$refs.suchfeld.focus());
        },
        schliessen() {
            this.offen = false;
            this.$refs.ausloeser.focus();
        },
        bewegen(schritte) {
            const anzahl = this.gefiltert.length;
            if (! anzahl) {
                this.markiert = -1;
                return;
            }
            this.markiert = (this.markiert + schritte + anzahl) % anzahl;
            this.$nextTick(() => this.$refs.liste
                ?.querySelector('[data-markiert]')
                ?.scrollIntoView({ block: 'nearest' }));
        },
        waehle(wert) {
            this.$refs.select.value = wert;
            $wire.set(@js($modell), wert);
            this.schliessen();
        },
    }"
    x-on:keydown.escape="if (offen) { $event.stopPropagation(); schliessen() }"
    x-on:click.outside="offen = false"
    x-on:scroll.window.capture="if (offen) platzieren()"
    x-on:resize.window="if (offen) platzieren()">

    {{-- aria-hidden und tabindex=-1: Bedient wird der Ausloeser darunter, das
         Select ist nur noch Traeger des Werts fuer Livewire. --}}
    <select
        x-ref="select"
        name="{{ $name }}"
        aria-hidden="true"
        tabindex="-1"
        @if ($fehler) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'sr-only']) }}
    >
        {{ $slot }}
    </select>

    <button type="button"
        x-ref="ausloeser"
        x-on:click="offen ? schliessen() : oeffnen()"
        x-on:keydown.down.prevent="oeffnen()"
        role="combobox"
        aria-haspopup="listbox"
        x-bind:aria-expanded="offen"
        aria-controls="{{ $name }}-liste"
        @class([
            'flex w-full items-center justify-between rounded-lg border bg-white px-3 py-2 text-left text-sm shadow-xs dark:bg-gray-800',
            'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500' => $fehler,
            'border-gray-300 dark:border-gray-700 focus:border-cerulean-500 focus:ring-cerulean-500' => ! $fehler,
        ])>
        <span x-text="beschriftung || '— {{ __('bitte wählen') }} —'"
            x-bind:class="beschriftung ? 'text-gray-900 dark:text-gray-300' : 'text-gray-500 dark:text-gray-400'"></span>
        <svg class="ml-2 size-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="offen" x-cloak
        x-bind:style="nachOben
            ? `left: ${x}px; bottom: ${y + 4}px; width: ${breite}px`
            : `left: ${x}px; top: ${y + 4}px; width: ${breite}px`"
        class="fixed z-50 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">

        <input type="search"
            x-ref="suchfeld"
            x-model="suche"
            x-on:input="markiert = gefiltert.length ? 0 : -1"
            x-on:keydown.down.prevent="bewegen(1)"
            x-on:keydown.up.prevent="bewegen(-1)"
            x-on:keydown.enter.prevent="if (gefiltert[markiert]) waehle(gefiltert[markiert].wert)"
            x-on:keydown.tab="offen = false"
            placeholder="{{ __('Suchen') }}"
            class="w-full rounded-t-lg border-0 border-b border-gray-200 bg-transparent px-3 py-2 text-sm text-gray-900 focus:ring-0 dark:border-gray-600 dark:text-gray-300" />

        <ul x-ref="liste" id="{{ $name }}-liste" role="listbox"
            class="max-h-60 overflow-y-auto py-1 text-sm">
            <template x-for="(option, i) in gefiltert" x-bind:key="option.wert">
                <li role="option"
                    x-bind:aria-selected="option.wert === wert"
                    x-bind:data-markiert="i === markiert ? '' : null"
                    x-on:click="waehle(option.wert)"
                    x-on:mouseenter="markiert = i"
                    x-bind:class="i === markiert
                        ? 'bg-cerulean-50 text-cerulean-700 dark:bg-gray-700 dark:text-cerulean-400'
                        : 'text-gray-700 dark:text-gray-300'"
                    class="cursor-pointer px-3 py-1.5"
                    x-text="option.text"></li>
            </template>
            <li x-show="! gefiltert.length" class="px-3 py-2 text-gray-500 dark:text-gray-400">
                {{ __('Nichts gefunden.') }}
            </li>
        </ul>
    </div>
</div>

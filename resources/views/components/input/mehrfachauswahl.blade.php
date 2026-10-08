{{--
    Like x-input.select, but several entries can be ticked - e.g. the domain
    controllers of an AD domain. Same trigger, same list, same search from
    twelve entries on: next to a single select it must not look like a
    different component.

    Livewire only: the value is an array on the bound property (wire:model).
    A click toggles an entry and leaves the list open - picking two DCs
    should not mean opening it twice.

    Options come as a prop (value => label), not as <option> slots: there is
    no native <select multiple> behind it, which nobody operates without Ctrl.
--}}
@props(['optionen' => [], 'feld' => null, 'leer' => '— keiner —'])

@php
    $modell = $attributes->wire('model')->value();
    $fehler = $feld && ($errors->has($feld) || $errors->has($feld.'.*'));
    $mitSuche = count($optionen) > 12;
    $liste = collect($optionen)->map(fn ($text, $wert) => ['wert' => (string) $wert, 'text' => $text])->values();
@endphp

<div {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'relative']) }}
    x-data="{
        offen: false,
        suche: '',
        markiert: -1,
        optionen: @js($liste),
        x: 0,
        y: 0,
        breite: 0,
        nachOben: false,
        mitSuche: @js($mitSuche),
        get werte() {
            return Array.from($wire.get(@js($modell)) ?? []).map(String);
        },
        get gefiltert() {
            const suche = this.suche.trim().toLowerCase();
            if (! suche) {
                return this.optionen;
            }
            return this.optionen.filter(o => o.text.toLowerCase().includes(suche));
        },
        get beschriftung() {
            return this.optionen.filter(o => this.werte.includes(o.wert)).map(o => o.text).join(', ');
        },
        platzieren() {
            const r = this.$refs.ausloeser.getBoundingClientRect();
            const platzUnten = window.innerHeight - r.bottom;
            this.x = r.left;
            this.breite = r.width;
            this.nachOben = platzUnten < 300 && r.top > platzUnten;
            this.y = this.nachOben ? window.innerHeight - r.top : r.bottom;
        },
        oeffnen() {
            if (this.offen) {
                return;
            }
            this.offen = true;
            this.suche = '';
            this.markiert = this.gefiltert.length ? 0 : -1;
            this.platzieren();
            if (this.mitSuche) {
                this.$nextTick(() => this.$refs.suchfeld?.focus());
            }
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
        umschalten(wert) {
            const werte = this.werte;
            $wire.set(@js($modell), werte.includes(wert)
                ? werte.filter(w => w !== wert)
                : [...werte, wert]);
        },
    }"
    x-on:keydown.escape="if (offen) { $event.stopPropagation(); schliessen() }"
    x-on:click.outside="offen = false"
    x-on:scroll.window.capture="if (offen) platzieren()"
    x-on:resize.window="if (offen) platzieren()">

    <button type="button"
        x-ref="ausloeser"
        x-on:click="offen ? schliessen() : oeffnen()"
        x-on:keydown.down.prevent="offen ? bewegen(1) : oeffnen()"
        x-on:keydown.up.prevent="offen ? bewegen(-1) : oeffnen()"
        x-on:keydown.enter.prevent="offen &amp;&amp; gefiltert[markiert] ? umschalten(gefiltert[markiert].wert) : oeffnen()"
        role="combobox"
        aria-haspopup="listbox"
        x-bind:aria-expanded="offen"
        @if ($fehler) aria-invalid="true" @endif
        @class([
            'flex w-full items-center justify-between gap-2 rounded-lg border bg-white px-3 py-2 text-left text-base shadow-xs dark:bg-gray-800',
            'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500 focus:ring-red-500' => $fehler,
            'border-gray-300 dark:border-gray-700 focus:border-cerulean-500 dark:focus:border-cerulean-500 focus:ring-cerulean-500' => ! $fehler,
        ])>
        <span class="truncate" x-text="beschriftung || @js(__($leer))"
            x-bind:class="beschriftung ? 'text-gray-900 dark:text-gray-300' : 'text-gray-500 dark:text-gray-400'"></span>
        <svg class="size-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    {{-- mousedown.prevent: a click on an entry must not take the focus
         from the trigger. Without it the focus fell to <body>, Escape no
         longer reached this component (which closes only the list) but the
         dialog - and closed the whole form with what was typed. --}}
    <div x-show="offen" x-cloak
        x-on:mousedown="if ($event.target !== $refs.suchfeld) $event.preventDefault()"
        x-bind:style="`left: ${x}px; min-width: ${breite}px; ` + (nachOben ? `bottom: ${y + 4}px` : `top: ${y + 4}px`)"
        class="fixed z-50 w-max max-w-[min(24rem,calc(100vw-2rem))] rounded-lg border border-gray-200 bg-gray-50 shadow-lg dark:border-gray-600 dark:bg-gray-700">

        @if ($mitSuche)
        <input type="search"
            x-ref="suchfeld"
            x-model="suche"
            x-on:input="markiert = gefiltert.length ? 0 : -1"
            x-on:keydown.down.prevent="bewegen(1)"
            x-on:keydown.up.prevent="bewegen(-1)"
            x-on:keydown.enter.prevent="if (gefiltert[markiert]) umschalten(gefiltert[markiert].wert)"
            x-on:keydown.tab="offen = false"
            placeholder="{{ __('Suchen') }}"
            class="w-full rounded-t-lg border-0 border-b border-gray-200 bg-transparent px-3 py-2 text-sm text-gray-900 focus:ring-0 dark:border-gray-600 dark:text-gray-300" />
        @endif

        <ul x-ref="liste" role="listbox" aria-multiselectable="true" class="max-h-60 overflow-y-auto py-1 text-sm">
            <template x-for="(option, i) in gefiltert" x-bind:key="option.wert">
                <li role="option"
                    x-bind:aria-selected="werte.includes(option.wert)"
                    x-bind:data-markiert="i === markiert ? '' : null"
                    x-on:click="umschalten(option.wert)"
                    x-on:mouseenter="markiert = i"
                    x-bind:class="i === markiert
                        ? 'bg-cerulean-50 text-cerulean-700 dark:bg-gray-600 dark:text-cerulean-400'
                        : 'text-gray-700 dark:text-gray-300'"
                    class="flex cursor-pointer items-center gap-2 px-3 py-1.5">
                    {{-- Tick instead of a checkbox: a real input would take
                         focus and clicks of its own. --}}
                    <svg class="size-4 shrink-0 text-cerulean-600 dark:text-cerulean-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"
                        x-bind:class="werte.includes(option.wert) ? '' : 'invisible'">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span x-text="option.text"></span>
                </li>
            </template>
            <li x-show="! gefiltert.length" class="px-3 py-2 text-gray-500 dark:text-gray-400">
                {{ __('Nichts gefunden.') }}
            </li>
        </ul>
    </div>
</div>

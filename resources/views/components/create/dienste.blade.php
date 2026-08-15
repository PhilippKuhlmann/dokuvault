{{--
    Dienste am Geraet: gewaehlte als Kacheln, darunter der Katalog aus der
    Administration zum Anklicken, darunter ein Feld fuer alles, was (noch)
    nicht im Katalog steht.

    Die Spalte am Geraet bleibt Freitext (komma-getrennt) - der Katalog gibt nur
    vor, was zur Auswahl steht und welche Farbe eine Kachel bekommt. Deshalb
    gibt die Komponente unveraendert ein einziges Feld "services" ab und der
    Controller merkt von der Umstellung nichts.
--}}
@props(['default' => ''])

@php
    $gewaehlt = collect(explode(',', (string) (old('services') ?? $default)))
        ->map(fn ($name) => trim($name))
        ->filter()
        ->unique()
        ->values();

    $katalog = \App\Models\Service::katalog();

    // Kleinschreibung als Schluessel: "AD" und "ad" sind derselbe Dienst.
    $stile = collect($katalog)->mapWithKeys(fn ($d) => [mb_strtolower($d['name']) => $d['stil']])->all();
@endphp

<div class="mt-2 sm:col-span-2"
    x-data="{
        gewaehlt: @js($gewaehlt->all()),
        neu: '',
        stile: @js($stile),
        stil(name) {
            return this.stile[name.toLowerCase()] ?? '';
        },
        hat(name) {
            return this.gewaehlt.some(d => d.toLowerCase() === name.toLowerCase());
        },
        dazu(name) {
            name = (name || '').trim();
            if (name && ! this.hat(name)) {
                this.gewaehlt.push(name);
            }
            this.neu = '';
        },
        weg(name) {
            this.gewaehlt = this.gewaehlt.filter(d => d !== name);
        },
    }">

    {{-- Das einzige Feld, das abgeschickt wird. --}}
    <input type="hidden" name="services" x-bind:value="gewaehlt.join(',')" />

    <x-input.label :value="__('Dienste')" />

    <div class="mt-1 flex min-h-[42px] flex-wrap items-center gap-2 rounded-sm border border-gray-300 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
        <template x-for="dienst in gewaehlt" x-bind:key="dienst">
            <span class="inline-flex items-center gap-1.5 rounded px-3 py-1 text-sm"
                x-bind:style="stil(dienst)"
                x-bind:class="stil(dienst) ? '' : 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-100'">
                <span x-text="dienst"></span>
                <button type="button" x-on:click="weg(dienst)" class="opacity-60 hover:opacity-100"
                    x-bind:aria-label="'{{ __('Entfernen') }}: ' + dienst">&times;</button>
            </span>
        </template>

        <span x-show="gewaehlt.length === 0" class="px-1 text-sm text-gray-400 dark:text-gray-500">
            {{ __('Noch keine Dienste ausgewählt.') }}
        </span>
    </div>

    {{-- Katalog aus der Administration. Bereits gewaehlte Eintraege verschwinden
         aus der Auswahl, damit die Liste zeigt, was noch dazukommen kann. --}}
    @if ($katalog)
        <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ __('Aus dem Katalog') }}
        </div>
        <div class="mt-1.5 flex flex-wrap gap-2">
            @foreach ($katalog as $dienst)
                {{-- Die Beschreibung im Hover-Fenster: Beim Auswaehlen ist genau
                     der Moment, in dem man wissen will, was "DFS" bedeutet. --}}
                <x-hovertext :text="$dienst['description']" x-show="! hat(@js($dienst['name']))" x-cloak>
                    <button type="button" x-show="! hat(@js($dienst['name']))" x-cloak
                        x-on:click="dazu(@js($dienst['name']))"
                        @class([
                            'rounded px-3 py-1 text-sm opacity-70 transition-opacity hover:opacity-100',
                            'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-100' => ! $dienst['stil'],
                        ])
                        @if ($dienst['stil']) style="{{ $dienst['stil'] }}" @endif>
                        + {{ $dienst['name'] }}
                    </button>
                </x-hovertext>
            @endforeach
        </div>
    @endif

    {{-- Freier Dienst. Enter fuegt hinzu, ohne das Formular abzuschicken. --}}
    <div class="mt-3 flex flex-wrap items-end gap-2">
        <div class="flex flex-col">
            <x-input.label :value="__('Nicht im Katalog?')" />
            <x-input.text x-model="neu" type="text" class="mt-1 w-56"
                x-on:keydown.enter.prevent="dazu(neu)"
                :placeholder="__('z. B. Nextcloud')" />
        </div>
        <x-input.button type="button" size="feld" x-on:click="dazu(neu)" :label="__('Hinzufügen')" />
    </div>

    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
        {{ __('Dienste ohne Katalogeintrag bleiben neutral. Farbe und Beschreibung lassen sich in der Administration ergänzen.') }}
    </p>
</div>

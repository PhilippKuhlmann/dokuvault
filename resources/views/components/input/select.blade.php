{{--
    Ein Auswahlfeld. Das native <select> bleibt bestehen und traegt weiterhin
    Name, Wert, Bindung und Fehlerzustand - es wird nur versteckt. Sichtbar ist
    ein Ausloeser mit eigener Liste darunter.

    Warum ueberhaupt: Ab einer gewissen Laenge findet man in einem <select>
    nichts mehr. Das Type-Ahead des Browsers greift nur vom Wortanfang -
    "Windows Server 2022 Standard" war nur ueber "Windows" erreichbar, nicht
    ueber "2022". Ab SCHWELLE Eintraegen erscheint deshalb ein Suchfeld, das an
    beliebiger Stelle sucht. Darunter bleibt es bei der blossen Liste: Bei drei
    Rollen waere ein Suchfeld ein Umweg.

    Das Aussehen ist in beiden Faellen dasselbe - ein Feld mit Suche und eines
    ohne duerfen sich nicht unterscheiden, sonst sieht ein Formular aus zwei
    Bauteilen zusammengesetzt aus.

    Ohne JavaScript bleibt das native <select> uebrig und damit benutzbar.

    Die Liste haengt am Viewport, nicht am Feld: Karten, Modale und Tabellen
    haben eigene Scrollrahmen, und die schneiden ein absolut positioniertes
    Fenster an ihrer Kante ab (overflow-x: auto macht auch overflow-y zu auto).
    position: fixed entkommt jedem Rahmen - dieselbe Loesung wie bei
    x-hovertext.
--}}
@props(['name', 'feld' => null])

@php
    $fehler = ($feld ?? $name) && $errors->has($feld ?? $name);

    // Der gebundene Eigenschaftsname, etwa 'form.operating_system_id' - leer
    // bei den klassischen Formularen (Adminbereich, Profil, Seitenleiste).
    // Danach richtet sich, woher der angezeigte Wert kommt: bei Livewire aus
    // $wire, sonst aus dem <select> selbst. Aus $wire zu lesen ist dort
    // notwendig, weil ein Modal fuer "Neu" und "Bearbeiten" wiederverwendet
    // wird - Livewire tauscht nur den Inhalt, Alpines init() liefe kein
    // zweites Mal, und der Ausloeser zeigte "bitte waehlen", waehrend laengst
    // ein Wert geladen war.
    $modell = $attributes->wire('model')->value();

    // Die Klassen der Aufrufstelle mussten bisher nur an eine Stelle, das
    // <select>. Jetzt gibt es zwei, und sie wollen Verschiedenes: Abstand und
    // Breite gehoeren an den Rahmen, damit das Feld im Layout sitzt wie vorher
    // - Schrift und Innenabstand an den Ausloeser, denn der ist das, was man
    // sieht. Ein 'mt-1' am Ausloeser wuerde ihn im Rahmen nach unten schieben,
    // ein 'py-1' am Rahmen die Zeile hoeher machen als gewollt.
    $klassen = preg_split('/\s+/', trim($attributes->get('class') ?? ''), -1, PREG_SPLIT_NO_EMPTY);

    [$rahmenklassen, $ausloeserklassen] = collect($klassen)->partition(
        fn ($k) => (bool) preg_match('/^(sm:|md:|lg:)?(m[trblxy]?-|w-|col-span-|self-|grow|shrink|order-)/', $k)
    );

    $durchgereicht = $attributes->except('class');

    // Ohne eigene Masse die bisherigen des <select>.
    $eigeneMasse = $ausloeserklassen->contains(fn ($k) => (bool) preg_match('/^(sm:|md:|lg:)?(p[trblxy]?-|text-(xs|sm|base|lg))/', $k));

    // Ab wie vielen Eintraegen ein Suchfeld erscheint. Zwoelf, weil bis dahin
    // die Liste ohne Scrollen sichtbar bleibt - darunter waere die Suche ein
    // zusaetzlicher Schritt zu nichts.
    //
    // Gezaehlt wird hier und nicht in Alpine, damit es im gerenderten HTML
    // steht: Sonst laesst sich nicht pruefen, welches Feld eine Suche bekommt,
    // und kurze Listen schleppten ein Suchfeld mit, das nie zu sehen ist.
    $mitSuche = substr_count((string) $slot, '<option') > 12;
@endphp

<div @class(['relative', $rahmenklassen->implode(' ') => $rahmenklassen->isNotEmpty()])
    x-data="{
        offen: false,
        suche: '',
        markiert: -1,
        optionen: [],
        eigenerWert: '',
        x: 0,
        y: 0,
        breite: 0,
        nachOben: false,
        mitSuche: @js($mitSuche),
        init() {
            this.lesen();
            {{-- Livewire tauscht bei einem Re-Render auch Optionslisten aus -
                 etwa die Switches eines Patchfelds. Ein einmal beim Init
                 gemerkter Bestand waere danach veraltet. --}}
            new MutationObserver(() => this.lesen())
                .observe(this.$refs.select, { childList: true, subtree: true });
        },
        lesen() {
            this.optionen = Array.from(this.$refs.select.options)
                .map(o => ({ wert: o.value, text: o.text }));
            @unless ($modell)
                this.eigenerWert = this.$refs.select.value;
            @endunless
        },
        get wert() {
            @if ($modell)
                return String($wire.get(@js($modell)) ?? '');
            @else
                return this.eigenerWert;
            @endif
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
            if (this.offen) {
                return;
            }
            this.offen = true;
            this.suche = '';
            this.markiert = this.gefiltert.findIndex(o => o.wert === this.wert);
            this.platzieren();
            {{-- Nur mit Suchfeld wandert der Fokus; ohne bleibt er am
                 Ausloeser, der die Pfeiltasten selbst behandelt. --}}
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
        waehle(wert) {
            this.$refs.select.value = wert;
            @if ($modell)
                $wire.set(@js($modell), wert);
            @else
                this.eigenerWert = wert;
                {{-- 'change', nicht 'input': Darauf hoeren die
                     onchange-Handler der klassischen Formulare. Mit 'input'
                     sieht die Auswahl richtig aus, kommt aber nirgends an. --}}
                this.$refs.select.dispatchEvent(new Event('change', { bubbles: true }));
            @endif
            this.schliessen();
        },
    }"
    x-on:keydown.escape="if (offen) { $event.stopPropagation(); schliessen() }"
    x-on:click.outside="offen = false"
    x-on:scroll.window.capture="if (offen) platzieren()"
    x-on:resize.window="if (offen) platzieren()">

    {{-- aria-hidden und tabindex=-1: Bedient wird der Ausloeser. Der Fokus
         kann hier trotzdem landen, wenn jemand die Beschriftung anklickt -
         label[for] trifft das <select>, ganz gleich welchen tabindex es hat.
         Dann oeffnet sich die Liste, statt dass der Fokus ins Nichts faellt. --}}
    <select
        x-ref="select"
        name="{{ $name }}"
        aria-hidden="true"
        tabindex="-1"
        x-on:focus="oeffnen()"
        @if ($fehler) aria-invalid="true" @endif
        {{ $durchgereicht->merge(['class' => 'sr-only']) }}
    >
        {{ $slot }}
    </select>

    <button type="button"
        x-ref="ausloeser"
        x-on:click="offen ? schliessen() : oeffnen()"
        x-on:keydown.down.prevent="offen ? bewegen(1) : oeffnen()"
        x-on:keydown.up.prevent="offen ? bewegen(-1) : oeffnen()"
        x-on:keydown.enter.prevent="offen &amp;&amp; gefiltert[markiert] ? waehle(gefiltert[markiert].wert) : oeffnen()"
        role="combobox"
        aria-haspopup="listbox"
        x-bind:aria-expanded="offen"
        @class([
            'flex w-full items-center justify-between gap-2 rounded-lg border bg-white text-left shadow-xs dark:bg-gray-800',
            'px-3 py-2 text-base' => ! $eigeneMasse,
            $ausloeserklassen->implode(' ') => $ausloeserklassen->isNotEmpty(),
            'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500 focus:ring-red-500' => $fehler,
            'border-gray-300 dark:border-gray-700 focus:border-cerulean-500 dark:focus:border-cerulean-500 focus:ring-cerulean-500' => ! $fehler,
        ])>
        <span class="truncate" x-text="beschriftung"
            x-bind:class="beschriftung ? 'text-gray-900 dark:text-gray-300' : 'text-gray-500 dark:text-gray-400'"></span>
        <svg class="size-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="offen" x-cloak
        {{-- Mindestens so breit wie das Feld, aber mitwachsend: In schmalen
             Tabellenspalten braechen lange Namen sonst auf drei Zeilen um.
             Ein natives Select-Popup wird auch breiter als sein Feld. --}}
        x-bind:style="`left: ${x}px; min-width: ${breite}px; ` + (nachOben ? `bottom: ${y + 4}px` : `top: ${y + 4}px`)"
        class="fixed z-50 w-max max-w-[min(24rem,calc(100vw-2rem))] rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">

        @if ($mitSuche)
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
        @endif

        <ul x-ref="liste" role="listbox" class="max-h-60 overflow-y-auto py-1 text-sm">
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

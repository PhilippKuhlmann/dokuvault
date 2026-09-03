{{-- Knopf plus Modal fuer jeden Typ aus config/forms.php.

     Aufbau und Knopfleiste folgen dem VLAN-Modal: Loeschen links abgesetzt,
     Abbrechen und Speichern rechts, die Rueckfrage ersetzt die Felder. --}}
<div class="inline">
    @can($typ.'_create')
        <button type="button" wire:click="neu"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('Neu') }}
        </button>
    @endcan

    @if ($offen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="$wire.abbrechen()">

            @php
                // Die Breite richtet sich nach dem, was drinsteht: Ein
                // Formular mit zwei Feldern braucht keine halbe Bildschirm-
                // breite, die Bloecke schon - Zugangsdaten und IP-Adressen sind
                // Tabellen mit vier Spalten und standen in max-w-md
                // ineinandergequetscht.
                //
                // 'breit' geht darueber hinaus und ist die Ausnahme: Wo das
                // Formular selbst fast nichts enthaelt, ist der Block der
                // eigentliche Inhalt und bekommt den Platz (FTP-Server: zwei
                // Felder, darunter die Zugangsdaten).
                $breite = match (true) {
                    $spalten > 1 => 'max-w-3xl',
                    $mehrzeiligeFelder => 'max-w-3xl',
                    $breitesModal && $bearbeiteId => 'max-w-3xl',
                    $mitBloecken && $bearbeiteId => 'max-w-2xl',
                    default => 'max-w-md',
                };
            @endphp
            <div class="max-h-[90vh] w-full {{ $breite }} overflow-y-auto rounded-xl border border-gray-200 bg-white px-5 pt-5 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">

                @unless ($loeschenGefragt)
                    <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                        {{ $bearbeiteId ? __($einzahl).' '.__('bearbeiten') : __('Neu').': '.__($einzahl) }}
                    </div>

                    {{-- Zwei Spalten, wo es viele Felder sind: Zwanzig Eingaben untereinander
                         ergeben eine Scrollstrecke, bei der man den Anfang aus den
                         Augen verliert. Felder mit 'breit' spannen ueber beide. --}}
                    <div @class([
                        'gap-x-4 gap-y-3',
                        'flex flex-col' => $spalten === 1,
                        'grid grid-cols-1 sm:grid-cols-2' => $spalten > 1,
                    ])>
                        @foreach ($felder as $feld)
                            {{-- Technische Felder wie "hidden" gehoeren ins
                                 Formular, aber nicht vor die Augen: Sie werden
                                 beim Bearbeiten geladen und beim Speichern
                                 unveraendert zurueckgeschrieben. --}}
                            @continue($feld['type'] === 'versteckt')
                            <div wire:key="feld-{{ $feld['name'] }}"
                                @class([
                                    'flex flex-col',
                                    'sm:col-span-2' => $spalten > 1 && ($feld['breit'] ?? false),
                                ])
                                {{-- Felder, die nur zu einer Bauform gehoeren: Ein
                                     Standserver hat keine Einbautiefe. --}}
                                @if (! empty($feld['sichtbar_wenn']))
                                    @php
                                        // Mehrere Bedingungen werden mit UND verknuepft: Der
                                        // Standort einer VM erscheint erst, wenn weder Host
                                        // noch Cluster gesetzt sind.
                                        //
                                        // Ein Freitextfeld wie der Hersteller wird verglichen,
                                        // nicht auf Gleichheit geprueft: "Securepoint GmbH" ist
                                        // dasselbe wie "Securepoint".
                                        //
                                        // 'leer': sichtbar, solange das andere Feld nichts
                                        // enthaelt - der Standort einer VM eruebrigt sich,
                                        // sobald ein Host gewaehlt ist, der seinen eigenen
                                        // mitbringt.
                                        $ausdruck = collect($feld['sichtbar_wenn'])
                                            ->map(fn ($erwartet, $anderesFeld) => match (true) {
                                                is_array($erwartet) && isset($erwartet['enthaelt']) => "(\$wire.form.{$anderesFeld} || '').toLowerCase().includes('".strtolower($erwartet['enthaelt'])."')",
                                                is_array($erwartet) && isset($erwartet['leer']) => "! \$wire.form.{$anderesFeld}",
                                                default => "\$wire.form.{$anderesFeld} === '{$erwartet}'",
                                            })
                                            ->implode(' && ');
                                    @endphp
                                    {{-- Unescaped, weil der Ausdruck JavaScript ist und aus
                         config/forms.php stammt, nicht aus einer Eingabe. Mit
                         {{ }} wuerden die Anfuehrungszeichen zu &#039; und der
                         Vergleich schluege immer fehl. --}}
                    x-show="{!! $ausdruck !!}"
                                    x-cloak
                                @endif>
                                @unless (in_array($feld['type'], ['dienste', 'einheit']))
                                    <x-input.label :value="__($feld['label'])" />
                                @endunless

                                @if ($feld['type'] === 'standort')
                                    <x-input.select :name="$feld['name']" wire:model="form.{{ $feld['name'] }}" class="mt-1">
                                        <option value="">— {{ __('bitte wählen') }} —</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </x-input.select>
                                @elseif ($feld['type'] === 'dienste')
                                    {{-- Dieselbe Auswahl wie auf der Seite: Kacheln,
                                         Katalog aus der Administration, Freitext.
                                         Sie meldet jede Aenderung an Livewire, statt
                                         ein verstecktes Formularfeld zu fuellen. --}}
                                    <x-create.dienste :default="$form[$feld['name']] ?? ''"
                                        wire-model="form.{{ $feld['name'] }}" />
                                @elseif ($feld['type'] === 'optionen')
                                    {{-- Feste Liste aus config/custom.php, etwa die
                                         Bauform eines Servers. --}}
                                    @php ($auswahlwerte = $feld['werte'] ?? config($feld['quelle']))
                                    {{-- Ist nichts gespeichert, zeigt eine Auswahl ohne
                                         passenden Eintrag ihren ersten an - beim
                                         AD-Status stand dann "Aktiv" im Fenster,
                                         waehrend die Liste "—" zeigte und in der
                                         Datenbank NULL stand. Ein eigener Eintrag
                                         sagt stattdessen, dass es niemand weiss. --}}
                                    @php ($wertBekannt = in_array(
                                        (string) ($form[$feld['name']] ?? ''),
                                        array_map('strval', array_keys($auswahlwerte)),
                                        true
                                    ))
                                    <x-input.select :name="$feld['name']" wire:model.live="form.{{ $feld['name'] }}" class="mt-1">
                                        @unless ($wertBekannt)
                                            <option value="">— {{ __('unbekannt') }} —</option>
                                        @endunless
                                        @foreach ($auswahlwerte as $wert => $beschriftung)
                                            <option value="{{ $wert }}">{{ __($beschriftung) }}</option>
                                        @endforeach
                                    </x-input.select>
                                @elseif ($feld['type'] === 'einheit')
                                    {{-- Zahl mit fester Einheit dahinter, etwa
                                         "250 | Mbit/s". Die Einheit ist
                                         Beschriftung, kein Eingabewert. --}}
                                    <x-create.einheit :label="__($feld['label'])" :name="$feld['name']"
                                        :einheit="$feld['einheit']" wire-model="form.{{ $feld['name'] }}" />
                                @elseif ($feld['type'] === 'datei')
                                    <x-input.file wire:model="datei" />

                                    {{-- Was schon hinterlegt ist: Ohne diesen
                                         Hinweis weiss man beim Bearbeiten nicht,
                                         ob ueberhaupt eine Datei da ist - und
                                         ueberschreibt sie womoeglich blind. --}}
                                    @if ($objekt?->{$feld['pfad_feld']})
                                        <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Hinterlegt:') }}
                                            {{ $objekt->{$feld['name_feld']} ?: basename($objekt->{$feld['pfad_feld']}) }}
                                            — {{ __('eine neue Datei ersetzt sie') }}
                                        </span>
                                    @endif

                                    <div wire:loading wire:target="datei" class="mt-1 text-xs text-cerulean-600 dark:text-cerulean-400">
                                        {{ __('Datei wird übertragen …') }}
                                    </div>

                                    <x-input.fehler feld="datei" />
                                @elseif ($feld['type'] === 'schalter')
                                    <label class="mt-1 inline-flex items-center gap-2">
                                        <input type="checkbox" wire:model="form.{{ $feld['name'] }}"
                                            class="rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Ja') }}</span>
                                    </label>
                                @elseif ($feld['type'] === 'auswahl')
                                    <x-input.select :name="$feld['name']" wire:model="form.{{ $feld['name'] }}" class="mt-1">
                                        <option value="">— {{ __('bitte wählen') }} —</option>
                                        @foreach (($auswahlen[$feld['name']] ?? []) as $id => $beschriftung)
                                            <option value="{{ $id }}">{{ $beschriftung }}</option>
                                        @endforeach
                                    </x-input.select>
                                @elseif ($feld['type'] === 'mehrzeilig')
                                    {{-- Werte, die keine Zeile sind, etwa ein
                                         SSH-Schluessel. --}}
                                    <x-input.textarea wire:model="form.{{ $feld['name'] }}"
                                        :rows="$feld['zeilen'] ?? 3"
                                        :placeholder="$feld['platzhalter'] ?? ''" class="mt-1" />
                                @else
                                    {{-- Hersteller und Modell werden bei Geraeten
                                         mit Frontblende laufend uebertragen: Nur
                                         so kann der Block unten melden, dass es
                                         zu dieser Schreibweise schon ein Bild
                                         gibt. Ohne das saehe man erst nach dem
                                         Speichern, ob der Abgleich getroffen hat.
                                         Alle uebrigen Felder bleiben gestundet -
                                         eine Runde je Tastenpause fuer jedes Feld
                                         waere Verschwendung. --}}
                                    @if ($mitModellbild && in_array($feld['name'], ['manufacturer', 'model'], true))
                                        <x-input.text wire:model.live.debounce.600ms="form.{{ $feld['name'] }}"
                                            type="{{ $feld['type'] }}" class="mt-1" />
                                    @else
                                        <x-input.text wire:model="form.{{ $feld['name'] }}"
                                            type="{{ $feld['type'] }}" class="mt-1" />
                                    @endif
                                @endif

                                <x-input.fehler :feld="'form.'.$feld['name']" />

                                {{-- Vorherige Kennwoerter. Der Fall dahinter: Jemand
                                     hat falsch geaendert, und man braucht das alte
                                     zurueck. Der Wert wird erst auf Klick geholt. --}}
                                @if (! empty($verlauf[$feld['name']]))
                                    <div class="mt-1.5">
                                        @if (isset($gezeigterVerlauf[$feld['name']]))
                                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-600 dark:bg-gray-900/40">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('Bisherige Kennwörter') }}</span>
                                                    <button type="button" wire:click="verlaufVerbergen('{{ $feld['name'] }}')"
                                                        class="text-xs text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">{{ __('verbergen') }}</button>
                                                </div>
                                                <div class="mt-1.5 space-y-1.5">
                                                    @foreach ($gezeigterVerlauf[$feld['name']] as $eintrag)
                                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                            <x-password :value="$eintrag['wert']" width="w-40" />
                                                            <span class="text-xs text-gray-400 dark:text-gray-500" title="{{ $eintrag['wann'] }}">
                                                                {{ $eintrag['seit'] }}@if ($eintrag['wer']) · {{ $eintrag['wer'] }}@endif
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <button type="button" wire:click="verlaufZeigen('{{ $feld['name'] }}')"
                                                class="text-xs text-gray-500 hover:text-cerulean-600 dark:text-gray-400 dark:hover:text-cerulean-400">
                                                {{ __('Zuletzt geändert') }} {{ $verlauf[$feld['name']]['zuletzt'] }} —
                                                <span class="underline">{{ trans_choice('{1}vorheriges Kennwort anzeigen|[2,*]:anzahl vorherige anzeigen', $verlauf[$feld['name']]['anzahl'], ['anzahl' => $verlauf[$feld['name']]['anzahl']]) }}</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($mitModellbild)
                        {{-- Ein Foto der Frontblende - fuer das Modell, nicht
                             fuer dieses eine Geraet. Deshalb der Hinweis:
                             Wer es hier hinterlegt, hinterlegt es fuer jeden
                             Kunden, bei dem dasselbe Geraet steht. --}}
                        <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                            <x-input.label for="modellbild" :value="__('Bild der Frontblende')" />

                            <div class="mt-1 flex flex-wrap items-start gap-4">
                                <x-input.file id="modellbild" wire:model="modellbild"
                                    accept="{{ collect(config('custom.bild_formate'))->map(fn ($e) => '.'.$e)->join(',') }}" />

                                @if ($modell?->bildUrl())
                                    {{-- Im Seitenverhaeltnis einer Blende, nicht in
                                         einem festen Kasten: Sonst schwebt ein
                                         1-HE-Geraet als duenner Streifen in der
                                         Mitte einer leeren Flaeche. --}}
                                    <figure class="w-48">
                                        <img src="{{ $modell->bildUrl() }}" alt=""
                                            style="aspect-ratio: 1086 / {{ 100 * max(1, (int) $modell->height_units) }};"
                                            class="w-full rounded border border-gray-300 bg-gray-100 object-contain dark:border-gray-600 dark:bg-gray-900">
                                        <figcaption class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                            {{ __('Hinterlegt für') }} {{ $modell->bezeichnung() }} —
                                            {{ __('ein neues Bild ersetzt es') }}
                                        </figcaption>
                                    </figure>
                                @endif

                                {{-- Waehrend die Runde laeuft, steht die Meldung
                                     von der vorigen Schreibweise noch da. Ein
                                     stiller Hinweis ist ehrlicher als eine
                                     Auskunft, die gerade veraltet. --}}
                                <span wire:loading wire:target="form.manufacturer, form.model"
                                    class="self-center text-xs text-gray-400 dark:text-gray-500">
                                    {{ __('wird geprüft …') }}
                                </span>
                            </div>

                            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                {{ __('Das Bild gehört zum Modell, nicht zu diesem Gerät: Es erscheint überall dort, wo Hersteller und Modell übereinstimmen — auch bei anderen Kunden. Gepflegt wird es im Adminbereich unter „Auswahlmenüs → Gerätemodelle".') }}
                            </p>

                            <div wire:loading wire:target="modellbild" class="mt-1 text-xs text-cerulean-600 dark:text-cerulean-400">
                                {{ __('Datei wird übertragen …') }}
                            </div>

                            <x-input.fehler feld="modellbild" />
                        </div>
                    @endif

                    @if ($erzeugerLabel)
                        {{-- Fuellt Felder, speichert aber nicht: Erst der
                             Speichern-Knopf legt an. Beim Bearbeiten wird
                             nachgefragt, sonst ist ein dokumentierter
                             Schluessel mit einem Klick weg. --}}
                        <div class="mt-3 flex items-center gap-3">
                            {{-- Zwei Varianten statt einer mit @if in den
                                 Attributen: Ein @if innerhalb eines
                                 Komponenten-Tags uebersetzt Blade nicht - der
                                 Tag stand woertlich im HTML. Und ein leeres
                                 wire:confirm gilt trotzdem als Nachfrage, haelt
                                 beim Anlegen also mit einem leeren Dialog an. --}}
                            @if ($bearbeiteId)
                                <x-input.button type="button" size="feld" color="gray" wire:click="erzeugen"
                                    wire:confirm="{{ __('Vorhandene Werte werden überschrieben. Fortfahren?') }}"
                                    :label="__($erzeugerLabel)" />
                            @else
                                <x-input.button type="button" size="feld" color="gray" wire:click="erzeugen"
                                    :label="__($erzeugerLabel)" />
                            @endif
                            <span wire:loading wire:target="erzeugen" class="text-xs text-cerulean-600 dark:text-cerulean-400">
                                {{ __('wird erzeugt …') }}
                            </span>
                        </div>
                    @endif

                    @if ($mitBloecken)
                        @if ($bearbeiteId)
                            {{-- Eigene Livewire-Bloecke mit eigenem Speichern -
                                 deshalb ausserhalb der Felder darueber. --}}
                            {{-- Keine eigene Trennlinie: Der Block bringt im
                                 eingebetteten Zustand selbst eine mit, zwei
                                 uebereinander sahen aus wie ein Fehler. --}}
                            <div class="mt-1">
                                @if ($mitIpAdressen)
                                    <livewire:device-ip-addresses :model="$objekt" :customer="$kunde" eingebettet randlos
                                        :key="'ip-'.$typ.'-'.$objekt->id" />
                                @endif
                                @if ($mitZugangsdaten)
                                    <livewire:device-credentials :model="$objekt" :customer="$kunde" eingebettet randlos
                                        :key="'zug-'.$typ.'-'.$objekt->id" />
                                @endif
                            </div>
                        @else
                            {{-- Beim Anlegen haengt noch nichts am Objekt. Der Hinweis
                                 stand auch im alten Formular, damit das Fehlen nicht
                                 wie ein Mangel aussieht. --}}
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                @if ($mitIpAdressen && $mitZugangsdaten)
                                    {{ __('IP-Adressen und Zugangsdaten lassen sich eintragen, sobald der Eintrag angelegt ist.') }}
                                @elseif ($mitIpAdressen)
                                    {{ __('IP-Adressen lassen sich eintragen, sobald der Eintrag angelegt ist.') }}
                                @else
                                    {{ __('Zugangsdaten lassen sich eintragen, sobald der Eintrag angelegt ist.') }}
                                @endif
                            </p>
                        @endif
                    @endif
                @endunless

                @if ($loeschenGefragt)
                    {{-- Die Rueckfrage ersetzt die Felder, statt unter ihnen zu
                         haengen: Bei vielen Feldern stand sie sonst ausserhalb
                         des Sichtbereichs. --}}
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
                        <div class="text-sm font-medium text-red-800 dark:text-red-300">
                            {{ __($einzahl) }} {{ __('löschen?') }}
                        </div>
                        <p class="mt-1 text-xs text-red-700/80 dark:text-red-400/80">
                            {{ __('Der Eintrag landet im Papierkorb und lässt sich von dort wiederherstellen.') }}
                        </p>

                        <div class="mt-4 flex justify-end gap-2">
                            <x-input.button type="button" color="gray"
                                wire:click="$set('loeschenGefragt', false)" :label="__('Abbrechen')" />
                            <x-input.button type="button" color="red" wire:click="loeschen" :label="__('Löschen')" />
                        </div>
                    </div>
                @else
                    {{-- Am unteren Rand haften: Mit den Bloecken fuer IP-Adressen und
                         Zugangsdaten wird das Modal so hoch, dass Speichern sonst
                         ausserhalb des Bildes liegt und erst gescrollt werden muss.
                         Der eigene Hintergrund verhindert, dass Felder darunter
                         durchscheinen. --}}
                    <div class="sticky bottom-0 -mx-5 mt-5 flex flex-wrap items-center justify-end gap-2 rounded-b-xl border-t border-gray-100 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
                        @if ($bearbeiteId)
                            @can($typ.'_delete')
                                <x-input.button type="button" color="red" class="mr-auto"
                                    wire:click="$set('loeschenGefragt', true)" :label="__('Löschen')" />
                            @endcan
                        @endif

                        {{-- abbrechen() statt offen=false: sonst bleibt bearbeiteId stehen
                             und das naechste "Neu" oeffnet das Bearbeiten-Modal. --}}
                        <x-input.button type="button" color="gray" wire:click="abbrechen" :label="__('Abbrechen')" />
                        <x-input.button type="button" wire:click="speichern"
                            :label="$bearbeiteId ? __('Speichern') : __('Anlegen')" />
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<div class="p-3 sm:p-5 space-y-6">
    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Allgemein') }}</div>

    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Name und Logo') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Beides steht in der Kopfzeile, auf der Anmeldeseite und im PDF-Export. Änderungen gelten sofort.') }}
        </p>

        <div>
            <x-input.label for="name" :value="__('Name')" />
            {{-- .live mit Pause: Ohne die ginge bei jedem Tastendruck eine
                 Anfrage heraus. --}}
            <x-input.field id="name" wire:model.live.debounce.600ms="name" class="mt-1 w-full"
                :placeholder="$standardName" />

            <div class="mt-1 flex items-center gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Leer lassen für den Namen aus der Konfiguration:') }} <span class="font-mono">{{ $standardName }}</span>
                </p>
                {{-- Nur waehrend der Anfrage: ein ruhiges Zeichen, dass der
                     Wert unterwegs ist - statt eines Speichern-Knopfes. --}}
                <span wire:loading wire:target="name" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
            </div>

            @error('name')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Drei Felder statt eines: Das Logo auf der Anmeldeseite darf gross
             und breit sein, das in der Kopfzeile muss neben den Namen passen,
             ein Favicon ist quadratisch. --}}
        @foreach ($stellen as $s)
            <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-700" wire:key="logo-{{ $s['stelle'] }}">
                <x-input.label :for="'logo_'.$s['stelle']" :value="__('Logo').' — '.__($s['label'])" />
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __($s['hinweis']) }}</p>

                <div class="mt-2 flex flex-wrap items-center gap-4">
                    @if ($s['vorhanden'])
                        {{-- Auf kariertem Grund: Ein Logo mit transparentem Rand
                             sieht auf Weiss aus, als haette es keinen. --}}
                        <span class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700"
                            style="background-image: linear-gradient(45deg, #eee 25%, transparent 25%), linear-gradient(-45deg, #eee 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #eee 75%), linear-gradient(-45deg, transparent 75%, #eee 75%); background-size: 12px 12px; background-position: 0 0, 0 6px, 6px -6px, -6px 0;">
                            <img src="{{ route('branding.logo', $s['stelle']) }}?v={{ now()->timestamp }}"
                                alt="{{ __($s['label']) }}" class="h-10 w-auto max-w-[12rem] object-contain" />
                        </span>

                        {{-- Ein Knopf, kein Haken mit Speichern danach: Wer
                             entfernen will, klickt einmal. --}}
                        {{-- Ohne Rueckfrage: Ein Logo ist in zehn Sekunden
                             wieder hochgeladen, und eine Rueckfrage waere
                             derselbe Zweischritt, den der Haken vorher hatte. --}}
                        <button type="button" wire:click="entfernen('{{ $s['stelle'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-red-600 shadow-sm transition-colors hover:border-red-300 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                            <x-svg.trash class="h-4 w-4" />
                            {{ __('Entfernen') }}
                        </button>
                    @endif

                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-cerulean-600 px-4 py-2 text-sm font-DINPro-bold text-white shadow-sm transition-colors hover:bg-cerulean-700">
                        {{ $s['vorhanden'] ? __('Ersetzen') : __('Logo wählen') }}
                        {{-- Ohne sichtbares Feld: Der eingebaute Datei-Knopf
                             laesst sich nicht gestalten und sieht in jedem
                             Browser anders aus. --}}
                        <input type="file" id="logo_{{ $s['stelle'] }}" wire:model="logo_{{ $s['stelle'] }}"
                            accept="image/png,image/jpeg,image/webp" class="hidden" />
                    </label>

                    <span wire:loading wire:target="logo_{{ $s['stelle'] }}" class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('lädt …') }}
                    </span>
                </div>

                @error('logo_'.$s['stelle'])
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Erlaubt sind :formate, höchstens 512 KB. PNG mit transparentem Hintergrund passt in hellem und dunklem Erscheinungsbild.', ['formate' => strtoupper(implode(', ', $formate))]) }}
        </p>
        {{-- Kein SVG: Eine SVG-Datei darf Skript enthalten, und von derselben
             Herkunft ausgeliefert waere das ausfuehrbarer Code auf jeder Seite. --}}
    </div>

    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Zeitzone') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('Gilt für alle angezeigten Zeitpunkte. Gespeichert wird weiterhin in UTC — die Einstellung ändert nur die Anzeige.') }}</p>

        {{-- Nur die Anzeige, nicht die Ablage: Gespeichert wird weiter in
             UTC. Waere es anders, stuenden nach der Umstellung zwei Zeitzonen
             in derselben Spalte. --}}
        <div>
            {{-- Beschriftung nur fuer Vorleseprogramme: Ueber dem Feld steht
                 die Ueberschrift der Karte, und zweimal "Zeitzone"
                 untereinander liest sich wie ein Fehler. --}}
            <x-input.label for="zeitzone" :value="__('Zeitzone')" class="sr-only" />
            <x-input.select id="zeitzone" name="zeitzone" wire:model.live="zeitzone" class="mt-1 w-full">
                @foreach ($zonen as $zone)
                    <option value="{{ $zone }}">{{ $zone }}</option>
                @endforeach
            </x-input.select>

            <div class="mt-1 flex items-center gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Gerade ist es:') }}
                    <span class="font-mono">{{ $jetzt }}</span>
                </p>
                <span wire:loading wire:target="zeitzone" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
            </div>

            @error('zeitzone')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Hochladen') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('Gilt für Dateien an Lizenzen und Zertifikaten und für die Dateiablage eines Kunden.') }}</p>

        {{-- Nach oben durch den Server begrenzt: Ein hoeherer Wert waere ein
             Versprechen, das nicht haelt - der Upload braeche mitten im
             Hochladen ab, ohne verstaendliche Meldung. --}}
        <div>
            <x-input.label for="uploadMb" :value="__('Größte Datei beim Hochladen (MB)')" />
            <x-input.field id="uploadMb" type="number" min="1" :max="$serverMb"
                wire:model.live.debounce.600ms="uploadMb" class="mt-1 w-40" />

            <div class="mt-1 flex flex-wrap items-center gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Dieser Server nimmt höchstens :max MB an', ['max' => $serverMb]) }}
                    <span class="font-mono">(upload_max_filesize {{ $phpWerte['upload_max_filesize'] }}, post_max_size {{ $phpWerte['post_max_size'] }})</span>.
                    {{ __('Sitzt ein Webserver davor, kann dessen eigene Grenze noch niedriger sein.') }}
                </p>
                <span wire:loading wire:target="uploadMb" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
            </div>

            @error('uploadMb')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

</div>

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
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Sprache und Zeitzone') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('Beides gilt für die ganze Installation. Die Zeitzone ändert nur die Anzeige — gespeichert wird weiterhin in UTC.') }}</p>

        <div class="mb-6">
            <x-input.label for="sprache" :value="__('Sprache')" />
            <x-input.select id="sprache" name="sprache" wire:model.live="sprache" class="mt-1 w-full">
                @foreach (config('custom.locales') as $kuerzel => $bezeichnung)
                    <option value="{{ $kuerzel }}">{{ $bezeichnung }}</option>
                @endforeach
            </x-input.select>

            <div class="mt-1 flex items-center gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Greift, wenn der Benutzer im Profil nichts gewählt hat und der Browser keine der angebotenen Sprachen verlangt.') }}
                </p>
                <span wire:loading wire:target="sprache" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
            </div>

            @error('sprache')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Nur die Anzeige, nicht die Ablage: Gespeichert wird weiter in
             UTC. Waere es anders, stuenden nach der Umstellung zwei Zeitzonen
             in derselben Spalte. --}}
        <div>
            <x-input.label for="zeitzone" :value="__('Zeitzone')" />
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

        <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-700">
            <x-input.label :value="__('Erlaubte Dateiendungen')" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Eine Positivliste: Was hier nicht angehakt ist, kommt nicht herein. Abwählen geht, hinzufügen nicht — die Auswahl stammt aus der Konfiguration.') }}
            </p>

            <div class="mt-3 grid grid-cols-3 gap-x-4 gap-y-2 sm:grid-cols-4">
                @foreach (config('custom.datei_formate') as $endung)
                    <label class="flex cursor-pointer select-none items-center gap-2" wire:key="format-{{ $endung }}">
                        <input type="checkbox" value="{{ $endung }}" wire:model.live="endungen"
                            class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                        <span class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $endung }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-2 flex items-center gap-2">
                {{-- Kein SVG in der Liste: Ein SVG darf Skripte enthalten. Das
                     ist eine Sicherheitsentscheidung und bleibt im Code. --}}
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('SVG steht bewusst nicht zur Wahl: Eine SVG-Datei darf Skripte enthalten.') }}
                </p>
                <span wire:loading wire:target="endungen" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
            </div>

            @error('endungen')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Anmeldeseite') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('Ein Satz unter dem Anmeldeformular — etwa, wer bei Fragen zum Zugang hilft.') }}</p>

        <div>
            <x-input.label for="anmeldeHinweis" :value="__('Hinweis')" />
            <textarea id="anmeldeHinweis" rows="2" maxlength="200"
                wire:model.live.debounce.600ms="anmeldeHinweis"
                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-cerulean-500 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>

            <div class="mt-1 flex flex-wrap items-center gap-2">
                {{-- Ausgegeben wird der Text escaped. Die Anmeldeseite ist die
                     eine Seite, die jeder erreicht - auch ohne Zugang. --}}
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Höchstens 200 Zeichen, reiner Text. Leer lassen heißt: kein Hinweis.') }}
                </p>
                <span wire:loading wire:target="anmeldeHinweis" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
            </div>

            @error('anmeldeHinweis')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Listen') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('Wie viele Zeilen eine Seite zeigt, bevor geblättert wird.') }}</p>

        <div class="space-y-6">
            @foreach ([
                ['seiteListe', __('In den Listen eines Kunden'),
                    __('Geräte, Dateien, Netzwerke, Racks und Patchfelder.')],
                ['seiteAdmin', __('Im Adminbereich'),
                    __('Benutzer, Rollen, Kunden und die Auswahlmenüs. Kürzer, weil diese Listen schmaler sind.')],
            ] as [$feld, $label, $wirkung])
                <div wire:key="seite-{{ $feld }}">
                    <x-input.label for="{{ $feld }}" :value="$label" />

                    <div class="mt-1 flex items-center gap-2">
                        <x-input.field id="{{ $feld }}" type="number" min="5" max="200"
                            wire:model.live.debounce.600ms="{{ $feld }}" class="w-32" />
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Zeilen') }}</span>
                        <span wire:loading wire:target="{{ $feld }}" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
                    </div>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $wirkung }}</p>

                    @error($feld)
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Das Protokoll bleibt bei 50 Zeilen: Dort sucht man nach einem Vorgang und überfliegt, statt zu lesen.') }}
        </p>
    </div>

</div>

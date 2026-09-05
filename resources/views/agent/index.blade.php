@use('App\Support\Zeit')
<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">

        <div class="p-5 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-2xl font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Auto-Dokumentation') }}</div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {{ __('Erzeuge einen Agent-Token und lade das passende Script herunter – für Proxmox, Hyper-V, VMware, Windows-Server und -Arbeitsplatzrechner, Active Directory, UniFi oder Microsoft 365. Einmal ausgeführt, dokumentiert sich die Umgebung selbst. Der Token ist an den gewählten Standort gebunden und darf ausschließlich Dokumentationsdaten melden – kein weiterer Zugriff.') }}
            </p>
        </div>

        {{-- Frisch erzeugter Token + Scripts (nur einmalig sichtbar).

             Ein Block fuer alle Agenten, gespeist aus config('custom.agenten').
             Vorher stand hier je Agent ein eigener, fast gleicher Block von
             rund 45 Zeilen - bei acht Agenten waeren das 360 Zeilen Kopie. --}}
        @if (session('newToken'))
            {{-- Blockform, nicht @php(...): Blade sucht den Rohblock von @php
                 bis zum naechsten @endphp. Die Kurzform hier haette den
                 @endphp der Schleife weiter unten gekapert - alles
                 dazwischen waere rohes PHP geworden. --}}
            @php
                $agenten = config('custom.agenten', []);
            @endphp
            <div x-data="{ tab: @js(array_key_first($agenten)) }" class="p-5 rounded-xl border border-green-300 bg-green-50 shadow-sm dark:bg-gray-800 dark:border-green-800">
                <div class="text-lg font-CoconPro text-green-800 dark:text-green-300">
                    Token „{{ session('newTokenName') }}" erstellt
                </div>
                <p class="mt-1 text-sm text-green-800 dark:text-green-300">
                    {{ __('Dieser Token wird') }} <strong>nur jetzt</strong> angezeigt. Lade das passende Script herunter oder
                    kopiere es – der Token ist darin bereits eingetragen.
                </p>

                <div class="mt-3">
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Token') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="flex-1 break-all rounded-lg bg-white px-3 py-2 text-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">{{ session('newToken') }}</code>
                    </div>
                </div>

                {{-- Script-Umschalter. flex-wrap: acht Reiter passen nicht mehr
                     in eine Zeile, ohne sie waeren die letzten abgeschnitten. --}}
                <div class="mt-4 flex flex-wrap gap-1 border-b border-green-200 dark:border-green-800">
                    @foreach ($agenten as $schluessel => $agent)
                        <button type="button" @click="tab = @js($schluessel)"
                            :class="tab === @js($schluessel) ? 'border-cerulean-600 text-cerulean-700 dark:text-cerulean-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                            class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                            {{ __($agent['name']) }}
                        </button>
                    @endforeach
                </div>

                @foreach ($agenten as $schluessel => $agent)
                    <div x-show="tab === @js($schluessel)" x-cloak class="mt-4" x-data="{ variante: 0 }">

                        {{-- Was das Script tut, gilt fuer alle Varianten: die
                             Bash- und die PowerShell-Fassung melden dasselbe an
                             denselben Endpunkt. Deshalb steht der Kasten ueber
                             der Variantenwahl und nicht darin. --}}
                        <div class="mb-3 rounded-lg border border-cerulean-100 bg-cerulean-50/60 p-3 dark:border-cerulean-900/60 dark:bg-cerulean-950/20">
                            <div class="text-xs font-semibold uppercase tracking-wide text-cerulean-700 dark:text-cerulean-300">{{ __('Was macht das Script?') }}</div>
                            <ul class="mt-1.5 list-inside list-disc space-y-1 text-xs text-cerulean-900 dark:text-cerulean-200">
                                @foreach ($agent['macht'] as $punkt)
                                    <li>{{ __($punkt) }}</li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Nur zeigen, wo es etwas zu waehlen gibt. Die Agenten,
                             die auf dem Geraet selbst laufen, haben genau eine
                             Fassung - ein Umschalter mit einem Knopf waere Zierrat. --}}
                        @if (count($agent['varianten']) > 1)
                            <div class="mb-3 inline-flex rounded-lg border border-gray-200 p-0.5 dark:border-gray-600" role="tablist">
                                @foreach ($agent['varianten'] as $i => $fassung)
                                    <button type="button" @click="variante = @js($i)" role="tab"
                                        :class="variante === @js($i) ? 'bg-cerulean-500 text-white' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'"
                                        class="rounded-md px-3 py-1.5 text-sm transition-colors">
                                        {{ $fassung['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @foreach ($agent['varianten'] as $i => $fassung)
                            @php
                                // Bash-Scripts bekommen den passenden Typ mit, damit der
                                // Download nicht als .txt beim Nutzer landet.
                                $typ = str_ends_with($fassung['datei'], '.sh') ? 'text/x-shellscript' : 'text/plain';
                                $endung = pathinfo($fassung['datei'], PATHINFO_EXTENSION);
                            @endphp
                            <div x-show="variante === @js($i)" x-data="{ copied: false }">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __(':name-Script', ['name' => __($agent['name'])]) }} ({{ $fassung['datei'] }})</label>
                                    <div class="flex gap-2">
                                        <button type="button"
                                            @click="copyText($refs.skript.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="text-sm px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">
                                            <span x-show="!copied">{{ __('Kopieren') }}</span>
                                            <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400">{{ __('Kopiert ✓') }}</span>
                                        </button>
                                        <button type="button"
                                            @click="const blob = new Blob([$refs.skript.textContent], {type:'{{ $typ }}'}); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = '{{ $fassung['datei'] }}'; a.click();"
                                            class="text-sm px-3 py-1.5 rounded-lg bg-cerulean-600 text-white hover:bg-cerulean-700">
                                            {{ __('Download') }} .{{ $endung }}
                                        </button>
                                    </div>
                                </div>
                                <pre x-ref="skript" class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100 leading-relaxed">{{ session('agentSkripte')[$schluessel][$i] ?? '' }}</pre>
                                <div class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <p>{{ __($fassung['ausfuehren_auf']) }} <code class="break-all">{{ $fassung['aufruf'] }}</code></p>
                                    <p>{{ __('Ziel-URL im Script:') }} <code class="break-all">{{ url('/api/agent/'.$agent['endpunkt']) }}</code></p>
                                    <p>
                                        {{ __($agent['erreichbar_von']) }}
                                        <code class="break-all">{{ $fassung['ueberschreiben'] }}</code>
                                    </p>
                                    @if ($agent['zugangsdaten'])
                                        <p class="text-amber-600 dark:text-amber-400">
                                            {{ __('Dieses Script fragt ein fremdes System ab. Dessen Zugangsdaten gibst du beim Aufruf mit – sie werden nicht in DokuVault gespeichert. Ein Konto mit reinen Leserechten genügt.') }}
                                        </p>
                                    @endif
                                    @if (\Illuminate\Support\Str::contains(url('/'), ['.test', 'localhost', '127.0.0.1']))
                                        <p class="text-amber-600 dark:text-amber-400">
                                            ⚠ Die App-Adresse (APP_URL) sieht nach einer lokalen Entwicklungsumgebung aus
                                            ({{ url('/') }}). Erzeuge den Token auf der produktiven Instanz oder überschreibe die URL beim Aufruf.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Neuen Token erzeugen --}}
        <div class="p-5 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-3">{{ __('Neuen Token erzeugen') }}</div>
            @if ($sites->isEmpty())
                <p class="text-sm text-amber-600 dark:text-amber-400">{{ __('Für diesen Kunden ist noch kein Standort angelegt. Bitte zuerst einen Standort anlegen.') }}</p>
            @else
                <form method="POST" action="{{ route('agent.store', $customer) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex flex-col">
                        <x-input.label :value="__('Bezeichnung')" />
                        <x-input.text name="name" class="mt-1 w-56" :placeholder="__('z. B. Proxmox Rechenzentrum')" />
                    </div>
                    <div class="flex flex-col">
                        <x-input.label :value="__('Standort')" />
                        <x-input.select name="site_id" class="mt-1">
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </x-input.select>
                    </div>
                    <x-input.button :label="__('Token erzeugen')" />
                </form>
            @endif
        </div>

        {{-- Bestehende Token --}}
        <div class="p-5 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-3">{{ __('Aktive Token') }}</div>
            @forelse ($tokens as $token)
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 dark:border-gray-700">
                    <div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ $token->name ?: 'Token #'.$token->id }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Standort: {{ $token->site?->name ?? '—' }} ·
                            Zuletzt genutzt: {{ Zeit::anzeigen($token->last_used_at, 'd.m.Y H:i', __('noch nie')) }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('agent.destroy', [$customer, $token]) }}"
                        onsubmit="return confirm('Token wirklich widerrufen? Geräte mit diesem Token können sich dann nicht mehr dokumentieren.')">
                        @csrf
                        @method('delete')
                        <x-input.button color="red" size="sm" :label="__('Widerrufen')" />
                    </form>
                </div>
            @empty
                <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Token erzeugt.') }}</div>
            @endforelse
        </div>

    </div>
</x-app-layout>

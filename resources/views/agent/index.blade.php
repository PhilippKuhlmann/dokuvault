@use('App\Support\Zeit')
<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">

        <x-panel>
            <div class="text-2xl font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Auto-Dokumentation') }}</div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {{ __('Erzeuge einen Agent-Token und lade das passende Script herunter – für Proxmox, Hyper-V, VMware, Windows-Server und -Arbeitsplatzrechner, Active Directory, UniFi oder Microsoft 365. Einmal ausgeführt, dokumentiert sich die Umgebung selbst. Der Token ist an den gewählten Standort gebunden und darf ausschließlich Dokumentationsdaten melden – kein weiterer Zugriff.') }}
            </p>
        </x-panel>

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
                $dienste = config('custom.dienste', []);
            @endphp
            {{-- art: agent (install once, reports by itself) or script (run by
                 hand). One of the two is shown - both at once was a wall of
                 text, and more of each are coming. --}}
            <div x-data="{ art: 'agent', tab: @js(array_key_first($agenten)), dienst: @js(array_key_first($dienste)) }" class="p-5 rounded-xl border border-green-300 bg-green-50 shadow-xs dark:bg-gray-800 dark:border-green-800">
                <div class="text-lg font-CoconPro text-green-800 dark:text-green-300">
                    {{ __('Token „:name" erstellt', ['name' => session('newTokenName')]) }}
                </div>
                <p class="mt-1 text-sm text-green-800 dark:text-green-300">
                    {{-- Drei Teile statt eines Satzes mit HTML darin: {!! !!} waere der
                         einzige Weg, das <strong> aus der Uebersetzung kommen zu lassen -
                         und rohes HTML aus einer Sprachdatei will hier niemand. --}}
                    {{ __('Dieser Token wird') }} <strong>{{ __('nur jetzt') }}</strong>
                    {{ __('angezeigt. Installiere einen Agenten oder lade ein Script herunter – der Token ist jeweils schon eingetragen.') }}
                </p>

                <div class="mt-3">
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Agent-Token') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="flex-1 break-all rounded-lg bg-white px-3 py-2 text-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">{{ session('newToken') }}</code>
                    </div>
                </div>

                {{-- Agent or script. --}}
                <div class="mt-4 grid gap-2 sm:grid-cols-2" role="tablist">
                    <button type="button" role="tab" @click="art = 'agent'"
                        :class="art === 'agent' ? 'border-cerulean-500 bg-white ring-1 ring-cerulean-500 dark:bg-gray-900' : 'border-gray-200 bg-white/60 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900/40'"
                        class="rounded-lg border p-3 text-left transition-colors">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Agent') }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Einmal installieren – meldet danach automatisch.') }}</div>
                    </button>
                    <button type="button" role="tab" @click="art = 'script'"
                        :class="art === 'script' ? 'border-cerulean-500 bg-white ring-1 ring-cerulean-500 dark:bg-gray-900' : 'border-gray-200 bg-white/60 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900/40'"
                        class="rounded-lg border p-3 text-left transition-colors">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Script') }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Einmal von Hand ausführen – auch für Proxmox, VMware, UniFi, Microsoft 365.') }}</div>
                    </button>
                </div>

                {{-- Agents from config('custom.dienste'): one tab each, only
                     shown once there is more than one. --}}
                <div x-show="art === 'agent'" class="mt-4">
                    @if (count($dienste) > 1)
                        <div class="mb-3 flex flex-wrap gap-1 border-b border-green-200 dark:border-green-800">
                            @foreach ($dienste as $schluessel => $dienst)
                                <button type="button" @click="dienst = @js($schluessel)"
                                    :class="dienst === @js($schluessel) ? 'border-cerulean-600 text-cerulean-700 dark:text-cerulean-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                                    class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                                    {{ __($dienst['name']) }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @foreach ($dienste as $schluessel => $dienst)
                        <div x-show="dienst === @js($schluessel)" class="rounded-lg border border-cerulean-200 bg-white p-4 text-sm dark:border-cerulean-800 dark:bg-gray-900">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ __('Agent für :name', ['name' => __($dienst['name'])]) }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __($dienst['kurz']) }}</div>
                                </div>
                                {{-- URL and token are embedded in this download; only
                                     offered right after creating a token. --}}
                                <a href="{{ route($dienst['download'], $customer) }}"
                                    class="text-sm px-3 py-1.5 rounded-lg bg-cerulean-600 text-white hover:bg-cerulean-700">
                                    {{ __('Download') }} {{ $dienst['datei'] }}
                                </a>
                            </div>
                            <p class="mt-2 text-gray-600 dark:text-gray-300">{{ __($dienst['beschreibung']) }}</p>

                            {{-- The command line is the exception (distribution via
                                 script/GPO) - folded away like the scripts. --}}
                            <details class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                <summary class="cursor-pointer select-none">{{ __('Per Kommandozeile installieren') }}</summary>
                                <code class="mt-2 block break-all rounded bg-gray-100 px-3 py-2 text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ strtr($dienst['befehl'], [':url' => url('/'), ':token' => session('newToken')]) }}</code>
                                <ul class="mt-2 list-disc space-y-0.5 pl-5">
                                    @foreach ($dienst['hinweise'] as $hinweis)
                                        <li>{{ __($hinweis) }}</li>
                                    @endforeach
                                </ul>
                            </details>

                            <div class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <div class="mb-1 font-semibold uppercase tracking-wide">{{ __('Deinstallieren') }}</div>
                                <ul class="list-disc space-y-0.5 pl-5">
                                    @foreach ($dienst['deinstallieren'] as $schritt)
                                        <li>{{ __($schritt) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div x-show="art === 'script'" x-cloak>
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
                            {{-- Same card as the agent above: title left, actions top
                                 right, explanation below - the buttons sat at the
                                 bottom here before, below a long text box. --}}
                            <div x-show="variante === @js($i)" x-data="{ copied: false }" class="rounded-lg border border-cerulean-200 bg-white p-4 text-sm dark:border-cerulean-800 dark:bg-gray-900">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ __(':name-Script', ['name' => __($agent['name'])]) }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $fassung['datei'] }}</div>
                                    </div>
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
                                {{-- What the script does is the same for every variant
                                     (bash and PowerShell report the same to the same
                                     endpoint); repeated per variant so it sits in the
                                     card under the actions. --}}
                                <div class="mt-3 rounded-lg border border-cerulean-100 bg-cerulean-50/60 p-3 dark:border-cerulean-900/60 dark:bg-cerulean-950/20">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-cerulean-700 dark:text-cerulean-300">{{ __('Was macht das Script?') }}</div>
                                    <ul class="mt-1.5 list-inside list-disc space-y-1 text-xs text-cerulean-900 dark:text-cerulean-200">
                                        @foreach ($agent['macht'] as $punkt)
                                            <li>{{ __($punkt) }}</li>
                                        @endforeach
                                    </ul>
                                </div>

                                {{-- Folded: copy and download work without it, and
                                     the text was most of the page. Inside <details>
                                     the <pre> stays in the DOM, so $refs.skript
                                     still has the content. --}}
                                <details class="mt-3 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                    <summary class="cursor-pointer select-none px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Script anzeigen') }}</summary>
                                    <pre x-ref="skript" class="overflow-x-auto rounded-b-lg bg-gray-900 p-4 text-xs text-gray-100 leading-relaxed">{{ session('agentSkripte')[$schluessel][$i] ?? '' }}</pre>
                                </details>
                                <div class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{-- A downloaded .ps1 carries the "from the internet" mark,
                                         and with the usual RemoteSigned policy Windows refuses
                                         to run it unsigned. Unblock-File only removes that mark
                                         from this one file - the policy stays as it is. --}}
                                    @if (str_ends_with($fassung['datei'], '.ps1'))
                                        <p>{{ __('Nach dem Download einmal freigeben:') }} <code class="break-all">Unblock-File .\{{ $fassung['datei'] }}</code></p>
                                    @endif
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
            </div>
        @endif

        @if (session('success'))
            <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-gray-800 dark:text-green-300">{{ session('success') }}</div>
        @endif

        {{-- Installed agents: each reports in before every run and gets the
             roles ticked here. Defaults follow the detection, except AD -
             only one DC per domain gets it, the domain is the same from
             every DC. --}}
        <x-panel>
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">{{ __('Installierte Agenten') }}</div>
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
                {{ __('Was jeder Agent meldet und wie oft, legst du hier fest – gilt ab seinem nächsten Lauf. „erkannt“ heißt: der Rechner hat die Rolle. Active Directory reicht von einem Domänencontroller je Domäne.') }}
            </p>

            @forelse ($installations as $installation)
                @php($rollen = \App\Models\AgentInstallation::availableRoles($installation->kind))
                <div class="flex flex-wrap items-start justify-between gap-3 py-3 border-b border-gray-100 last:border-0 dark:border-gray-700">
                    <div class="min-w-48">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $installation->hostname }}
                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __(config('custom.dienste.'.$installation->kind.'.name', $installation->kind)) }}</span>
                            @if ($installation->isStale())
                                <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('meldet nicht') }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($installation->domain)
                                {{ $installation->domain }} ·
                            @endif
                            {{ __('Letzter Lauf') }}: {{ Zeit::anzeigen($installation->last_run_at, 'd.m.Y H:i', __('noch nie')) }} ·
                            {{ __('Zuletzt erreichbar') }}: {{ Zeit::anzeigen($installation->last_seen_at, 'd.m.Y H:i', __('noch nie')) }} ·
                            {{ __('Version') }}: {{ $installation->version ?? '—' }} ·
                            {{ __('Token') }}: {{ $installation->agentToken ? ($installation->agentToken->name ?: 'Token #'.$installation->agentToken->id) : __('widerrufen') }}
                        </div>
                        {{-- Failed roles with the end of their output - visible
                             without hovering, the full text folded. --}}
                        @foreach ($installation->failedRoles() as $rolle)
                            @php($ergebnis = $installation->resultFor($rolle))
                            <details class="mt-1 max-w-xl text-xs text-red-700 dark:text-red-400">
                                <summary class="cursor-pointer select-none">
                                    {{ __(':rolle fehlgeschlagen', ['rolle' => __($rollen[$rolle] ?? $rolle)]) }}
                                    · {{ \Illuminate\Support\Carbon::parse($ergebnis['at'])->format('d.m.Y H:i') }}
                                </summary>
                                <pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap rounded bg-red-50 p-2 text-[11px] text-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $ergebnis['message'] ?? __('Keine Ausgabe.') }}</pre>
                            </details>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <form method="POST" action="{{ route('agent.installation.update', [$customer, $installation]) }}" class="flex flex-wrap items-center gap-3">
                            @csrf
                            @method('PUT')
                            @foreach ($rollen as $rolle => $bezeichnung)
                                <label class="flex cursor-pointer select-none items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" name="roles[]" value="{{ $rolle }}" @checked(in_array($rolle, $installation->roles ?? [], true))
                                        class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                                    {{ __($bezeichnung) }}
                                    {{-- Outcome of the last run of this role. --}}
                                    @if ($ergebnis = $installation->resultFor($rolle))
                                        <span @class([
                                                'inline-block h-2 w-2 rounded-full',
                                                'bg-green-500' => $ergebnis['ok'],
                                                'bg-red-500' => ! $ergebnis['ok'],
                                            ])
                                            title="{{ ($ergebnis['ok'] ? __('Letzter Lauf erfolgreich') : __('Letzter Lauf fehlgeschlagen')).' · '.\Illuminate\Support\Carbon::parse($ergebnis['at'])->format('d.m.Y H:i') }}"></span>
                                    @endif
                                    @if (in_array($rolle, $installation->detected ?? [], true))
                                        <span class="text-[10px] text-green-700 dark:text-green-400">{{ __('erkannt') }}</span>
                                    @endif
                                </label>
                            @endforeach
                            {{-- One height for all controls of the row (h-9): the select came in
                                 its form size, the buttons in their small one. --}}
                            <x-input.select name="interval_minutes" class="w-40 h-9 px-3 py-1 text-sm" :aria-label="__('Intervall')">
                                @foreach (config('custom.agent_intervalle') as $minuten => $bezeichnung)
                                    <option value="{{ $minuten }}" @selected($minuten === $installation->intervalMinutes())>{{ __($bezeichnung) }}</option>
                                @endforeach
                            </x-input.select>
                            <x-input.button type="submit" class="h-9" :label="__('Speichern')" />
                        </form>
                        {{-- Picked up on the next checkin, within five minutes. --}}
                        <form method="POST" action="{{ route('agent.installation.run', [$customer, $installation]) }}">
                            @csrf
                            @if ($installation->run_requested_at)
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lauf angefordert') }}</span>
                            @else
                                <x-input.button type="submit" color="gray" class="h-9" :label="__('Jetzt melden')" />
                            @endif
                        </form>
                        <x-loeschdialog :url="route('agent.installation.destroy', [$customer, $installation])"
                            :frage="__('Agent aus der Liste entfernen?')"
                            :hinweis="__('Nur der Eintrag verschwindet. Ist der Agent noch installiert, meldet er sich beim nächsten Lauf wieder – deinstalliert wird auf dem Rechner.')"
                            :bestaetigen="__('Entfernen')">
                            <x-slot:ausloeser>
                                <x-input.button type="button" color="red" class="h-9"
                                    x-on:click="offen = true" :label="__('Entfernen')" />
                            </x-slot:ausloeser>
                        </x-loeschdialog>
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Noch kein Agent installiert. Agenten erscheinen hier nach ihrer ersten Meldung.') }}</div>
            @endforelse
        </x-panel>

        {{-- Welche Agenten es gibt.

             Steht ueber dem Formular, weil dort die Frage aufkommt: Wer einen
             Namen und einen Standort vergeben soll, muss vorher wissen, was
             ueberhaupt zur Auswahl steht. Vorher sah man das erst nach dem
             Anlegen des Tokens - also eine Entscheidung zu spaet. --}}
        <x-panel>
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">{{ __('Agenten und Scripte') }}</div>
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
                {{ __('Ein Token gilt für alle – nach dem Anlegen steht jeder Agent und jedes Script zum Herunterladen bereit. Der Name ist nur für dich, damit du den Token später wiedererkennst.') }}
            </p>

            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Agenten – einmal installieren, melden automatisch') }}</div>
            <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (config('custom.dienste', []) as $dienst)
                    <div class="rounded-lg border border-cerulean-200 p-3 dark:border-cerulean-800">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Agent für :name', ['name' => __($dienst['name'])]) }}</div>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __($dienst['kurz']) }}</div>
                        {{-- Always visible here: the card after creating a token is
                             gone by the time someone wants to remove the agent. --}}
                        <details class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <summary class="cursor-pointer select-none">{{ __('Deinstallieren') }}</summary>
                            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                @foreach ($dienst['deinstallieren'] as $schritt)
                                    <li>{{ __($schritt) }}</li>
                                @endforeach
                            </ul>
                        </details>
                    </div>
                @endforeach
            </div>

            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Scripte – einmal von Hand ausführen') }}</div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (config('custom.agenten', []) as $agent)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __($agent['name']) }}</div>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __($agent['kurz']) }}</div>
                        <div class="mt-2 flex flex-wrap items-center gap-1">
                            @foreach ($agent['varianten'] as $fassung)
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-mono text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $fassung['name'] }}</span>
                            @endforeach
                            {{-- Vor dem Anlegen wissen, wofuer man noch etwas
                                 besorgen muss: vCenter, UniFi und Graph wollen
                                 ein eigenes Lesekonto. --}}
                            @if ($agent['zugangsdaten'])
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ __('Zugangsdaten nötig') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- Neuen Token erzeugen --}}
        <x-panel>
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">{{ __('Neuen Token erzeugen') }}</div>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Mit dem Token melden Agenten und Scripte Daten an diesen Kunden – nur an den gewählten Standort, und nur bis zum Ablaufdatum. Nach dem Erzeugen kannst du Agenten und Scripte mit eingesetztem Token herunterladen; den Token selbst siehst du nur einmal.') }}
            </p>
            @if ($sites->isEmpty())
                <p class="text-sm text-amber-600 dark:text-amber-400">{{ __('Für diesen Kunden ist noch kein Standort angelegt. Bitte zuerst einen Standort anlegen.') }}</p>
            @else
                {{-- Same bar as the list filters: equal columns, equal field
                     heights (x-input.field / x-input.select), the button level
                     with the fields. Before, three different input components
                     stood side by side in three heights and two backgrounds. --}}
                <form method="POST" action="{{ route('agent.store', $customer) }}" class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <div class="min-w-0">
                        <x-input.label for="token-name" :value="__('Bezeichnung')" />
                        <x-input.field id="token-name" name="name" class="mt-1 w-full" :value="old('name')" :placeholder="__('z. B. Proxmox Rechenzentrum')" />
                    </div>
                    <div class="min-w-0">
                        <x-input.label :value="__('Standort')" />
                        <x-input.select name="site_id" class="mt-1 w-full">
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </x-input.select>
                    </div>
                    {{-- Pflicht und vorbelegt: Ein Token ohne Ablauf ist ein
                         Dauerzugang. Die Vorgabe kommt aus der Konfiguration. --}}
                    <div class="min-w-0">
                        <x-input.label for="token-ablauf" :value="__('Läuft ab am')" />
                        <x-input.field id="token-ablauf" type="date" name="expires_at" class="mt-1 w-full dark:scheme-dark"
                            min="{{ now()->addDay()->format('Y-m-d') }}"
                            :value="old('expires_at', now()->addDays(config('custom.agenten_token.gueltigkeit_tage_standard'))->format('Y-m-d'))" />
                    </div>
                    <x-input.button size="feld" class="w-full justify-center" :label="__('Token erzeugen')" />
                    <x-input.fehler feld="expires_at" class="sm:col-span-2 lg:col-span-4" />
                </form>
            @endif
        </x-panel>

        {{-- Bestehende Token --}}
        <x-panel>
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-3">{{ __('Aktive Token') }}</div>
            @forelse ($tokens as $token)
                <div class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0 dark:border-gray-700">
                    <div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            {{ $token->name ?: 'Token #'.$token->id }}
                            {{-- Sofort sichtbar, welcher Token tot ist und welcher
                                 noch aus der Zeit ohne Ablauf stammt. --}}
                            @if ($token->istAbgelaufen())
                                <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('abgelaufen') }}</span>
                            @elseif ($token->expires_at === null)
                                <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ __('unbegrenzt (Altbestand)') }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Standort') }}: {{ $token->site?->name ?? '—' }} ·
                            {{ __('Zuletzt genutzt') }}: {{ Zeit::anzeigen($token->last_used_at, 'd.m.Y H:i', __('noch nie')) }} ·
                            {{ __('Läuft ab') }}: {{ Zeit::anzeigen($token->expires_at, 'd.m.Y', __('unbegrenzt')) }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Erneuern: neuer Klartext, alter sofort ungültig -
                             der Weg, einen verbrannten oder ablaufenden Token zu
                             wechseln, ohne Kunde und Standort neu einzurichten. --}}
                        <form method="POST" action="{{ route('agent.erneuern', [$customer, $token]) }}">
                            @csrf
                            <x-input.button type="submit" size="sm" :label="__('Erneuern')" />
                        </form>
                        {{-- Der Satz lief bisher nicht durch __() und stand fest auf
                             Deutsch - aufgefallen beim Ersetzen des confirm(). --}}
                        <x-loeschdialog :url="route('agent.destroy', [$customer, $token])"
                            :frage="__('Token wirklich widerrufen?')"
                            :hinweis="__('Geräte mit diesem Token können sich dann nicht mehr dokumentieren.')"
                            :bestaetigen="__('Widerrufen')">
                            <x-slot:ausloeser>
                                <x-input.button type="button" color="red" size="sm"
                                    x-on:click="offen = true" :label="__('Widerrufen')" />
                            </x-slot:ausloeser>
                        </x-loeschdialog>
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Token erzeugt.') }}</div>
            @endforelse
        </x-panel>

    </div>
</x-app-layout>

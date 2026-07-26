<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">

        <div class="p-5 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-2xl font-CoconPro text-chathams-blue-800 dark:text-gray-100">Auto-Dokumentation</div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Erzeuge einen Agent-Token und lade das passende Script herunter – für Proxmox-Hosts oder
                Windows Active Directory. Auf dem Gerät ausgeführt, dokumentiert es sich selbst. Der Token ist an den
                gewählten Standort gebunden und darf ausschließlich Dokumentationsdaten melden – kein weiterer Zugriff.
            </p>
        </div>

        {{-- Frisch erzeugter Token + Scripts (nur einmalig sichtbar) --}}
        @if (session('newToken'))
            <div x-data="{ tab: 'proxmox' }" class="p-5 rounded-xl border border-green-300 bg-green-50 shadow-sm dark:bg-gray-800 dark:border-green-800">
                <div class="text-lg font-CoconPro text-green-800 dark:text-green-300">
                    Token „{{ session('newTokenName') }}" erstellt
                </div>
                <p class="mt-1 text-sm text-green-800 dark:text-green-300">
                    Dieser Token wird <strong>nur jetzt</strong> angezeigt. Lade das passende Script herunter oder
                    kopiere es – der Token ist darin bereits eingetragen.
                </p>

                <div class="mt-3">
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Token</label>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="flex-1 break-all rounded-lg bg-white px-3 py-2 text-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">{{ session('newToken') }}</code>
                    </div>
                </div>

                {{-- Script-Umschalter --}}
                <div class="mt-4 flex gap-1 border-b border-green-200 dark:border-green-800">
                    <button type="button" @click="tab = 'proxmox'"
                        :class="tab === 'proxmox' ? 'border-cerulean-600 text-cerulean-700 dark:text-cerulean-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                        class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                        Proxmox
                    </button>
                    <button type="button" @click="tab = 'windowsAd'"
                        :class="tab === 'windowsAd' ? 'border-cerulean-600 text-cerulean-700 dark:text-cerulean-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                        class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                        Windows AD
                    </button>
                </div>

                {{-- Proxmox-Script --}}
                <div x-show="tab === 'proxmox'" x-cloak class="mt-4" x-data="{ copied: false }">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Proxmox-Script (proxmox-doku.sh)</label>
                        <div class="flex gap-2">
                            <button type="button"
                                @click="copyText($refs.proxmoxScript.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                                class="text-sm px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">
                                <span x-show="!copied">Kopieren</span>
                                <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400">Kopiert ✓</span>
                            </button>
                            <button type="button"
                                @click="const blob = new Blob([$refs.proxmoxScript.textContent], {type:'text/x-shellscript'}); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'proxmox-doku.sh'; a.click();"
                                class="text-sm px-3 py-1.5 rounded-lg bg-cerulean-600 text-white hover:bg-cerulean-700">
                                Download .sh
                            </button>
                        </div>
                    </div>
                    <pre x-ref="proxmoxScript" class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100 leading-relaxed">{{ session('proxmoxScript') }}</pre>
                    <div class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <p>Ausführen auf dem Proxmox-Host (als root): <code>bash proxmox-doku.sh</code></p>
                        <p>Ziel-URL im Script: <code class="break-all">{{ url('/api/agent/proxmox') }}</code></p>
                        <p>
                            Diese URL muss vom Gerät aus erreichbar sein. Falls nicht, beim Aufruf überschreiben:
                            <code class="break-all">bash proxmox-doku.sh https://euer-server/api/agent/proxmox</code>
                        </p>
                        @if (\Illuminate\Support\Str::contains(url('/'), ['.test', 'localhost', '127.0.0.1']))
                            <p class="text-amber-600 dark:text-amber-400">
                                ⚠ Die App-Adresse (APP_URL) sieht nach einer lokalen Entwicklungsumgebung aus
                                ({{ url('/') }}). Erzeuge den Token auf der produktiven Instanz oder überschreibe die URL beim Aufruf.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Windows-AD-Script --}}
                <div x-show="tab === 'windowsAd'" x-cloak class="mt-4" x-data="{ copied: false }">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Windows-AD-Script (windows-ad-doku.ps1)</label>
                        <div class="flex gap-2">
                            <button type="button"
                                @click="copyText($refs.windowsAdScript.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                                class="text-sm px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">
                                <span x-show="!copied">Kopieren</span>
                                <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400">Kopiert ✓</span>
                            </button>
                            <button type="button"
                                @click="const blob = new Blob([$refs.windowsAdScript.textContent], {type:'text/plain'}); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'windows-ad-doku.ps1'; a.click();"
                                class="text-sm px-3 py-1.5 rounded-lg bg-cerulean-600 text-white hover:bg-cerulean-700">
                                Download .ps1
                            </button>
                        </div>
                    </div>
                    <pre x-ref="windowsAdScript" class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100 leading-relaxed">{{ session('windowsAdScript') }}</pre>
                    <div class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <p>Ausführen auf einem Domaincontroller bzw. Rechner mit RSAT-AD-Modul: <code>.\windows-ad-doku.ps1</code></p>
                        <p>Ziel-URL im Script: <code class="break-all">{{ url('/api/agent/windows-ad') }}</code></p>
                        <p>
                            Diese URL muss vom DC aus erreichbar sein. Falls nicht, beim Aufruf überschreiben:
                            <code class="break-all">.\windows-ad-doku.ps1 -ApiUrl "https://euer-server/api/agent/windows-ad"</code>
                        </p>
                        <p>
                            Es werden nur „echte" Benutzer (inkl. Administrator, ohne Gast/krbtgt) und selbst
                            angelegte Gruppen gemeldet – Standard-/Built-in-Gruppen werden ausgelassen. Passwörter
                            werden nie übertragen.
                        </p>
                        @if (\Illuminate\Support\Str::contains(url('/'), ['.test', 'localhost', '127.0.0.1']))
                            <p class="text-amber-600 dark:text-amber-400">
                                ⚠ Die App-Adresse (APP_URL) sieht nach einer lokalen Entwicklungsumgebung aus
                                ({{ url('/') }}). Erzeuge den Token auf der produktiven Instanz oder überschreibe die URL beim Aufruf.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Neuen Token erzeugen --}}
        <div class="p-5 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-3">Neuen Token erzeugen</div>
            @if ($sites->isEmpty())
                <p class="text-sm text-amber-600 dark:text-amber-400">Für diesen Kunden ist noch kein Standort angelegt. Bitte zuerst einen Standort anlegen.</p>
            @else
                <form method="POST" action="{{ route('agent.store', $customer) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex flex-col">
                        <x-input.label value="Bezeichnung" />
                        <x-input.text name="name" class="mt-1 w-56" placeholder="z. B. Proxmox Rechenzentrum" />
                    </div>
                    <div class="flex flex-col">
                        <x-input.label value="Standort" />
                        <x-input.select name="site_id" class="mt-1">
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </x-input.select>
                    </div>
                    <x-input.button label="Token erzeugen" />
                </form>
            @endif
        </div>

        {{-- Bestehende Token --}}
        <div class="p-5 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-3">Aktive Token</div>
            @forelse ($tokens as $token)
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 dark:border-gray-700">
                    <div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ $token->name ?: 'Token #'.$token->id }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Standort: {{ $token->site?->name ?? '—' }} ·
                            Zuletzt genutzt: {{ $token->last_used_at ? $token->last_used_at->format('d.m.Y H:i') : 'noch nie' }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('agent.destroy', [$customer, $token]) }}"
                        onsubmit="return confirm('Token wirklich widerrufen? Geräte mit diesem Token können sich dann nicht mehr dokumentieren.')">
                        @csrf
                        @method('delete')
                        <x-input.button color="red" size="sm" label="Widerrufen" />
                    </form>
                </div>
            @empty
                <div class="text-sm text-gray-400 dark:text-gray-500">Noch keine Token erzeugt.</div>
            @endforelse
        </div>

    </div>
</x-app-layout>

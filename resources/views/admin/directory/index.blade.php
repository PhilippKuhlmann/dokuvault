<x-admin-layout>
    <div class="p-3 sm:p-5 space-y-6">
        <div>
            <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Anmeldung über Active Directory') }}</div>
            <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                {{ __('Techniker melden sich mit ihrem AD-Konto an, eingeladene Kunden und der lokale Admin weiter mit ihrem DokuVault-Kennwort. Welche Rolle jemand bekommt, entscheidet seine AD-Gruppe – bei jeder Anmeldung neu.') }}
            </p>
        </div>

<x-demo-locked class="space-y-6">
        @if (session('success'))
            <div class="max-w-3xl rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/25 dark:text-green-300" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <x-panel class="max-w-3xl rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/25 dark:text-red-300">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $fehler)
                        <li>{{ $fehler }}</li>
                    @endforeach
                </ul>
            </x-panel>
        @endif

        @unless ($extension)
            {{-- Without it nothing below works - said before anyone fills in the form. --}}
            <div class="max-w-3xl rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/25 dark:text-amber-200">
                {{ __('Auf diesem Server fehlt die PHP-Erweiterung „ldap“. Ohne sie ist keine Anmeldung über das AD möglich. Unter Debian/Ubuntu: apt install php-ldap, danach PHP-FPM neu starten.') }}
            </div>
        @endunless

        @if ($unverschluesselt)
            {{-- Stays as long as the setting does - not a one-time notice. --}}
            <div class="max-w-3xl rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/25 dark:text-red-300" role="alert">
                <div class="font-semibold">{{ __('Die AD-Anmeldung läuft ohne Verschlüsselung.') }}</div>
                <p class="mt-1">{{ __('Bei jeder Anmeldung gehen das Kennwort des Technikers und das des Dienstkontos lesbar durchs Netz – wer mitschneidet, hat echte Domänenkonten. Viele Domänencontroller lehnen das ohnehin ab (LDAP-Signierung). Nur in einem abgeschotteten Netz nutzen; besser STARTTLS oder LDAPS.') }}</p>
            </div>
        @endif

        {{-- Connection --}}
        <form method="POST" action="{{ route('admin.directory.update') }}" class="max-w-3xl space-y-4">
            @csrf
            @method('PATCH')

            <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Verbindung') }}</div>

            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))
                    class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                {{ __('Anmeldung über das Active Directory einschalten') }}
            </label>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input.label for="host" :value="__('Server')" />
                    <x-input.field id="host" name="host" class="mt-1 w-full" value="{{ old('host', $host) }}" placeholder="dc01.firma.local" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ein Domänencontroller, so wie ihn das Zertifikat nennt.') }}</p>
                </div>
                <div>
                    <x-input.label for="port" :value="__('Port')" />
                    <x-input.field id="port" name="port" type="number" class="mt-1 w-full" value="{{ old('port', $port) }}" placeholder="636" />
                </div>

                <div>
                    <x-input.label for="encryption" :value="__('Verschlüsselung')" />
                    <x-input.select id="encryption" name="encryption" class="mt-1 w-full">
                        {{-- "Ohne" last and named for what it is: the password of
                             every technician crosses the network readable. --}}
                        @foreach (['ldaps' => 'LDAPS (636)', 'starttls' => 'STARTTLS (389)', 'none' => __('Ohne Verschlüsselung (389) – nicht empfohlen')] as $wert => $beschriftung)
                            <option value="{{ $wert }}" @selected(old('encryption', $encryption) === $wert)>{{ $beschriftung }}</option>
                        @endforeach
                    </x-input.select>
                </div>
                <div class="sm:col-span-2 flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input type="hidden" name="verify_cert" value="0">
                        <input type="checkbox" name="verify_cert" value="1" @checked(old('verify_cert', $verifyCert))
                            class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                        {{ __('Zertifikat prüfen (nur für ein selbst ausgestelltes Zertifikat abschalten)') }}
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <x-input.label for="base_dn" :value="__('Basis-DN')" />
                    <x-input.field id="base_dn" name="base_dn" class="mt-1 w-full" value="{{ old('base_dn', $baseDn) }}" placeholder="DC=firma,DC=local" />
                </div>
                <div>
                    <x-input.label for="domain" :value="__('Domäne (NetBIOS)')" />
                    <x-input.field id="domain" name="domain" class="mt-1 w-full" value="{{ old('domain', $domain) }}" placeholder="FIRMA" />
                </div>

                <div class="sm:col-span-3">
                    <x-input.label for="bind_dn" :value="__('Dienstkonto')" />
                    <x-input.field id="bind_dn" name="bind_dn" class="mt-1 w-full" value="{{ old('bind_dn', $bindDn) }}"
                        placeholder="CN=svc-dokuvault,OU=Dienste,DC=firma,DC=local" autocomplete="off" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ein Konto, das nur lesen darf. Es sucht Benutzer und ihre Gruppen; Kennwörter prüft der Server mit dem Konto des Benutzers selbst.') }}</p>
                </div>
                <div class="sm:col-span-3">
                    <x-input.label for="bind_password" :value="__('Kennwort des Dienstkontos')" />
                    <x-input.field id="bind_password" name="bind_password" type="password" class="mt-1 w-full" autocomplete="new-password"
                        placeholder="{{ $hatKennwort ? __('unverändert lassen') : '' }}" />
                    @if ($hatKennwort)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ein Kennwort ist hinterlegt. Leer lassen heißt: unverändert.') }}</p>
                    @endif
                </div>
            </div>

            {{-- Only the start value for new directory accounts; afterwards
                 it is set per user in the user admin. --}}
            <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                <input type="hidden" name="require_2fa" value="0">
                <input type="checkbox" name="require_2fa" value="1" @checked(old('require_2fa', $requireTwoFactor))
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                <span>
                    {{ __('Neue AD-Benutzer müssen die zweite Stufe einrichten') }}
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Gilt beim ersten Anmelden. Danach legst du es je Benutzer unter Benutzer › Bearbeiten fest – etwa Pflicht für Admins, nicht für andere. Das AD prüft nur das Kennwort.') }}</span>
                </span>
            </label>

            <div class="flex flex-wrap items-center gap-2">
                <x-input.button type="submit" :label="__('Speichern')" />
            </div>
        </form>

        <div class="flex max-w-3xl flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('admin.directory.test') }}">
                @csrf
                <x-input.button type="submit" color="gray" :label="__('Verbindung testen')" />
            </form>
            @if ($hatKennwort)
                <form method="POST" action="{{ route('admin.directory.password') }}">
                    @csrf
                    @method('DELETE')
                    <x-input.button type="submit" color="gray" :label="__('Kennwort entfernen')" />
                </form>
            @endif
        </div>

        {{-- Group -> role --}}
        <x-panel class="max-w-3xl">
            <div class="mb-1 text-xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Gruppe → Rolle') }}</div>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Von oben nach unten: Die erste Gruppe, in der jemand ist, bestimmt seine Rolle – höhere Rollen also nach oben. Verschachtelte Gruppen zählen mit. Wer in keiner dieser Gruppen ist, kommt nicht hinein.') }}
            </p>

            @if ($zuordnungen->isNotEmpty())
                <div class="mb-4 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    @foreach ($zuordnungen as $zuordnung)
                        <div class="flex flex-wrap items-center gap-3 px-3 py-2 text-sm">
                            <span class="w-6 shrink-0 text-right font-mono text-xs text-gray-400">{{ $loop->iteration }}.</span>
                            {{-- A stored DN reads by its name; the DN stays in the tooltip. --}}
                            @php
                                $anzeige = $gruppenNamen[mb_strtolower($zuordnung->group)]
                                    ?? (preg_match('/^CN=([^,]+)/i', $zuordnung->group, $treffer) ? $treffer[1] : $zuordnung->group);
                            @endphp
                            <span class="min-w-0 flex-1 break-all text-gray-900 dark:text-gray-100" title="{{ $zuordnung->group }}">{{ $anzeige }}</span>
                            <span class="shrink-0 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $zuordnung->role?->name }}</span>
                            {{-- A role above the viewer's ceiling: shown, but not to be moved or removed. --}}
                            @if ($zuordnung->changeable)
                            <div class="flex shrink-0 items-center gap-1">
                                @unless ($loop->first)
                                    <form method="POST" action="{{ route('admin.directory.groups.move', [$zuordnung, 'up']) }}">
                                        @csrf
                                        <button type="submit" class="rounded px-1.5 py-0.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="{{ __('Nach oben') }}" aria-label="{{ __('Nach oben') }}">↑</button>
                                    </form>
                                @endunless
                                @unless ($loop->last)
                                    <form method="POST" action="{{ route('admin.directory.groups.move', [$zuordnung, 'down']) }}">
                                        @csrf
                                        <button type="submit" class="rounded px-1.5 py-0.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="{{ __('Nach unten') }}" aria-label="{{ __('Nach unten') }}">↓</button>
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.directory.groups.destroy', $zuordnung) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded px-1.5 py-0.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" title="{{ __('Entfernen') }}" aria-label="{{ __('Entfernen') }}">✕</button>
                                </form>
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($gruppenFehler)
                <p class="mb-3 text-xs text-amber-700 dark:text-amber-400">{{ __('Die Gruppen ließen sich nicht laden (:grund). Den Namen bitte von Hand eintragen.', ['grund' => $gruppenFehler]) }}</p>
            @endif

            <form method="POST" action="{{ route('admin.directory.groups.store') }}" class="space-y-2">
                @csrf
                <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <x-input.label for="group" :value="__('AD-Gruppe')" />
                    @if ($gruppen)
                        {{-- Picked, not typed: the DN is stored, it is unique where a
                             name may not be. The list gets a search field by itself. --}}
                        <x-input.select id="group" name="group" class="mt-1 w-full">
                            @foreach ($gruppen as $gruppe)
                                <option value="{{ $gruppe['dn'] }}" @selected(old('group') === $gruppe['dn'])>{{ $gruppe['name'] }}</option>
                            @endforeach
                        </x-input.select>
                    @else
                        <x-input.field id="group" name="group" class="mt-1 w-full" value="{{ old('group') }}" placeholder="DokuVault-Techniker" />
                    @endif
                </div>
                <div>
                    <x-input.label for="role_id" :value="__('Rolle')" />
                    <x-input.select id="role_id" name="role_id" class="mt-1 w-48">
                        @foreach ($rollen as $rolle)
                            <option value="{{ $rolle->id }}" @selected((string) old('role_id') === (string) $rolle->id)>{{ $rolle->name }}</option>
                        @endforeach
                    </x-input.select>
                </div>
                <x-input.button type="submit" :label="__('Zuordnen')" />
                </div>

                @if ($gruppen)
                    {{-- The rare case: a group outside the base DN, say. --}}
                    <details class="text-xs text-gray-500 dark:text-gray-400">
                        <summary class="cursor-pointer select-none">{{ __('Gruppe nicht in der Liste? Von Hand eingeben') }}</summary>
                        <x-input.field name="group_manual" class="mt-2 w-full" value="{{ old('group_manual') }}" placeholder="CN=…,OU=…,DC=…" />
                    </details>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Gruppenname (sAMAccountName), allgemeiner Name oder vollständiger DN.') }}</p>
                @endif
            </form>
        </x-panel>

        {{-- Test sign-in: creates nothing --}}
        <x-panel class="max-w-3xl">
            <div class="mb-1 text-xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Anmeldung testen') }}</div>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ __('Prüft ein Konto gegen das AD und zeigt seine Gruppen und die Rolle, die es bekäme. Es wird niemand angemeldet und kein Benutzer angelegt.') }}</p>

            <form method="POST" action="{{ route('admin.directory.test-user') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="min-w-0 flex-1">
                    <x-input.label for="test_username" :value="__('Benutzername')" />
                    <x-input.field id="test_username" name="test_username" class="mt-1 w-full" value="{{ old('test_username') }}" placeholder="FIRMA\name" autocomplete="off" />
                </div>
                <div class="min-w-0 flex-1">
                    <x-input.label for="test_password" :value="__('Kennwort')" />
                    <x-input.field id="test_password" name="test_password" type="password" class="mt-1 w-full" autocomplete="off" />
                </div>
                <x-input.button type="submit" color="gray" :label="__('Testen')" />
            </form>

            @if ($ergebnis = session('ad_test'))
                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm sm:grid-cols-2 dark:border-gray-700 dark:bg-gray-900/40">
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Name') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $ergebnis['name'] }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Benutzername in DokuVault') }}</dt><dd class="font-mono text-gray-900 dark:text-gray-100">{{ $ergebnis['username'] }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('E-Mail') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $ergebnis['email'] ?: '—' }}</dd></div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Rolle') }}</dt>
                        <dd @class(['font-medium', 'text-green-700 dark:text-green-400' => $ergebnis['rolle'], 'text-red-700 dark:text-red-400' => ! $ergebnis['rolle']])>
                            {{ $ergebnis['rolle'] ?? __('keine – kein Zugang') }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Gruppen') }} ({{ count($ergebnis['gruppen']) }})</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($ergebnis['gruppen'] as $gruppe)
                                <span class="rounded bg-white px-1.5 py-0.5 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">{{ $gruppe }}</span>
                            @empty
                                <span class="text-gray-500">—</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>
            @endif
        </x-panel>

        {{-- How signing in works, for the person setting it up --}}
        <x-panel class="max-w-3xl text-sm text-gray-600 dark:text-gray-300">
            <div class="mb-2 font-medium text-gray-900 dark:text-gray-100">{{ __('So melden sich Benutzer an') }}</div>
            <ul class="list-disc space-y-1 pl-5">
                <li>{{ __('Gibt es einen lokalen Benutzer mit genau diesem Namen, gilt sein DokuVault-Kennwort. Sonst fragt DokuVault das AD.') }}</li>
                <li>{{ __('Im AD geht „name“, „DOMÄNE\name“ und „name@domain“. Wer denselben Namen wie ein lokaler Benutzer hat, nimmt eine der beiden letzten Schreibweisen.') }}</li>
                <li>{{ __('Beim ersten Mal entsteht der Benutzer von selbst. AD-Benutzer richten danach die zweite Stufe ein – das AD prüft nur das Kennwort.') }}</li>
                <li>{{ __('Wird jemand im AD gesperrt oder aus der Gruppe genommen, kommt er bei der nächsten Anmeldung nicht mehr hinein. Kennwörter werden nie in DokuVault gespeichert.') }}</li>
            </ul>
        </x-panel>
</x-demo-locked>
    </div>
</x-admin-layout>

@use('App\Support\SystemWerte')
@use('App\Support\Zeit')
@php
    $cb = 'h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700';
@endphp
<div class="p-3 sm:p-5 space-y-4">
    <div>
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Backup') }}</div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Die Sicherung von DokuVault selbst: Datenbank und hochgeladene Dateien. Liegt sie nur auf diesem Server, ist sie mit ihm weg – deshalb zusätzlich ein externes Ziel.') }}
        </p>
    </div>

    <x-backup-zustand />

    <form wire:submit="speichern" class="space-y-4">
        {{-- Schedule --}}
        <x-panel>
            <div class="mb-3 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Zeitplan') }}</div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="flex items-center gap-2 pt-6 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="form.backup_aktiv" class="{{ $cb }}"> {{ __('Täglich sichern') }}
                </label>
                <div>
                    <x-input.label for="uhrzeit" :value="__('Uhrzeit').' ('.\App\Support\Zeit::zone().')'" />
                    <x-input.field id="uhrzeit" type="time" wire:model="form.backup_uhrzeit" class="mt-1 w-full dark:scheme-dark" />
                    <x-input.fehler feld="form.backup_uhrzeit" />
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Alte Sicherungen werden eine halbe Stunde vorher nach den Regeln unten aufgeräumt.') }}</p>
        </x-panel>

        {{-- External target --}}
        <x-panel>
            <div class="mb-3 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Externes Ziel') }}</div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input.label :value="__('Art')" />
                    <x-input.select name="backup_ziel_art" wire:model.live="form.backup_ziel_art" class="mt-1 w-full">
                        @foreach ($arten as $wert => $name)
                            <option value="{{ $wert }}">{{ __($name) }}</option>
                        @endforeach
                    </x-input.select>
                </div>
                @if ($form['backup_ziel_art'] !== 'keins')
                    <div>
                        <x-input.label for="host" :value="__('Server')" />
                        <x-input.field id="host" wire:model="form.backup_ziel_host" class="mt-1 w-full" placeholder="nas.firma.local" />
                        <x-input.fehler feld="form.backup_ziel_host" />
                    </div>
                    <div>
                        <x-input.label for="port" :value="__('Port')" />
                        <x-input.field id="port" type="number" wire:model="form.backup_ziel_port" class="mt-1 w-full" :placeholder="$form['backup_ziel_art'] === 'sftp' ? '22' : '21'" />
                        <x-input.fehler feld="form.backup_ziel_port" />
                    </div>
                    <div>
                        <x-input.label for="pfad" :value="__('Ordner')" />
                        <x-input.field id="pfad" wire:model="form.backup_ziel_pfad" class="mt-1 w-full" placeholder="/dokuvault-backup" />
                    </div>
                    <div>
                        <x-input.label for="benutzer" :value="__('Benutzer')" />
                        <x-input.field id="benutzer" wire:model="form.backup_ziel_benutzer" class="mt-1 w-full" autocomplete="off" />
                        <x-input.fehler feld="form.backup_ziel_benutzer" />
                    </div>
                    <div>
                        <x-input.label for="passwort" :value="__('Passwort')" />
                        <x-input.field id="passwort" type="password" wire:model="form.backup_ziel_geheim_passwort" class="mt-1 w-full" autocomplete="new-password"
                            :placeholder="filled($gespeichert['backup_ziel_geheim_passwort']) ? __('gespeichert – leer lassen zum Behalten') : ''" />
                        @if (filled($gespeichert['backup_ziel_geheim_passwort']))
                            <button type="button" wire:click="geheimnisLoeschen('backup_ziel_geheim_passwort')" class="mt-1 text-xs text-red-600 hover:underline dark:text-red-400">{{ __('Gespeichertes Passwort löschen') }}</button>
                        @endif
                    </div>
                    @if ($form['backup_ziel_art'] === 'sftp')
                        <div class="sm:col-span-2">
                            <x-input.label for="schluessel" :value="__('Privater SSH-Schlüssel (statt Passwort)')" />
                            <textarea id="schluessel" wire:model="form.backup_ziel_geheim_schluessel" rows="3"
                                class="mt-1 w-full rounded-sm border-gray-300 bg-white font-mono text-xs text-gray-900 focus:border-cerulean-500 focus:ring-cerulean-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                placeholder="{{ filled($gespeichert['backup_ziel_geheim_schluessel']) ? __('gespeichert – leer lassen zum Behalten') : '-----BEGIN OPENSSH PRIVATE KEY-----' }}"></textarea>
                            @if (filled($gespeichert['backup_ziel_geheim_schluessel']))
                                <button type="button" wire:click="geheimnisLoeschen('backup_ziel_geheim_schluessel')" class="text-xs text-red-600 hover:underline dark:text-red-400">{{ __('Gespeicherten Schlüssel löschen') }}</button>
                            @endif
                        </div>
                    @else
                        <label class="flex items-center gap-2 pt-6 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" wire:model.live="form.backup_ziel_ftps" class="{{ $cb }}"> {{ __('Verschlüsselt (FTPS)') }}
                        </label>
                    @endif
                    <label class="flex items-center gap-2 pt-6 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model="form.backup_lokal_behalten" class="{{ $cb }}"> {{ __('Zusätzlich lokal behalten') }}
                    </label>
                @endif
            </div>

            @if ($form['backup_ziel_art'] !== 'keins')
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-input.button type="button" color="gray" class="h-9" wire:click="verbindungTesten" :label="__('Verbindung testen')" />
                    <span wire:loading wire:target="verbindungTesten" class="text-sm text-gray-500">{{ __('prüft …') }}</span>
                    @if ($test)
                        <span @class(['text-sm', 'text-green-700 dark:text-green-400' => $test[0], 'text-red-600 dark:text-red-400' => ! $test[0]])>{{ $test[1] }}</span>
                    @endif
                </div>
                @if ($form['backup_ziel_art'] === 'ftp' && ! $form['backup_ziel_ftps'])
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">{{ __('Ohne FTPS gehen Passwort und Sicherung unverschlüsselt durchs Netz. Mit Archivpasswort (unten) ist wenigstens die Sicherung selbst geschützt.') }}</p>
                @endif
            @endif
        </x-panel>

        {{-- Retention --}}
        <x-panel>
            <div class="mb-1 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Aufbewahrung') }}</div>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ __('Erst alle Sicherungen, danach je eine pro Tag, Woche und Monat. Die neueste wird nie gelöscht.') }}</p>
            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    'backup_tage_alle' => __('Alle behalten (Tage)'),
                    'backup_tage_taeglich' => __('Täglich (Tage)'),
                    'backup_wochen' => __('Wöchentlich (Wochen)'),
                    'backup_monate' => __('Monatlich (Monate)'),
                    'backup_max_mb' => __('Höchstens (MB)'),
                ] as $feld => $beschriftung)
                    <div>
                        <x-input.label :for="$feld" :value="$beschriftung" />
                        <x-input.field :id="$feld" type="number" wire:model="form.{{ $feld }}" class="mt-1 w-full" />
                        <x-input.fehler :feld="'form.'.$feld" />
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- Encryption and notification --}}
        <x-panel>
            <div class="mb-3 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Verschlüsselung und Benachrichtigung') }}</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input.label for="archivpasswort" :value="__('Archivpasswort')" />
                    <x-input.field id="archivpasswort" type="password" wire:model="form.backup_geheim_archivpasswort" class="mt-1 w-full" autocomplete="new-password"
                        :placeholder="filled($gespeichert['backup_geheim_archivpasswort']) ? __('gespeichert – leer lassen zum Behalten') : __('mindestens 8 Zeichen')" />
                    <x-input.fehler feld="form.backup_geheim_archivpasswort" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Verschlüsselt das ZIP der Sicherung und nimmt die .env mit dem Schlüssel (APP_KEY) mit hinein. Ohne dieses Passwort lässt sie sich nicht wiederherstellen – gut aufbewahren, außerhalb von DokuVault.') }}</p>
                    @if (filled($gespeichert['backup_geheim_archivpasswort']))
                        <button type="button" wire:click="geheimnisLoeschen('backup_geheim_archivpasswort')" class="mt-1 text-xs text-red-600 hover:underline dark:text-red-400">{{ __('Archivpasswort entfernen') }}</button>
                    @endif
                </div>
                <div>
                    <x-input.label for="mail" :value="__('Benachrichtigung an')" />
                    <x-input.field id="mail" type="email" wire:model="form.backup_mail" class="mt-1 w-full" placeholder="it@firma.de" />
                    <x-input.fehler feld="form.backup_mail" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Mail bei erfolgreicher und fehlgeschlagener Sicherung. Leer: keine Mail. Braucht einen eingerichteten Mailserver.') }}</p>
                </div>
            </div>
            @if (blank($gespeichert['backup_geheim_archivpasswort']))
                {{-- Without an archive password the .env stays out of the ZIP, and with it APP_KEY. --}}
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                    <span class="font-semibold">{{ __('Schlüssel fehlt in der Sicherung.') }}</span>
                    {{ __('Ohne Archivpasswort kommt die .env nicht ins ZIP. Alle gespeicherten Passwörter (Server, WLAN, Firewall, …) sind mit dem APP_KEY verschlüsselt und nach einer Wiederherstellung ohne ihn nicht mehr lesbar. Archivpasswort setzen oder den APP_KEY aus der .env getrennt aufbewahren, z. B. im Passwortmanager.') }}
                </div>
            @endif
        </x-panel>

        <div class="flex justify-end">
            <x-input.button class="h-9" :label="__('Speichern')" />
        </div>
    </form>

    {{-- Existing backups --}}
    <x-panel polster="keins" class="overflow-x-auto">
        <div class="flex items-center justify-between p-4">
            <div class="text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Vorhandene Sicherungen') }}</div>
            <div class="flex gap-2">
                @if ($hatExtern && ! $externAbrufen)
                    <x-input.button type="button" color="gray" class="h-9" wire:click="$set('externAbrufen', true)" :label="__('Externe Sicherungen abrufen')" />
                @endif
                <x-input.button type="button" color="gray" class="h-9" wire:click="jetztSichern" :label="__('Jetzt sichern')" />
            </div>
        </div>
        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="border-y border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Datum') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Ziel') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Datei') }}</th>
                    <th class="px-4 py-2.5 text-right font-semibold">{{ __('Größe') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sicherungen as $s)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                        @if (isset($s['fehler']))
                            <td class="px-4 py-2" colspan="5"><span class="text-red-600 dark:text-red-400">{{ $s['ziel'] }}: {{ __('nicht lesbar') }} – {{ $s['fehler'] }}</span></td>
                        @else
                            <td class="whitespace-nowrap px-4 py-2 text-gray-900 dark:text-gray-100">{{ Zeit::anzeigen($s['datum'], 'd.m.Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $s['ziel'] }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ basename($s['pfad']) }}</td>
                            <td class="px-4 py-2 text-right">{{ SystemWerte::lesbar($s['groesse']) }}</td>
                            <td class="px-4 py-2 text-right">
                                {{-- A plain link, not a Livewire action: Livewire would push the whole archive base64 through JSON. --}}
                                <a href="{{ route('admin.backup.download', ['disk' => $s['disk'], 'pfad' => $s['pfad']]) }}"
                                   class="text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Herunterladen') }}</a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Sicherung vorhanden.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>

    {{-- Restore guide --}}
    <x-panel>
        <details>
            <summary class="cursor-pointer text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Wiederherstellen') }}</summary>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Die ZIP enthält den Datenbank-Dump, alle hochgeladenen Dateien (storage/app) und den Programmstand; mit Archivpasswort auch die .env. Auf einem neuen Server mit PHP, MySQL/MariaDB und Webserver:') }}</p>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-700 dark:text-gray-300">
                <li>{{ __('Repository klonen, z. B. nach /var/www/dokuvault, und die Sicherung herunterladen.') }}</li>
                <li>{{ __('Entpacken (fragt nach dem Archivpasswort):') }}
                    <code class="mt-1 block rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-800">7z x DokuVault-Sicherung.zip -o/tmp/restore</code></li>
                <li>{{ __('.env zurücklegen – aus der Sicherung oder eine neue mit dem aufbewahrten APP_KEY; Datenbank-Zugang an den neuen Server anpassen.') }}
                    <code class="mt-1 block rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-800">cp /tmp/restore/var/www/dokuvault/.env /var/www/dokuvault/.env</code></li>
                <li>{{ __('Dateien zurückkopieren:') }}
                    <code class="mt-1 block rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-800">rsync -a /tmp/restore/var/www/dokuvault/storage/app/ /var/www/dokuvault/storage/app/</code></li>
                <li>{{ __('Leere Datenbank anlegen und den Dump einspielen:') }}
                    <code class="mt-1 block rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-800">mysql dokuvault &lt; /tmp/restore/db-dumps/mysql-*.sql</code></li>
                <li>{{ __('Abhängigkeiten, Migrationen und Caches:') }}
                    <code class="mt-1 block rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-800">cd /var/www/dokuvault &amp;&amp; ./deploy.sh</code></li>
                <li>{{ __('Anmelden und ein gespeichertes Passwort öffnen – ist es lesbar, passt der APP_KEY.') }}</li>
            </ol>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ __('Die Pfade in der ZIP folgen dem Installationsordner des alten Servers. Einmal auf einer Test-VM durchspielen, bevor man es braucht.') }}</p>
        </details>
    </x-panel>
</div>

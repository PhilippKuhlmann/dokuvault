<x-admin-layout>
    <div class="p-3 sm:p-5 space-y-6">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Einstellungen') }}</div>

        @if (session('success'))
            <div class="max-w-3xl rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/25 dark:text-green-300" role="status">
                {{ session('success') }}
            </div>
        @endif

        {{-- Eine Sammelanzeige oben: Ein Fehler an einem Feld ohne eigene
             Meldung - etwa die Auswahl - blieb sonst unsichtbar, und die Seite
             sah aus, als waere nichts passiert. --}}
        @if ($errors->any())
            <div class="max-w-3xl rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/25 dark:text-red-300">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $fehler)
                        <li>{{ $fehler }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.mail.update') }}"
            class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            @csrf
            @method('PATCH')

            <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Mailversand') }}</div>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Über diesen Server gehen Einladungen und Links zum Zurücksetzen von Kennwörtern hinaus. Bleibt der Server leer, gelten die Werte aus der Umgebung.') }}
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input.label for="mail_host" :value="__('Server')" />
                    <x-input.field id="mail_host" name="mail_host" class="mt-1 w-full"
                        value="{{ old('mail_host', $host) }}" placeholder="smtp.example.com" />
                    <x-input.error :messages="$errors->get('mail_host')" class="mt-1" />
                    @unless ($host)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Zurzeit aus der Umgebung:') }} {{ $ausUmgebung['host'] ?: '—' }}:{{ $ausUmgebung['port'] ?: '—' }}
                        </p>
                    @endunless
                </div>

                <div>
                    <x-input.label for="mail_port" :value="__('Port')" />
                    <x-input.field id="mail_port" name="mail_port" type="number" class="mt-1 w-full"
                        value="{{ old('mail_port', $port) }}" placeholder="587" />
                    <x-input.error :messages="$errors->get('mail_port')" class="mt-1" />
                </div>

                <div>
                    <x-input.label for="mail_encryption" :value="__('Verschlüsselung')" />
                    <x-input.select id="mail_encryption" name="mail_encryption" class="mt-1 w-full">
                        {{-- Die üblichen zwei plus "ohne". Mehr kennt der
                             Mailer nicht, und eine freie Eingabe hier wäre nur
                             eine Fehlerquelle. --}}
                        @foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', '' => __('Ohne')] as $wert => $beschriftung)
                            <option value="{{ $wert }}" @selected(old('mail_encryption', $encryption) === $wert)>{{ $beschriftung }}</option>
                        @endforeach
                    </x-input.select>
                </div>

                <div class="sm:col-span-2">
                    <x-input.label for="mail_username" :value="__('Benutzername')" />
                    <x-input.field id="mail_username" name="mail_username" class="mt-1 w-full"
                        value="{{ old('mail_username', $username) }}" autocomplete="off" />
                </div>

                <div class="sm:col-span-3">
                    <x-input.label for="mail_password" :value="__('Kennwort')" />
                    <x-input.field id="mail_password" name="mail_password" type="password" class="mt-1 w-full"
                        autocomplete="new-password"
                        placeholder="{{ $hatKennwort ? __('unverändert lassen') : '' }}" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($hatKennwort)
                            {{-- Es geht nie wieder hinaus - nur die Auskunft, dass eines da ist. --}}
                            {{ __('Ein Kennwort ist hinterlegt. Leer lassen heißt: unverändert.') }}
                        @else
                            {{ __('Wird verschlüsselt abgelegt.') }}
                        @endif
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <x-input.label for="mail_from_address" :value="__('Absenderadresse')" />
                    <x-input.field id="mail_from_address" name="mail_from_address" type="email" class="mt-1 w-full"
                        value="{{ old('mail_from_address', $from_address) }}" placeholder="{{ $ausUmgebung['from'] }}" />
                    <x-input.error :messages="$errors->get('mail_from_address')" class="mt-1" />
                </div>

                <div>
                    <x-input.label for="mail_from_name" :value="__('Absendername')" />
                    <x-input.field id="mail_from_name" name="mail_from_name" class="mt-1 w-full"
                        value="{{ old('mail_from_name', $from_name) }}" placeholder="{{ \App\Models\Setting::appName() }}" />
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-input.button :label="__('Speichern')" />
            </div>
        </form>

        @if ($hatKennwort)
            <form method="POST" action="{{ route('admin.mail.kennwort') }}" class="max-w-3xl">
                @csrf
                @method('delete')
                <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700 dark:hover:text-gray-300">
                    {{ __('Hinterlegtes Kennwort entfernen') }}
                </button>
            </form>
        @endif

        {{-- Ohne diese Probe erfährt ein Administrator erst dann, dass die
             Zugangsdaten nicht stimmen, wenn ein Benutzer auf seine Einladung
             wartet. --}}
        <form method="POST" action="{{ route('admin.mail.test') }}"
            class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            @csrf

            <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Probe') }}</div>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Verschickt eine Nachricht mit den gespeicherten Einstellungen. Meldet der Server einen Fehler, steht er hier.') }}
            </p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="grow">
                    <x-input.label for="test_an" :value="__('Empfänger')" />
                    <x-input.field id="test_an" name="test_an" type="email" class="mt-1 w-full"
                        value="{{ old('test_an', auth()->user()->email) }}" />
                </div>
                <x-input.button size="feld" color="gray" :label="__('Testmail senden')" />
            </div>

            <x-input.error :messages="$errors->get('test_an')" class="mt-2" />
        </form>
    </div>
</x-admin-layout>

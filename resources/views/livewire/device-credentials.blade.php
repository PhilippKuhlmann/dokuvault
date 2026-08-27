{{-- Eigenstaendig: Wrapper haelt dieselbe zentrierte Spaltenbreite wie das
     Formular darueber (x-create.main), plus eigener Kartenrahmen.
     Eingebettet: beides faellt weg, der Block sitzt in der Karte des Formulars
     und bekommt statt des Rahmens nur eine Trennlinie nach oben. --}}
<div @class([
    'mx-auto max-w-3xl px-3' => ! $eingebettet,
    'px-5 sm:px-6' => $eingebettet && ! $randlos,
])>
<div @class([
    'my-3 p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700' => ! $eingebettet,
    'border-t border-gray-100 py-5 dark:border-gray-700' => $eingebettet,
])>
    {{-- Der Hinweis trennt diese Karte vom Formular darueber: Dort speichert ein
         Knopf am Ende, hier wirkt jede Verknuepfung sofort. --}}
    <div class="mb-1 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Zugangsdaten') }}</div>
        <span class="rounded bg-cerulean-50 px-2 py-0.5 text-xs text-cerulean-700 dark:bg-cerulean-950 dark:text-cerulean-300">{{ __('speichert sofort') }}</span>
    </div>
    <div class="text-xs text-gray-400 dark:text-gray-500 mb-4">
        {{ __('Kennwörter und SSH-Schlüssel – derselbe Eintrag kann an mehreren Systemen hängen.') }}
    </div>

    @if ($entries->isNotEmpty())
        {{-- Die Spalte nur zeigen, wenn irgendwo etwas drinsteht: Sie ist fuer den
             Ausnahmefall gedacht, dass dasselbe Login an zwei Geraeten
             Verschiedenes bedeutet. Sonst wiederholt sie nur den Namen. --}}
        @php ($zeigeVerwendung = $entries->contains(fn ($e) => filled($e->note)))

        {{-- Nur wo ein Schluessel haengt: Bei reinen Kennwoertern waere die
             Spalte durchgehend leer. Sie ist das, was man mit
             "ssh-keygen -lf ~/.ssh/authorized_keys" auf dem Server vergleicht -
             erst damit laesst sich pruefen, ob das Dokumentierte noch stimmt. --}}
        @php ($zeigeFingerprint = $entries->contains(fn ($e) => $e->login->istSchluessel()))

        {{-- Eigener Scrollbereich: die Spalten passen auf 375 px nicht nebeneinander,
             und die ganze Seite soll deswegen nicht seitlich wandern. --}}
        <div class="overflow-x-auto mb-4">
        <table class="w-full min-w-[26rem] text-sm">
            <thead class="text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('Name') }}</th>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('Benutzername') }}</th>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('Passwort') }}</th>
                    @if ($zeigeFingerprint)
                        <th class="py-2 pr-4 text-left font-semibold">{{ __('Fingerprint') }}</th>
                    @endif
                    @if ($zeigeVerwendung)
                        <th class="py-2 pr-4 text-left font-semibold">{{ __('Verwendung') }}</th>
                    @endif
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr wire:key="link-{{ $entry->id }}" class="border-b border-gray-50 last:border-0 dark:border-gray-700/50">
                        <td class="py-2 pr-4 text-gray-900 dark:text-gray-100">
                            @php ($istSchluessel = $entry->login->istSchluessel())
            {{-- Je Art in die eigene Liste: Beide werden im Modal bearbeitet,
                 eigene Bearbeiten-Seiten gibt es nicht mehr. --}}
                            @if ($istSchluessel && auth()->user()->can('sshkey_viewAny'))
                                <a href="{{ route('sshkey.index', $kunde) }}"
                                    class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">{{ $entry->login->name }}</a>
                            @elseif (! $istSchluessel && auth()->user()->can('logingeneral_viewAny'))
                                <a href="{{ route('logingeneral.index', $kunde) }}"
                                    class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">{{ $entry->login->name }}</a>
                            @else
                                {{ $entry->login->name }}
                            @endif

                            {{-- Ohne das Merkmal sieht man der Zeile nicht an, dass
                                 unter "Passwort" eine Passphrase steht. --}}
                            @if ($istSchluessel)
                                <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 align-middle text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __('SSH') }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4 font-mono text-gray-600 dark:text-gray-300">{{ $entry->login->username ?: '—' }}</td>
                        <td class="py-2 pr-4">
                            @if ($entry->login->password)
                                <div class="flex items-center gap-2" x-data="{ show: false, copied: false }">
                                    <input x-ref="pw{{ $entry->id }}" :type="show ? 'text' : 'password'" disabled
                                        value="{{ $entry->login->password }}"
                                        class="w-28 p-0 text-sm font-mono bg-transparent border-0 text-gray-900 dark:text-gray-100">
                                    <button type="button" tabindex="-1" x-on:click="show = !show"
                                        title="{{ $entry->login->istSchluessel() ? __('Passphrase anzeigen') : __('Passwort anzeigen') }}"
                                        class="text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                    <button type="button" tabindex="-1" title="{{ __('Passwort kopieren') }}"
                                        x-on:click="copyText($refs.pw{{ $entry->id }}.value); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
                                        <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                        </svg>
                                        <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-green-600 dark:text-green-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </button>
                                </div>
                            @elseif ($entry->login->istSchluessel())
                                {{-- Ein Schluessel ohne Passphrase ist eine Aussage,
                                     kein fehlender Wert. --}}
                                <span class="text-gray-400 dark:text-gray-500">{{ __('ohne Passphrase') }}</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">—</span>
                            @endif
                        </td>
                        @if ($zeigeFingerprint)
                            <td class="py-2 pr-4">
                                @if ($entry->login->istSchluessel())
                                    <x-fingerprint :value="$entry->login->fingerprint" />
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                        @endif
                        @if ($zeigeVerwendung)
                            <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">{{ $entry->note ?: '—' }}</td>
                        @endif
                        <td class="py-2 text-right">
                            <button type="button" wire:click="detach({{ $entry->id }})"
                                wire:confirm="{{ __('Verknüpfung lösen? Der Login-Eintrag selbst bleibt bestehen.') }}"
                                class="text-red-600 hover:text-red-700 text-sm">{{ __('Lösen') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @else
        <div class="text-sm text-gray-400 dark:text-gray-500 mb-4">{{ __('Noch keine Zugangsdaten verknüpft.') }}</div>
    @endif

    {{-- Vorhandenes Login anhaengen. Der haeufige Fall: das root-Passwort gibt es schon. --}}
    {{-- wire:key auf beiden Bloecken: sonst verwendet Livewire beim Umschalten
         dieselben Input-Elemente weiter und haengt sie an ein anderes wire:model. --}}
    @unless ($neu)
    <div class="flex flex-wrap items-end gap-2" wire:key="anhaengen">
        <div class="flex flex-col min-w-0 max-w-full">
            <x-input.label :value="__('Vorhandenes Login')" />
            <x-input.select name="login_id" wire:model="login_id" class="mt-1 max-w-full">
                <option value="">{{ __('— bitte wählen —') }}</option>
                {{-- Benutzername nur anhaengen, wenn der Name ihn nicht schon nennt:
                     umgezogene Geraete-Logins heissen bereits "NAS-01 (admin)". --}}
                @foreach (['password' => __('Kennwörter'), 'sshkey' => __('SSH-Schlüssel')] as $art => $ueberschrift)
                    @if (($logins[$art] ?? collect())->isNotEmpty())
                        <optgroup label="{{ $ueberschrift }}">
                            @foreach ($logins[$art] as $login)
                                <option value="{{ $login->id }}">{{ $login->name }}{{ $login->username && ! str_contains($login->name, $login->username) ? ' ('.$login->username.')' : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </x-input.select>
            @error('login_id') <span class="text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
        </div>
        <div class="flex flex-col">
            <x-input.label :value="__('Abweichende Verwendung')" />
            <x-input.text wire:model="note" type="text" class="mt-1 w-48" :placeholder="__('nur wenn abweichend')" />
        </div>
        <x-input.button type="button" size="feld" wire:click="attach" :label="__('Verknüpfen')" />
        {{-- Textknopf ohne Flaeche, aber auf derselben Hoehe wie die Felder daneben --}}
        <button type="button" wire:click="$set('neu', true)"
            class="inline-flex items-center border border-transparent px-2 py-2 text-sm leading-6 text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">{{ __('oder neu anlegen') }}</button>
    </div>
    @else

    {{-- Neues Login anlegen und in einem Rutsch anhaengen, damit man fuer den
         ersten Eintrag nicht die Seite verlassen muss. --}}
    <div class="flex flex-wrap items-end gap-2" wire:key="neu-anlegen">
        <div class="flex flex-col">
            <x-input.label :value="__('Name')" />
            <x-input.text wire:model="name" type="text" class="mt-1 w-44" :placeholder="__('z. B. Linux root')" />
            @error('name') <span class="text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
        </div>
        <div class="flex flex-col">
            <x-input.label :value="__('Benutzername')" />
            <x-input.text wire:model="username" type="text" class="mt-1 w-32" placeholder="root" />
        </div>
        <div class="flex flex-col">
            <x-input.label :value="__('Passwort')" />
            <x-input.text wire:model="password" type="text" class="mt-1 w-40" />
        </div>
        <div class="flex flex-col">
            <x-input.label :value="__('Abweichende Verwendung')" />
            <x-input.text wire:model="note" type="text" class="mt-1 w-40" :placeholder="__('nur wenn abweichend')" />
        </div>
        <x-input.button type="button" size="feld" wire:click="create" :label="__('Anlegen und verknüpfen')" />
        {{-- Textknopf ohne Flaeche, aber auf derselben Hoehe wie die Felder daneben --}}
        <button type="button" wire:click="$set('neu', false)"
            class="inline-flex items-center border border-transparent px-2 py-2 text-sm leading-6 text-gray-500 hover:text-gray-700 dark:text-gray-400">{{ __('Abbrechen') }}</button>
    </div>
    @endunless
</div>
</div>

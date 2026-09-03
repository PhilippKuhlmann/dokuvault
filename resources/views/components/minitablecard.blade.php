@props(['title', 'array' => []])

{{-- Leere Felder weglassen und den Block ganz auslassen, wenn nichts uebrig
     bleibt: Beim Standserver sind Einbautiefe und Hoeheneinheiten leer, bei den
     meisten Geraeten die zweite IP - als leere Zeilen sah die Karte
     lueckenhaft aus. Dieselbe Regel wie in der Schrankliste. --}}
@php ($gefuellt = array_filter($array, fn ($v) => filled($v)))

@if (count($gefuellt))
<div class="w-full mb-5 break-inside-avoid">
    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ $title }}
    </div>
    <div class="text-sm dark:text-gray-100">
        {{-- siehe Hinweis unten an der Beschriftungsspalte --}}
        <table class="w-full">
            @foreach ($gefuellt as $key => $value)
                <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                    {{-- Kein whitespace-nowrap: Der Kartenkoerper laeuft in CSS-Spalten, und
                         eine Tabelle schrumpft nicht unter ihre Mindestbreite - mit unbrechbarer
                         Beschriftung lief sie in die Nachbarspalte und aus der Karte heraus
                         ("10.10.30.7Hersteller"). Umgebrochen wird nur, wenn es sonst nicht passt. --}}
                    <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ __($key) }}</td>
                    @if ($key == 'Passwort' || $key == 'BMC Passwort' || $key == 'DSRM Passwort' || $key == 'Cloud Backup Passwort' || $key == 'Verschlüsselungscode' || $key == 'USC-PIN')
                        <td scope="row">
                            <div class="" x-data="{ show: true, copied: false }">

                                <div class="relative">
                                    <input x-ref="pw" placeholder="" :type="show ? 'password' : 'text'"
                                        class="text-sm w-full p-0 pr-16 text-gray-900 bg-transparent dark:text-gray-100 border-0"
                                        value="{{ $value }}" disabled>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2 text-sm leading-5">

                                        <button type="button" tabindex="-1" title="{{ __('Passwort kopieren') }}"
                                            @click="copyText($refs.pw.value); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                                            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                            </svg>
                                            <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-green-600 dark:text-green-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </button>

                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" @click="show = !show"
                                            :class="{ 'hidden': !show, 'block': show }"
                                            class="w-6 h-6 text-gray-900 dark:text-gray-100 cursor-pointer">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>

                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" @click="show = !show"
                                            :class="{ 'block': !show, 'hidden': show }"
                                            class="w-6 h-6 text-gray-900 dark:text-gray-100 cursor-pointer">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>

                                    </div>
                                </div>
                            </div>
                        </td>
                    @elseif ($ziel = \App\Support\Adresse::sicher($value))
                        {{-- Am Wert, nicht an der Beschriftung. Vorher stand hier
                             eine Liste von vier Beschriftungen, und die
                             Verwaltungsoberflaeche einer Firewall heisst
                             "Oberflaeche" - sie war deshalb nie ein Link, obwohl
                             dort eine https-Adresse steht. Wer ein neues URL-Feld
                             anlegt, muesste sonst eine Liste pflegen, die er
                             nicht findet.

                             Verlinkt wird nur, was http oder https ist. In
                             diesen Feldern steht oft eine nackte IP - die soll
                             weiterhin dastehen, nur eben nicht als Link. Und ein
                             "javascript:..." fuehrte sonst bei jedem Klick Code
                             aus, in der Sitzung dessen, der klickt.

                             Nach den Geheimnissen, nicht davor: Ein Kennwort
                             bleibt maskiert, auch wenn es wie eine Adresse
                             aussieht. --}}
                        <td class="py-1 break-all">
                            <a href="{{ $ziel }}" target="_blank" rel="noopener noreferrer" class="text-cerulean-600 hover:text-cerulean-700 hover:underline">
                                {{ $value }}
                            </a>
                        </td>
                    @elseif (\Illuminate\Support\Str::contains($key, ['IP', 'MAC', 'Serien']))
                        <td class="py-1 break-words text-gray-900 dark:text-gray-100">
                            <x-copy :value="$value" />
                        </td>
                    @else
                        <td class="py-1 break-words text-gray-900 dark:text-gray-100">{{ $value }}</td>
                    @endif

                </tr>
            @endforeach
        </table>
    </div>
</div>
@endif

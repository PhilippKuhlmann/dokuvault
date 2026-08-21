{{--
    $nach: Inhalt, der in derselben Karte unter dem Formular steht, aber
    ausserhalb des <form> - fuer eigenstaendige Livewire-Bloecke (weitere
    IP-Adressen, Zugangsdaten). Verschachtelte Formulare erlaubt HTML nicht,
    darum bleibt ihr Speichern getrennt; optisch ist es eine Karte.
    Ist $nach gesetzt, traegt ein Wrapper den Rahmen statt des <form>.

    $breit: 5xl statt 3xl Lesebreite - fuer zweispaltige Formulare.
--}}
@props(['header', 'action', 'labelsubmit' => 'Hinzufügen', 'right' => '', 'nach' => '', 'breit' => false])

@php
    // Formulare mit rechter Spalte (z. B. die Rechte-Matrix im Rollen-Formular) brauchen
    // die volle Breite. Alle uebrigen werden mittig auf Lesebreite gehalten - dieselbe
    // Breite nutzen auch die Karten darunter (IP-Adressen, Loeschen), damit die
    // Bearbeiten-Seite eine durchgehende Spalte ergibt.
    $hasRight = trim((string) $right) !== '';
    $hasNach = trim((string) $nach) !== '';
    $rahmen = 'rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700';

    // Ziel von "Abbrechen": die Liste, aus der man kommt. Vorher stand hier
    // redirect()->back(), das beim Rendern der Seite auf die Seite selbst zeigte -
    // der Knopf lud also nur neu, und aus dem Formular kam man nur ueber die
    // Seitenleiste zurueck. Der Name der Liste steht im Routennamen:
    // vm.edit -> vm.index, admin.user.create -> admin.user.index.
    $listenRoute = \Illuminate\Support\Str::beforeLast((string) request()->route()?->getName(), '.').'.index';
    $liste = \Illuminate\Support\Facades\Route::getRoutes()->getByName($listenRoute);

    if ($liste) {
        // Die Kundenlisten brauchen den Kunden, die Admin-Listen nicht.
        $abbrechen = in_array('customer', $liste->parameterNames(), true)
            ? route($listenRoute, ['customer' => request()->route('customer')])
            : route($listenRoute);
    } else {
        $abbrechen = url()->previous();
    }
@endphp

{{-- px-3 haelt den seitlichen Abstand auf schmalen Bildschirmen, wo max-w-3xl noch nicht greift --}}
<div @class([
    'md:flex xs:flex-col',
    'mx-auto px-3' => ! $hasRight,
    'max-w-5xl' => ! $hasRight && $breit,
    'max-w-3xl' => ! $hasRight && ! $breit,
])>
@if ($hasNach)
<div class="{{ $rahmen }} my-3 w-full">
@endif
    <form method="post" action="{{ $action }}" @class([
        'p-5 sm:p-6',
        $rahmen => ! $hasNach,
        'm-3' => $hasRight && ! $hasNach,
        'w-full my-3' => ! $hasRight && ! $hasNach,   // my statt m: die Zentrierung macht der Container
        'w-full' => $hasNach,
    ]) enctype="multipart/form-data">
        @csrf

        <div @class(['md:flex xs:flex-col', 'md:w-128' => $hasRight, 'w-full' => ! $hasRight])>

            <div class="flex flex-col text-cerulean-950 dark:text-cerulean-500 w-full">
                {{-- Weg zurueck, ohne zu speichern. "Abbrechen" steht am Formularende;
                     bei einem langen Formular scrollt man dafuer erst nach unten.
                     w-fit, sonst reicht die Klickflaeche ueber die ganze Breite. --}}
                <a href="{{ $abbrechen }}"
                    class="mb-2 inline-flex w-fit items-center gap-1 text-sm text-gray-500 hover:text-cerulean-600 dark:text-gray-400 dark:hover:text-cerulean-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    {{ __('Zurück') }}
                </a>

                <div class="text-2xl font-CoconPro">
                    {{ $header }}
                </div>

                {{ $slot }}

            </div>
        </div>

        {{ $right }}

        {{-- Ganz unten, hinter der rechten Spalte: Im Rollen-Formular steht
             dort die Rechte-Matrix, und ein Speichern-Knopf ueber einer
             Tabelle mit fuenfzig Zeilen sieht so aus, als gehoere er nicht
             dazu. Bei einspaltigen Formularen ist die Stelle dieselbe wie
             vorher, weil die rechte Spalte dann leer ist. --}}
        <div class="flex flex-row justify-end gap-3 mt-6">
            <a href="{{ $abbrechen }}"
                class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-DINPro-bold text-gray-700 bg-white border border-gray-300 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">{{ __('Abbrechen') }}</a>
            <x-input.button label="{{ $labelsubmit }}" />
        </div>

        <div class="flex flex-col mt-10 w-full max-w-md:w-96">
            @foreach ($errors->all() as $error)
                <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400"
                    role="alert">
                    {{ $error }}
                </div>
            @endforeach
        </div>
    </form>

{{-- Ausserhalb des <form>, innerhalb der Karte. Der Knopf oben speichert die
     Stammdaten, diese Bloecke speichern selbst - darum tragen sie den
     Hinweis "speichert sofort". --}}
@if ($hasNach)
    {{ $nach }}
</div>
@endif
</div>

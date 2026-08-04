@props(['header', 'action', 'labelsubmit' => 'Hinzufügen', 'right' => ''])

@php
    // Formulare mit rechter Spalte (z. B. die Rechte-Matrix im Rollen-Formular) brauchen
    // die volle Breite. Alle uebrigen werden mittig auf Lesebreite gehalten - dieselbe
    // Breite nutzen auch die Karten darunter (IP-Adressen, Loeschen), damit die
    // Bearbeiten-Seite eine durchgehende Spalte ergibt.
    $hasRight = trim((string) $right) !== '';
@endphp

{{-- px-3 haelt den seitlichen Abstand auf schmalen Bildschirmen, wo max-w-3xl noch nicht greift --}}
<div @class(['md:flex xs:flex-col', 'mx-auto max-w-3xl px-3' => ! $hasRight])>
    <form method="post" action="{{ $action }}" @class([
        'p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700',
        'm-3' => $hasRight,
        'w-full my-3' => ! $hasRight,   // my statt m: die Zentrierung macht der Container
    ]) enctype="multipart/form-data">
        @csrf

        <div @class(['md:flex xs:flex-col', 'md:w-128' => $hasRight, 'w-full' => ! $hasRight])>

            <div class="flex flex-col text-cerulean-950 dark:text-cerulean-500 w-full">
                <div class="text-2xl font-CoconPro">
                    {{ $header }}
                </div>

                {{ $slot }}

                <div class="flex flex-row justify-end gap-3 mt-6">
                    <a href="{{ redirect()->back()->getTargetUrl() }}"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-DINPro-bold text-gray-700 bg-white border border-gray-300 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">{{ __('Abbrechen') }}</a>
                    <x-input.button label="{{ $labelsubmit }}" />
                </div>

            </div>
        </div>

        {{ $right }}

        <div class="flex flex-col mt-10 w-full max-w-md:w-96">
            @foreach ($errors->all() as $error)
                <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400"
                    role="alert">
                    {{ $error }}
                </div>
            @endforeach
        </div>
    </form>
</div>

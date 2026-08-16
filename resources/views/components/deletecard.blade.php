@props(['action', 'breit' => false])

{{-- Wrapper haelt dieselbe zentrierte Spaltenbreite wie das Formular darueber -
     breit muss zu x-create.main passen, sonst sitzt die Karte versetzt. --}}
<div @class(['mx-auto px-3 py-3', 'max-w-5xl' => $breit, 'max-w-3xl' => ! $breit])>

    <div id="alert-additional-content-2" class="p-4 mb-4 text-red-800 border border-red-300 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800" role="alert">
        <div class="flex items-center">
            <x-svg.info class="w-5 h-5 mr-2" />
            <h3 class="text-lg font-medium">ACHTUNG</h3>
        </div>
        {{-- Vorher stand hier "unwiederruflich geloescht" - falsch geschrieben
             und sachlich falsch dazu: Geloeschtes landet im Papierkorb und
             laesst sich von dort zurueckholen. Wer das nicht weiss, traut sich
             nicht zu loeschen oder erschrickt hinterher unnoetig. --}}
        <div class="mt-2 mb-4 text-sm">
            {{ __('Der Eintrag wandert in den Papierkorb und lässt sich von dort wiederherstellen.') }}
        </div>
        <div class="flex">
            <form method="POST" action="{{ $action }}"
                onsubmit="return confirm('{{ __('Eintrag in den Papierkorb verschieben?') }}')">
                @csrf
                @method('delete')
                <x-input.button color="red" :label="__('Löschen!')" />
            </form>
        </div>
    </div>

</div>

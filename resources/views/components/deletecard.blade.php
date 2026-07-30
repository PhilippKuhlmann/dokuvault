@props(['action'])

{{-- Wrapper haelt dieselbe zentrierte Spaltenbreite wie Formular und IP-Karte darueber --}}
<div class="mx-auto max-w-3xl px-3 py-3">

    <div id="alert-additional-content-2" class="p-4 mb-4 text-red-800 border border-red-300 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800" role="alert">
        <div class="flex items-center">
            <x-svg.info class="w-5 h-5 mr-2" />
            <h3 class="text-lg font-medium">ACHTUNG</h3>
        </div>
        <div class="mt-2 mb-4 text-sm">
            Mit dem Klick auf Löschen wird das Objekt unwiederruflich gelöscht!
        </div>
        <div class="flex">
            <form method="POST" action="{{ $action }}" onsubmit="return confirm('Objekt wirklich unwiderruflich löschen?')">
                @csrf
                @method('delete')
                <x-input.button color="red" label="Löschen!" />
            </form>
        </div>
    </div>

</div>

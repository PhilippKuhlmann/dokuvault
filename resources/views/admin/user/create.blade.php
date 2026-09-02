<x-admin-layout>
    {{-- Entweder der Administrator vergibt ein Kennwort, oder der Benutzer tut
         es selbst. Beides nebeneinander stehen zu lassen waere die Einladung
         zum Missverstaendnis: ein getipptes Kennwort, das nie gilt. --}}
    <div x-data="{ einladen: {{ old('einladen') ? 'true' : 'false' }} }">
    <x-create.main :header="__('Neuer Benutzer')" action="{{ route('admin.user.store') }}">

        @if ($errors->has('einladung'))
            <div class="mt-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/25 dark:text-red-300">
                {{ $errors->first('einladung') }}
            </div>
        @endif

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        {{-- x-show statt x-if: Das Feld bleibt im Formular stehen und ist nur
             verborgen. Wer den Haken wieder loest, findet sein getipptes
             Kennwort noch vor. --}}
        <div x-show="!einladen" x-cloak>
            <x-create.singlerow :label="__('Passwort')" name="password" type="password" />
            <x-kennwortregel />
        </div>

        <x-create.singlerow :label="__('E-Mail')" name="email" />

        <div class="mt-4">
            <label class="flex cursor-pointer select-none items-start gap-3">
                <input type="checkbox" name="einladen" value="1" x-model="einladen"
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                <span class="text-sm">
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Per E-Mail einladen') }}</span>
                    <span class="mt-0.5 block text-gray-600 dark:text-gray-400">
                        {{ __('Der Benutzer bekommt einen Link und vergibt sich sein Kennwort selbst. Kein Kennwort, das per Zuruf weitergegeben wird.') }}
                    </span>
                </span>
            </label>
        </div>

        <x-create.select.role :$roles/>

        <x-create.select.customer :$customers/>

        <x-create.zweite-stufe />

    </x-create.main>
    </div>
</x-admin-layout>

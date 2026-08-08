<x-app-layout :$customer>
    <x-create.main :header="__('Login bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('logingeneral.update', [$customer, $logingeneral]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$logingeneral->name" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" :default="$logingeneral->description" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$logingeneral->username" :label2="__('Passwort')" name2="password" :default2="$logingeneral->password" />

        <x-edit.hidden hidden="{{ $logingeneral->hidden }}" />

    </x-create.main>

    {{-- Umgekehrte Richtung zur Verknuepfung am Geraet: Wer das Passwort hier
         aendert, muss sehen, welche Systeme davon betroffen sind. Nur Anzeige -
         geloest wird am Geraet, wo auch die Verwendung dokumentiert ist. --}}
    <div class="mx-auto max-w-3xl px-3">
        <div class="my-3 p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-4">{{ __('Verwendet bei') }}</div>

            @php ($verwendungen = $logingeneral->verwendungen())

            @forelse ($verwendungen as $verwendung)
                <div class="py-1.5 text-sm border-b border-gray-50 last:border-0 dark:border-gray-700/50">
                    <span class="text-gray-900 dark:text-gray-100">{{ $verwendung->zielBezeichnung() }}</span>
                    @if ($verwendung->note)
                        <span class="text-gray-400 dark:text-gray-500"> · {{ $verwendung->note }}</span>
                    @endif
                </div>
            @empty
                <div class="text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Mit keinem System verknüpft. Die Verknüpfung entsteht beim Bearbeiten des Geräts.') }}
                </div>
            @endforelse
        </div>
    </div>

    @can('logingeneral_delete')
        <x-deletecard action="{{ route('logingeneral.destroy', [$customer, $logingeneral]) }}" />
    @endcan

</x-app-layout>

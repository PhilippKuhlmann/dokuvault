<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('checklist.index', $customer) }}" class="text-sm text-cerulean-600 hover:underline dark:text-cerulean-400">← {{ __('Checklisten') }}</a>
                <div class="mt-1 flex items-center gap-3 text-3xl font-CoconPro text-gray-900 dark:text-gray-100">
                    @include('checklist._type', ['type' => $checklist->type])
                    {{ $checklist->fullName() }}
                </div>
            </div>
            @can('checklist_manage')
                <x-loeschdialog :url="route('checklist.destroy', [$customer, $checklist])"
                    :frage="__('Checkliste wirklich löschen?')"
                    :hinweis="__('Alle Punkte und der Antrag gehen verloren.')">
                    <x-slot:ausloeser>
                        <x-input.button type="button" color="gray" x-on:click="offen = true" :label="__('Löschen')" />
                    </x-slot:ausloeser>
                </x-loeschdialog>
            @endcan
        </div>

        <livewire:checklist-progress :customer="$customer" :checklist="$checklist" />
    </div>
</x-app-layout>

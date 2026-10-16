<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-5">
        <div>
            <a href="{{ route('checklist.index', $customer) }}" class="text-sm text-cerulean-600 hover:underline dark:text-cerulean-400">← {{ __('Checklisten') }}</a>
            <div class="mt-1 text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Checklisten einrichten') }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Was der Kunde im Formular auswählen kann und welche Schritte die Listen für diesen Kunden enthalten.') }}</div>
        </div>

        <livewire:checklist-settings :customer="$customer" />
    </div>
</x-app-layout>

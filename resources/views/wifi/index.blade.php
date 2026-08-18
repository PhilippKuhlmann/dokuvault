<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegtes WLAN
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="wifi" :customer="$customer" />
</x-app-layout>

<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegtes Postfach
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="mailbox" :customer="$customer" />
</x-app-layout>

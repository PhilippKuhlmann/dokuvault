<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Server
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="server" :customer="$customer" />
</x-app-layout>

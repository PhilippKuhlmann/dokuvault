<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Anschluss
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="internetconnection" :customer="$customer" />
</x-app-layout>

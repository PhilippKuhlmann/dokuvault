<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Anschluss
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:object-list typ="internetconnection" :customer="$customer" />
</x-app-layout>

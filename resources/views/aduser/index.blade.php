<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Benutzer
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="aduser" :customer="$customer" />
</x-app-layout>

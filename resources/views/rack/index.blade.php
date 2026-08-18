<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Schrank
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="rack" :customer="$customer" />
</x-app-layout>

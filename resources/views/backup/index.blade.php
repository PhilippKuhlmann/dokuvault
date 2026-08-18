<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Eintrag
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="backup" :customer="$customer" />
</x-app-layout>

<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Eintrag
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:object-list typ="scanner" :customer="$customer" />
</x-app-layout>

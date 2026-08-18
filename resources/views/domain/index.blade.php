<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Eine im Modal angelegte Domain
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="domain" :customer="$customer" />
</x-app-layout>

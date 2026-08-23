<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Cluster
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:objekt-liste typ="cluster" :customer="$customer" />
</x-app-layout>

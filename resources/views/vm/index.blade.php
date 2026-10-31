<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Eine im Modal angelegte VM
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:object-list typ="vm" :customer="$customer" />
</x-app-layout>

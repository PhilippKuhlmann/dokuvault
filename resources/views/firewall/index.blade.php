<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Eine im Modal angelegte Firewall
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:object-list typ="firewall" :customer="$customer" />
</x-app-layout>

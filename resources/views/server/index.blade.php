<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Ein im Modal angelegter Server
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:object-list typ="server" :customer="$customer" />
</x-app-layout>

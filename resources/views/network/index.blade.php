<x-app-layout :$customer>
    {{-- Die Liste selbst ist eine Livewire-Komponente: Ein im Modal angelegtes
         VLAN erscheint dadurch sofort, ohne die Seite neu zu laden. --}}
    <livewire:network-list :customer="$customer" />
</x-app-layout>

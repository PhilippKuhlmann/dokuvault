<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire: Eine im Modal angelegte Lizenz
         erscheint sofort, ohne die Seite neu zu laden. --}}
    <livewire:object-list typ="licensesoftware" :customer="$customer" />
</x-app-layout>

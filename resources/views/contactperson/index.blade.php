<x-app-layout :$customer>
    {{-- List and form are Livewire: an entry created in the modal appears
         at once, without reloading the page. --}}
    <livewire:object-list typ="contactperson" :customer="$customer" />
</x-app-layout>

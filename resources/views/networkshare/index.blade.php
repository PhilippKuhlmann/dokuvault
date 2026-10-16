<x-app-layout :$customer>
    {{-- List and form are Livewire: an entry created in the modal shows up
         right away, without reloading the page. --}}
    <livewire:objekt-liste typ="networkshare" :customer="$customer" />
</x-app-layout>

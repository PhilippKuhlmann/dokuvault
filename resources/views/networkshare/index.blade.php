<x-app-layout :$customer>
    {{-- List and form are Livewire: an entry created in the modal shows up
         right away, without reloading the page. --}}
    <livewire:object-list typ="networkshare" :customer="$customer" />
</x-app-layout>

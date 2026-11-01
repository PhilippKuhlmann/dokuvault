<x-app-layout :$customer>
    {{-- List and form are Livewire: a licence created in the modal appears
         at once, without reloading the page. --}}
    <livewire:object-list typ="licensesoftware" :customer="$customer" />
</x-app-layout>

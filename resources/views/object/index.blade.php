<x-app-layout :$customer>
    {{-- List and form are Livewire: an entry created in the modal appears
         at once, without reloading the page. One view for every kind from
         config/forms.php - see ObjectListController. --}}
    <livewire:object-list :typ="$type" :customer="$customer" />
</x-app-layout>

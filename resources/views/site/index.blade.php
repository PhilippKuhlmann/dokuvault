<x-app-layout :$customer>
    {{-- Liste und Formular sind Livewire. Nach dem Speichern laedt die Seite
         neu, weil der Standort auch in der Seitenleiste und in jedem
         Geraeteformular auftaucht. --}}
    <livewire:objekt-liste typ="site" :customer="$customer" />
</x-app-layout>

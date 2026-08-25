<x-app-layout :$customer>

    {{-- Die Liste ist Livewire (Suche, Filter, Sortierung); das Layout drumherum
         braucht den Kunden fuer Kopfzeile und Seitenleiste. Dasselbe Muster wie
         bei den uebrigen Kundenlisten. --}}
    <livewire:datei-liste :customer="$customer" />

</x-app-layout>

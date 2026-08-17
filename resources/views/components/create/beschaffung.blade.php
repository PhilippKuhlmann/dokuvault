{{--
    Der Beschaffungsabschnitt fuer jedes Geraeteformular - einmal gebaut statt
    siebzehnmal abgeschrieben.

    Ohne :model rendert er leere Felder (Anlegen), mit :model die gespeicherten
    Werte (Bearbeiten). Die Datumsfelder kommen als Carbon-Objekt aus dem Model
    und muessen fuer <input type="date"> als Y-m-d formatiert werden.
--}}
@props(['model' => null])

<x-create.abschnitt :titel="__('Beschaffung')"
    :hinweis="__('Beantwortet später die Frage, ob das Gerät noch Garantie hat')">

    <x-create.singlerow :label="__('Kaufdatum')" name="purchase_date" type="date"
        :default="$model?->purchase_date?->format('Y-m-d') ?? ''" />

    <x-create.singlerow :label="__('Garantie bis')" name="warranty_until" type="date"
        :default="$model?->warranty_until?->format('Y-m-d') ?? ''" />

    <x-create.singlerow :label="__('Support-Ende (EOL)')" name="eol_date" type="date"
        :default="$model?->eol_date?->format('Y-m-d') ?? ''" />

    <x-create.singlerow :label="__('Lieferant')" name="supplier"
        :default="$model?->supplier ?? ''" />

</x-create.abschnitt>

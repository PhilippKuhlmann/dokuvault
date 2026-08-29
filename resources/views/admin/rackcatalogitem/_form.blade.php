{{--
    Gemeinsame Felder von Anlegen und Bearbeiten.
    Erwartet: $item (RackCatalogItem oder null beim Anlegen).
--}}
@php
    $appearances = config('custom.rack_appearances');
    $gewaehlt = old('appearance', $item->appearance ?? 'blank');
    $maxHe = \App\Http\Requests\RackCatalogItemRequest::MAX_HE;
    $he = max(1, min((int) old('height_units', $item->height_units ?? 1), $maxHe));
    $bild = $item?->bildUrl();
@endphp

<div x-data="{
    appearance: '{{ $gewaehlt }}',
    he: {{ $he }},
    neuesBild: null,
    melden() { $dispatch('rack-vorschau', { appearance: this.appearance, he: this.he }) },
}">

    <x-create.singlerow :label="__('Bezeichnung')" name="name" :default="$item->name ?? ''" />

    <div class="flex flex-col sm:flex-row gap-2">
        <div class="flex flex-col mt-2 w-full sm:w-1/2">
            <x-input.label for="height_units" :value="__('Höheneinheiten (HE)')" />
            {{-- min/max wie in RackCatalogItemRequest: Der Browser soll dieselbe
                 Grenze nennen, die der Server danach ohnehin zieht. --}}
            <x-input.field id="height_units" name="height_units" type="number" class="mt-1"
                min="1" max="{{ $maxHe }}" :value="$he"
                x-model.number="he" x-on:input.debounce.400ms="melden()" />
        </div>

        <div class="flex flex-col mt-2 w-full sm:w-1/2">
            <x-input.label for="sort_order" :value="__('Reihenfolge in der Palette')" />
            <x-input.field id="sort_order" name="sort_order" type="number" class="mt-1"
                value="{{ old('sort_order') ?? $item->sort_order ?? 0 }}" />
        </div>
    </div>

    <x-create.options :label="__('Einbautiefe')" name="full_depth"
        :options="config('custom.server_depths')" :default="(int) ($item->full_depth ?? 1)" />

    <div class="flex flex-col mt-2">
        <x-input.label for="appearance" :value="__('Darstellung in der Frontansicht')" />
        <x-input.select id="appearance" name="appearance" x-model="appearance" x-on:change="melden()">
            @foreach ($appearances as $key => $label)
                <option value="{{ $key }}" @selected($gewaehlt === $key)>{{ __($label) }}</option>
            @endforeach
        </x-input.select>
    </div>

    {{-- Eigenes Foto. Es ersetzt die Zeichnung, hebt sie aber nicht auf: Wer
         das Bild wieder entfernt, sieht sofort wieder die gewaehlte
         Darstellung - deshalb bleibt das Auswahlfeld oben bestehen. --}}
    <div class="flex flex-col mt-4" x-data="{ weg: false }">
        <x-input.label for="image" :value="__('Eigenes Bild der Frontblende')" />

        <div class="mt-1 flex flex-wrap items-start gap-4">
            <x-input.file id="image" name="image"
                accept="{{ collect(\App\Models\RackCatalogItem::FORMATE)->map(fn ($e) => '.'.$e)->join(',') }}"
                x-on:change="neuesBild = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />

            @if ($bild)
                {{-- Das hinterlegte Bild mit einem Knopf daran, statt einer
                     Ankreuzbox weiter unten: Man entfernt das Bild, das man
                     ansieht. Weg ist es erst mit dem Speichern - wie jede
                     andere Aenderung in diesem Formular auch. --}}
                <input type="hidden" name="image_remove" :value="weg ? 1 : 0">

                <figure class="w-32" :class="weg && 'opacity-40'">
                    <img src="{{ $bild }}" alt=""
                        class="h-12 w-full rounded border border-gray-300 bg-gray-100 object-contain dark:border-gray-600 dark:bg-gray-900">
                    <figcaption class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                        <span x-show="!weg">
                            {{ __('Hinterlegtes Bild') }} ·
                            <button type="button" x-on:click="weg = true"
                                class="text-red-600 hover:underline dark:text-red-400">{{ __('Entfernen') }}</button>
                        </span>
                        <span x-show="weg" x-cloak>
                            {{ __('Wird beim Speichern entfernt') }} ·
                            <button type="button" x-on:click="weg = false"
                                class="text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Rückgängig') }}</button>
                        </span>
                    </figcaption>
                </figure>
            @endif

            {{-- Die ausgewaehlte Datei sofort zeigen. Im Schrank steht sie erst
                 nach dem Speichern - hochgeladen ist sie bis dahin nicht. --}}
            <template x-if="neuesBild">
                <figure class="w-32">
                    <img :src="neuesBild" alt=""
                        class="h-12 w-full rounded border border-gray-300 bg-gray-100 object-contain dark:border-gray-600 dark:bg-gray-900">
                    <figcaption class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                        {{ __('Ausgewählt – im Schrank nach dem Speichern') }}
                    </figcaption>
                </figure>
            </template>
        </div>

        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
            {{ __('PNG, JPEG oder WEBP, höchstens 2 MB. Ist ein Bild hinterlegt, tritt es an die Stelle der Zeichnung – in der Vorschau, im Rack und im PDF.') }}
        </p>

        {{-- Empfehlung, die der eingestellten Hoehe folgt. Eine 19"-Blende ist
             482,6 mm breit und 44,45 mm je Hoeheneinheit hoch, also 10,86 : 1 -
             genau aufgeht das nur bei 1086 x 100 je HE. Zwei krumme Zahlen fuer
             0,5 % Genauigkeit sind es nicht wert: 1200 x 110 je HE laesst sich
             merken und ist von blossem Auge nicht zu unterscheiden. --}}
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500"
            x-text="'{{ __('Empfohlene Auflösung für :he HE: :breite × :hoehe Pixel – das Seitenverhältnis einer 19-Zoll-Blende. Anders geschnittene Bilder werden gestaucht.') }}'
                .replace(':he', he).replace(':breite', 1200).replace(':hoehe', he * 110)"></p>
    </div>

    {{-- Vorschau: das Element in einem Schrank, nicht freistehend. Erst dort
         ist zu sehen, wie viel Platz es einnimmt - eine Blende allein sieht
         bei einer und bei drei Hoeheneinheiten gleich aus. --}}
    <div class="mt-4">
        <x-input.label :value="__('Vorschau im Schrank')" />
        <p class="mb-1 mt-1 text-xs text-gray-400 dark:text-gray-500">
            {{ __('Ein Schrank mit :max HE – so viel darf ein Katalogelement höchstens hoch sein.', ['max' => $maxHe]) }}
        </p>
        <livewire:rack-katalog-vorschau :appearance="$gewaehlt" :he="$he" :bild="$bild" />
    </div>

</div>

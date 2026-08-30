{{--
    Gemeinsame Felder von Anlegen und Bearbeiten.
    Erwartet: $item (DeviceModel oder null beim Anlegen).
--}}
@php
    $typen = collect(config('custom.rack_device_types'))->map(fn ($t) => $t[1]);
    // Die Zeichnung eines Geraetetyps steht in derselben Liste - sie ist der
    // Rueckfall, solange kein Foto hinterlegt ist.
    $zeichnungen = collect(config('custom.rack_device_types'))->map(fn ($t) => $t[2]);
    $gewaehlt = old('device_type', $item->device_type ?? $typen->keys()->first());
    $maxHe = \App\Http\Requests\RackCatalogItemRequest::MAX_HE;
    $he = max(1, min((int) old('height_units', $item->height_units ?? 1), $maxHe));
    $bild = $item?->bildUrl();
@endphp

<div x-data="{
    typ: '{{ $gewaehlt }}',
    he: {{ $he }},
    drawing: '{{ old('drawing', $item->drawing ?? '') }}',
    neuesBild: null,
    weg: false,
    zeichnungen: {{ Illuminate\Support\Js::from($zeichnungen) }},
    melden() {
        $dispatch('rack-vorschau', {
            appearance: this.zeichnungen[this.typ], he: this.he,
            drawing: this.drawing,
        })
    },
}">

    <div class="flex flex-col mt-2">
        <x-input.label for="device_type" :value="__('Gerätetyp')" />
        <x-input.select id="device_type" name="device_type" class="mt-1" x-model="typ" x-on:change="melden()">
            @foreach ($typen as $key => $label)
                <option value="{{ $key }}" @selected($gewaehlt === $key)>{{ __($label) }}</option>
            @endforeach
        </x-input.select>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            {{ __('Nur Geräte dieses Typs greifen auf den Eintrag zu – sonst träfe ein Switch „RS-1000" auf einen Recorder gleichen Namens.') }}
        </p>
    </div>

    <x-create.doublerow
        :label1="__('Hersteller')" name1="manufacturer" :default1="$item->manufacturer ?? ''"
        :label2="__('Modell')" name2="model" :default2="$item->model ?? ''" />

    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
        {{ __('Genau so geschrieben wie im Geräteformular – darüber wird der Eintrag gefunden. Groß- und Kleinschreibung sowie zusätzliche Leerzeichen spielen keine Rolle.') }}
    </p>

    <div class="flex flex-col sm:flex-row gap-2">
        <div class="flex flex-col mt-2 w-full sm:w-1/2">
            <x-input.label for="height_units" :value="__('Höheneinheiten (HE)')" />
            <x-input.field id="height_units" name="height_units" type="number" class="mt-1"
                min="1" max="{{ $maxHe }}" :value="$he"
                x-model.number="he" x-on:input.debounce.400ms="melden()" />
        </div>

        <div class="flex flex-col mt-2 w-full sm:w-1/2">
            <x-input.label for="full_depth" :value="__('Einbautiefe')" />
            <x-input.select id="full_depth" name="full_depth" class="mt-1">
                @foreach (config('custom.server_depths') as $wert => $beschriftung)
                    <option value="{{ $wert }}" @selected((string) (old('full_depth') ?? (int) ($item->full_depth ?? 1)) === (string) $wert)>{{ __($beschriftung) }}</option>
                @endforeach
            </x-input.select>
        </div>
    </div>

    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
        {{ __('Gilt beim Einbau für Geräte, die keine eigene Höhe führen – Switch, NAS, Router, USV und Recorder.') }}
    </p>

    {{-- Eine eigene Zeichnung, wo es fuer das Modell eine gibt. Sie tritt an
         die Stelle der Blende des Geraetetyps. --}}
    <div class="flex flex-col mt-4">
        <x-input.label for="drawing" :value="__('Eigene Zeichnung')" />
        <x-input.select id="drawing" name="drawing" class="mt-1" x-model="drawing" x-on:change="melden()">
            <option value="">{{ __('— Blende des Gerätetyps —') }}</option>
            @foreach (config('custom.rack_model_drawings') as $schluessel => $beschriftung)
                <option value="{{ $schluessel }}" @selected(old('drawing', $item->drawing ?? '') === $schluessel)>{{ $beschriftung }}</option>
            @endforeach
        </x-input.select>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            {{ __('Gezeichnete Frontblenden einzelner Modelle – im Projekt hinterlegt, nicht hochgeladen. Ein eigenes Bild würde auch sie ersetzen.') }}
        </p>
    </div>

    <div class="flex flex-col mt-4">
        <x-input.label for="image" :value="__('Bild der Frontblende')" />

        <div class="mt-1 flex flex-wrap items-start gap-4">
            <x-input.file id="image" name="image"
                accept="{{ collect(config('custom.bild_formate'))->map(fn ($e) => '.'.$e)->join(',') }}"
                x-on:change="neuesBild = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />

            @if ($bild)
                <input type="hidden" name="image_remove" :value="weg ? 1 : 0">

                <figure class="w-48" :class="weg && 'opacity-40'">
                    <img src="{{ $bild }}" alt=""
                        :style="'aspect-ratio: 1086 / ' + (100 * he)"
                        class="w-full rounded border border-gray-300 bg-gray-100 object-contain dark:border-gray-600 dark:bg-gray-900">
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

            <template x-if="neuesBild">
                <figure class="w-48">
                    <img :src="neuesBild" alt=""
                        :style="'aspect-ratio: 1086 / ' + (100 * he)"
                        class="w-full rounded border border-gray-300 bg-gray-100 object-contain dark:border-gray-600 dark:bg-gray-900">
                    <figcaption class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                        {{ __('Ausgewählt – im Schrank nach dem Speichern') }}
                    </figcaption>
                </figure>
            </template>
        </div>

        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
            {{ __('Das Bild gilt für alle Kunden: Wo ein solches Gerät steht, erscheint es – auch in Racks, die längst dokumentiert sind.') }}
        </p>

        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500"
            x-text="'{{ __('Empfohlene Auflösung für :he HE: :breite × :hoehe Pixel – das Seitenverhältnis einer 19-Zoll-Blende. Anders geschnittene Bilder werden gestaucht.') }}'
                .replace(':he', he).replace(':breite', 1200).replace(':hoehe', he * 110)"></p>
    </div>

    <div class="mt-4">
        <x-input.label :value="__('Vorschau im Schrank')" />
        <p class="mb-1 mt-1 text-xs text-gray-400 dark:text-gray-500">
            {{ __('Ohne Bild zeichnet die Frontansicht die Blende des Gerätetyps.') }}
        </p>
        <livewire:rack-katalog-vorschau :appearance="$zeichnungen[$gewaehlt] ?? 'server'" :he="$he"
            :bild="$bild" :drawing="$item->drawing ?? null" />
    </div>

</div>

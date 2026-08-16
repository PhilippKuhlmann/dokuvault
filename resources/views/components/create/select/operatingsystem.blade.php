<div class="flex flex-col mt-2">
    <x-input.label for="operating_system_id" :value="__('Betriebssystem')" />
    <x-input.select id="operating_system_id" name="operating_system_id">
        {{-- Kein vorausgewaehltes Betriebssystem: Vorher stand hier der erste
             Eintrag der Liste, und wer das uebersah, dokumentierte still das
             falsche. Die Auswahl ist Pflicht, ein Versehen faellt damit beim
             Speichern auf statt spaeter im Serverraum. --}}
        <option value="">— {{ __('bitte wählen') }} —</option>

        @foreach ($operatingSystems as $os)
            <option value="{{ $os->id }}">{{ $os->name }}</option>
        @endforeach
    </x-input.select>
</div>

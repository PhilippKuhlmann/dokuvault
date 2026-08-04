<div class="flex flex-col mt-2">
    <x-input.label for="recorder_id" :value="__('Recorder')" />
    <x-input.select id="recorder_id" name="recorder_id">
        @foreach ($recorders as $recorder)
            <option value="{{ $recorder->id }}">{{ $recorder->name }}</option>
        @endforeach
    </x-input.select>
</div>

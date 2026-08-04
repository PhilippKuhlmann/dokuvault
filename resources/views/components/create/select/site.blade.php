<div class="flex flex-col mt-2">
    <x-input.label for="site_id" :value="__('Standort')" />
    <x-input.select id="site_id" name="site_id">
        @foreach ($sites as $site)
            <option value="{{ $site->id }}">{{ $site->name }}</option>
        @endforeach
    </x-input.select>
</div>

<div class="flex flex-col mt-2">
    <x-input.label for="role_id" :value="__('Rolle')" />
    <x-input.select id="role_id" name="role_id">
        @foreach ($roles as $role)
            <option value="{{ $role->id }}">{{ $role->name }}</option>
        @endforeach
    </x-input.select>
</div>

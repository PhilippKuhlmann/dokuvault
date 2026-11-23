{{-- Name, description and the members of a team - for create and edit. --}}
<x-create.singlerow :label="__('Name')" name="name" :default="$team?->name" />

<x-create.singlerow :label="__('Beschreibung')" name="description" :default="$team?->description" />

<fieldset class="mt-5">
    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Mitglieder') }}</legend>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {{ __('Nur Benutzer ohne eigenen Kunden – Kundenzugänge sehen ohnehin nur ihren Kunden.') }}
    </p>
    @php ($checked = collect(old('members', $selected))->map(fn ($id) => (int) $id)->all())
    <div class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
        @foreach ($staff as $user)
            <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                <input type="checkbox" name="members[]" value="{{ $user->id }}" @checked(in_array($user->id, $checked, true))
                    class="rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                <span>{{ $user->name }}</span>
                <span class="font-mono text-xs text-gray-400">{{ $user->username }}</span>
            </label>
        @endforeach
    </div>
    @error('members')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</fieldset>

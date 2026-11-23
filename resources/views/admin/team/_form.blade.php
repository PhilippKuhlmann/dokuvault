{{-- Name, description and the members of a team - for create and edit. --}}
<x-create.singlerow :label="__('Name')" name="name" :default="$team?->name" />

<x-create.singlerow :label="__('Beschreibung')" name="description" :default="$team?->description" />

{{-- The members as cards like the section "Zugriff" on a customer: a
     click anywhere on the card ticks it, a ticked one stands out in blue
     (has-checked). Staff only - a customer login sees its customer anyway. --}}
<fieldset class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
    <legend class="flex items-center gap-1.5 px-1 text-sm font-medium text-gray-700 dark:text-gray-300">
        <x-svg.group class="h-4 w-4" />
        {{ __('Mitglieder') }}
    </legend>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('Nur Benutzer ohne eigenen Kunden – Kundenzugänge sehen ohnehin nur ihren Kunden.') }}
    </p>
    @php
        $checked = collect(old('members', $selected))->map(fn ($id) => (int) $id)->all();
        // From a handful on, a search: with dozens of users the cards alone
        // would be a long scroll to find one name.
        $withSearch = $staff->count() > 8;
    @endphp
    <div x-data="memberPicker" x-on:change="count()">
    @if ($withSearch)
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <label class="relative min-w-0 flex-1 basis-56">
                <span class="sr-only">{{ __('Benutzer suchen') }}</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <x-input.field type="search" x-model="search" :placeholder="__('Name, Anmeldename oder Rolle')" class="w-full py-2 pl-9 pr-3 text-sm" />
            </label>
            <button type="button" x-on:click="toggleOnlySelected()" x-bind:aria-pressed="onlySelected ? 'true' : 'false'"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 aria-pressed:border-cerulean-500 aria-pressed:bg-cerulean-50 aria-pressed:text-cerulean-800 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:aria-pressed:bg-cerulean-900/30 dark:aria-pressed:text-cerulean-200">
                {{ __('Nur ausgewählte') }}
            </button>
            <span class="text-sm text-gray-500 dark:text-gray-400"><span x-text="selected"></span> {{ __('ausgewählt') }}</span>
        </div>
    @endif
    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($staff as $user)
            <label @if ($withSearch) x-show="shows($el)" data-search="{{ mb_strtolower($user->name.' '.$user->username.' '.$user->role?->name) }}" @endif class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5 transition-colors hover:border-cerulean-300 has-checked:border-cerulean-500 has-checked:bg-cerulean-50 dark:border-gray-700 dark:bg-gray-800/60 dark:hover:border-cerulean-700 dark:has-checked:border-cerulean-500 dark:has-checked:bg-cerulean-900/30">
                <input type="checkbox" name="members[]" value="{{ $user->id }}" @checked(in_array($user->id, $checked, true))
                    class="shrink-0 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200" aria-hidden="true">
                    {{ collect(preg_split('/\s+/', trim($user->name)))->filter()->map(fn ($teil) => mb_strtoupper(mb_substr($teil, 0, 1)))->take(2)->join('') }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-mono">{{ $user->username }}</span>@if ($user->role) · {{ $user->role->name }}@endif
                    </span>
                </span>
            </label>
        @endforeach
    </div>
    </div>
    @error('members')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</fieldset>

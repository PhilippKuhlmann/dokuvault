{{--
    Who may see the customer (App\Support\CustomerAccess): no team assigned -
    every user, as before; teams assigned - their members only, admins
    included, each team with its level. CustomerTeams refuses a choice that
    would lock the editor out.
--}}
<fieldset class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
    <legend class="flex items-center gap-1.5 px-1 text-sm font-medium text-gray-700 dark:text-gray-300">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 13a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z" />
            <path d="M8 11V7a4 4 0 1 1 8 0v4" />
        </svg>
        {{ __('Zugriff') }}
    </legend>

    {{-- Changing it takes "Teams verwalten" (CustomerTeams): without, the
         section only shows - no inputs, so the form sends nothing. --}}
    @if (! auth()->user()->can('admin_team'))
        {{-- A block, not the one-line form: with the @php ... @endphp further
             down Blade took both for one block. --}}
        @php
            $assigned = $teams->whereIn('id', $selectedTeams);
        @endphp
        <p class="text-sm text-gray-900 dark:text-gray-100">
            @forelse ($assigned as $team)
                {{ $team->name }} ({{ \App\Support\CustomerAccess::label($levels[$team->id] ?? 'full') }})@unless ($loop->last), @endunless
            @empty
                {{ __('alle Benutzer') }}
            @endforelse
        </p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Den Zugriff ändern darf nur, wer Teams verwalten darf.') }}</p>
    @elseif ($teams->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Alle Benutzer sehen diesen Kunden. Um ihn auf bestimmte Personen zu beschränken, lege unter Admin › Teams ein Team an.') }}
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Ohne zugewiesenes Team sehen alle Benutzer den Kunden. Mit Team nur dessen Mitglieder – auch Admins.') }}
        </p>
        {{-- One switch per team instead of tick plus select: "not assigned"
             or the level. The role stays the upper bound - a level only
             takes away. Plain radios, styled as a segmented control (peer),
             so it works without script. --}}
        @php
            $chosen = old('access', collect($selectedTeams)->mapWithKeys(fn ($id) => [$id => $levels[$id] ?? 'full'])->all());
            $choices = [
                'none' => [__('Nicht zugewiesen'), __('Das Team hat hier keinen eigenen Zugriff.'), 'peer-checked:bg-white peer-checked:text-gray-900 dark:peer-checked:bg-gray-600 dark:peer-checked:text-white'],
                'full' => [__('Vollzugriff'), __('Was die Rolle erlaubt.'), 'peer-checked:bg-cerulean-600 peer-checked:text-white dark:peer-checked:bg-cerulean-500'],
                'read' => [__('Lesen'), __('Ansehen und Kennwörter aufdecken, aber nichts anlegen, ändern oder löschen.'), 'peer-checked:bg-amber-500 peer-checked:text-white dark:peer-checked:bg-amber-600'],
                'read_no_secrets' => [__('Lesen ohne Kennwörter'), __('Wie Lesen, aber kein Kennwort, Schlüssel oder PIN im Klartext.'), 'peer-checked:bg-amber-600 peer-checked:text-white dark:peer-checked:bg-amber-700'],
            ];
            // Assigned teams on top - with ten teams the ones that matter
            // here would otherwise be anywhere in the list.
            $sorted = $teams->sortBy(fn ($team) => [($chosen[$team->id] ?? 'none') === 'none' ? 1 : 0, mb_strtolower($team->name)])->values();
            $withSearch = $teams->count() > 6;
        @endphp
        <div x-data="pickList" x-on:change="count()">
        @if ($withSearch)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <label class="relative min-w-0 flex-1 basis-56">
                    <span class="sr-only">{{ __('Teams suchen') }}</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <x-input.field type="search" x-model="search" :placeholder="__('Team oder Mitglied')" class="w-full py-2 pl-9 pr-3 text-sm" />
                </label>
                <button type="button" x-on:click="toggleOnlySelected()" x-bind:aria-pressed="onlySelected ? 'true' : 'false'"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 aria-pressed:border-cerulean-500 aria-pressed:bg-cerulean-50 aria-pressed:text-cerulean-800 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:aria-pressed:bg-cerulean-900/30 dark:aria-pressed:text-cerulean-200">
                    {{ __('Nur zugewiesene') }}
                </button>
                <span class="text-sm text-gray-500 dark:text-gray-400"><span x-text="selected"></span> {{ __('zugewiesen') }}</span>
            </div>
        @endif
        <div class="mt-4 space-y-2">
            @foreach ($sorted as $team)
                <div @if ($withSearch) x-show="shows($el)" @endif data-search="{{ mb_strtolower($team->name.' '.$team->members->pluck('name')->join(' ')) }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $team->name }}</div>
                        <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $team->members->pluck('name')->join(', ') ?: __('keine Mitglieder') }}</div>
                    </div>
                    <div role="radiogroup" aria-label="{{ __('Stufe für :team', ['team' => $team->name]) }}"
                        class="inline-flex shrink-0 rounded-lg bg-gray-100 p-1 dark:bg-gray-900/60">
                        @foreach ($choices as $value => [$label, $hint, $active])
                            <label class="cursor-pointer" title="{{ $hint }}">
                                <input type="radio" name="access[{{ $team->id }}]" value="{{ $value }}" class="peer sr-only"
                                    @checked(($chosen[$team->id] ?? 'none') === $value)>
                                <span class="block whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:text-gray-900 peer-focus-visible:ring-2 peer-focus-visible:ring-cerulean-500 dark:text-gray-300 dark:hover:text-white {{ $active }}">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-gray-500 sm:grid-cols-[auto_1fr] dark:text-gray-400">
            @foreach (array_slice($choices, 1, null, true) as [$label, $hint])
                <dt class="font-semibold text-gray-700 dark:text-gray-300">{{ $label }}</dt>
                <dd>{{ $hint }}</dd>
            @endforeach
        </dl>
    @endif

    @error('teams')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</fieldset>

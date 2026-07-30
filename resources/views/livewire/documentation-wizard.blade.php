<div class="p-3 sm:p-5">

    <div class="flex items-center justify-between mb-5">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">Dokumentations-Assistent</div>
        <a href="{{ route('customer.dashboard', $customer) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            Zum Dashboard
        </a>
    </div>

    @if ($finished)
        <div class="p-8 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700 max-w-xl text-center">
            <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-2">Durchlauf abgeschlossen</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ count($run->completed_steps ?? []) }} {{ Str::plural('Bereich', count($run->completed_steps ?? [])) }} erfasst,
                {{ count($run->skipped_steps ?? []) }} übersprungen.
            </p>
            <div class="flex justify-center gap-3">
                <a href="{{ route('customer.dashboard', $customer) }}"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg font-DINPro-bold shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 px-4 py-2 text-sm bg-cerulean-600 text-white hover:bg-cerulean-700 focus:ring-cerulean-500">
                    Zum Dashboard
                </a>
                <x-input.button type="button" wire:click="restart" label="Neuen Durchlauf starten" color="gray" />
            </div>
        </div>
    @elseif ($step)
        {{-- Fortschritt: schlanke segmentierte Leiste statt einzelner Pillen je Schritt
             (Muster aus der IPAM-Auslastungsleiste) - ein Segment je Schritt, Position in Worten. --}}
        @php $currentIndex = collect($steps)->search(fn ($s) => $s['key'] === $step['key']); @endphp
        <div class="mb-6 max-w-2xl">
            <div class="flex items-baseline justify-between mb-2">
                <span class="text-sm font-DINPro-medium text-gray-700 dark:text-gray-300">{{ $step['group'] }}</span>
                <span class="text-xs text-gray-400 dark:text-gray-500 font-mono tabular-nums">
                    Schritt {{ $currentIndex + 1 }} von {{ count($steps) }}
                </span>
            </div>
            <div class="flex gap-0.5">
                @foreach ($steps as $s)
                    <div @class([
                        'h-1.5 flex-1 rounded-full',
                        'bg-cerulean-600' => $s['key'] === $step['key'],
                        'bg-cerulean-300 dark:bg-cerulean-700' => $s['key'] !== $step['key'] && in_array($s['key'], $run->completed_steps ?? []),
                        'bg-gray-300 dark:bg-gray-600' => $s['key'] !== $step['key'] && in_array($s['key'], $run->skipped_steps ?? []),
                        'bg-gray-200 dark:bg-gray-700' => $s['key'] !== $step['key'] && ! in_array($s['key'], $run->completed_steps ?? []) && ! in_array($s['key'], $run->skipped_steps ?? []),
                    ]) wire:key="progress-{{ $s['key'] }}"></div>
                @endforeach
            </div>
        </div>

        <div class="p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700 max-w-2xl">
            <div class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ $step['group'] }}</div>
            <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ $step['label'] }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ $step['question'] }}</p>

            @if ($step['key'] === 'site' && $existingSites->isNotEmpty() && ! $run->site_id)
                <div class="mb-5 space-y-1.5" wire:key="existing-sites">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Vorhandenen Standort verwenden</div>
                    @foreach ($existingSites as $site)
                        <div class="flex items-center justify-between px-3 py-2 rounded-lg border border-gray-100 dark:border-gray-700">
                            <span class="text-sm text-gray-800 dark:text-gray-100">{{ $site->name }}</span>
                            <button type="button" wire:click="selectSite({{ $site->id }})" class="text-sm text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">
                                Verwenden
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($step['key'] !== 'site' && $run->site_id)
                <div class="mb-4 text-xs text-gray-400 dark:text-gray-500">
                    Standort: <span class="text-gray-600 dark:text-gray-300">{{ $run->site?->name }}</span>
                </div>
            @endif

            @if ($entries->isNotEmpty())
                <div class="mb-5" wire:key="entries-{{ $step['key'] }}">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Bereits erfasst ({{ $entries->count() }})
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm max-h-48 overflow-y-auto">
                        @foreach ($entries as $entry)
                            <li class="py-1.5 text-gray-800 dark:text-gray-100">
                                {{ $entry->{$step['label_field']} ?: '—' }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (($step['requires'] ?? null) === 'operatingsystems' && empty($selectOptions['operating_system_id'] ?? null) && \App\Models\OperatingSystem::count() === 0)
                <div class="mb-5 p-3 rounded-lg bg-amber-50 text-amber-700 text-sm dark:bg-amber-900/20 dark:text-amber-400">
                    Es ist noch kein Betriebssystem hinterlegt. Bitte zuerst unter
                    <a href="{{ route('admin.operatingsystem.create') }}" class="underline" target="_blank">Admin → Betriebssysteme</a>
                    eines anlegen.
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                @foreach ($step['fields'] as $field)
                    <div class="flex flex-col" wire:key="{{ $step['key'] }}-{{ $field['name'] }}">
                        <x-input.label :value="$field['label']" />

                        @if (($field['type'] ?? 'text') === 'select')
                            <x-input.select :name="$field['name']" wire:model="form.{{ $field['name'] }}" class="mt-1">
                                <option value="">— bitte wählen —</option>
                                @if (is_array($field['options'] ?? null))
                                    @foreach ($field['options'] as $value => $optionLabel)
                                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                                    @endforeach
                                @else
                                    @foreach (($selectOptions[$field['name']] ?? []) as $option)
                                        <option value="{{ $option->id }}">
                                            {{ $option->name ?? $option->description ?? ('VLAN ' . $option->vlanId) }}
                                        </option>
                                    @endforeach
                                @endif
                            </x-input.select>
                        @else
                            <x-input.field :name="$field['name']" wire:model="form.{{ $field['name'] }}"
                                type="{{ $field['type'] ?? 'text' }}" class="mt-1"
                                placeholder="{{ $field['placeholder'] ?? '' }}" />
                        @endif

                        @error('form.' . $field['name'])
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-input.button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" label="Hinzufügen" />

                <div class="flex items-center gap-4">
                    <button type="button" wire:click="previousStep" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Zurück
                    </button>
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Überspringen
                    </button>
                    <x-input.button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" label="Weiter" color="gray" />
                </div>
            </div>
        </div>
    @endif

</div>

<div class="p-3 sm:p-5">

    <div class="mx-auto mb-5 flex max-w-2xl items-center justify-between">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Dokumentations-Assistent') }}</div>
        <a href="{{ route('customer.dashboard', $customer) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            {{ __('Zum Dashboard') }}
        </a>
    </div>

    @if ($finished)
        <div class="p-8 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700 mx-auto max-w-xl text-center">
            <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-2">{{ __('Durchlauf abgeschlossen') }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ count($run->completed_steps ?? []) }} {{ Str::plural('Bereich', count($run->completed_steps ?? [])) }} erfasst,
                {{ count($run->skipped_steps ?? []) }} übersprungen.
            </p>
            <div class="flex justify-center gap-3">
                <a href="{{ route('customer.dashboard', $customer) }}"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg font-DINPro-bold shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 px-4 py-2 text-sm bg-cerulean-600 text-white hover:bg-cerulean-700 focus:ring-cerulean-500">
                    {{ __('Zum Dashboard') }}
                </a>
                <x-input.button type="button" wire:click="restart" :label="__('Neuen Durchlauf starten')" color="gray" />
            </div>
        </div>
    @elseif ($step)
        {{-- Fortschritt: schlanke segmentierte Leiste statt einzelner Pillen je Schritt
             (Muster aus der IPAM-Auslastungsleiste) - ein Segment je Schritt, Position in Worten. --}}
        @php $currentIndex = collect($steps)->search(fn ($s) => $s['key'] === $step['key']); @endphp
        <div class="mb-6 mx-auto max-w-2xl">
            <div class="flex items-baseline justify-between mb-2">
                <span class="text-sm font-DINPro-medium text-gray-700 dark:text-gray-300">{{ __($step['group']) }}</span>
                <span class="text-xs text-gray-400 dark:text-gray-500 font-mono tabular-nums">
                    Schritt {{ $currentIndex + 1 }} von {{ count($steps) }}
                </span>
            </div>
            {{-- Jeder Balken traegt seinen Schrittnamen als Titel: Sechzehn
                 namenlose Striche sagen nur, wie weit es noch ist, nicht was
                 kommt. Der aktuelle ist doppelt so hoch und damit auch ohne
                 Farbunterscheidung zu finden. --}}
            <div class="flex items-end gap-0.5">
                @foreach ($steps as $s)
                    @php
                        $erledigt = in_array($s['key'], $run->completed_steps ?? []);
                        $uebersprungen = in_array($s['key'], $run->skipped_steps ?? []);
                        $stand = $erledigt ? __('erfasst') : ($uebersprungen ? __('übersprungen') : __('offen'));
                    @endphp

                    <x-hovertext :text="__($s['label']).' — '.$stand" class="flex-1" wire:key="progress-{{ $s['key'] }}">
                        {{-- Klickbar statt bloss Anzeige: Man merkt oft erst drei
                             Schritte weiter, dass etwas fehlt. --}}
                        <button type="button" wire:click="gotoStep('{{ $s['key'] }}')"
                            aria-label="{{ __($s['label']) }}" @class([
                                'block w-full rounded-full transition-all hover:opacity-80',
                                'h-3' => $s['key'] === $step['key'],
                                'h-1.5 hover:h-3' => $s['key'] !== $step['key'],
                                'bg-cerulean-600' => $s['key'] === $step['key'],
                                'bg-cerulean-300 dark:bg-cerulean-700' => $s['key'] !== $step['key'] && $erledigt,
                                'bg-gray-300 dark:bg-gray-600' => $s['key'] !== $step['key'] && $uebersprungen,
                                'bg-gray-200 dark:bg-gray-700' => $s['key'] !== $step['key'] && ! $erledigt && ! $uebersprungen,
                            ])></button>
                    </x-hovertext>
                @endforeach
            </div>
        </div>

        <div class="p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700 mx-auto max-w-2xl">
            <div class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ __($step['group']) }}</div>
            <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __($step['label']) }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ __($step['question']) }}</p>

            @if ($step['key'] === 'site' && $existingSites->isNotEmpty() && ! $run->site_id)
                <div class="mb-5 space-y-1.5" wire:key="existing-sites">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ __('Vorhandenen Standort verwenden') }}</div>
                    @foreach ($existingSites as $site)
                        <div class="flex items-center justify-between px-3 py-2 rounded-lg border border-gray-100 dark:border-gray-700">
                            <span class="text-sm text-gray-800 dark:text-gray-100">{{ $site->name }}</span>
                            <button type="button" wire:click="selectSite({{ $site->id }})" class="text-sm text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">
                                {{ __('Verwenden') }}
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($step['key'] !== 'site' && $run->site_id)
                <div class="mb-4 text-xs text-gray-400 dark:text-gray-500">
                    {{ __('Standort:') }} <span class="text-gray-600 dark:text-gray-300">{{ $run->site?->name }}</span>
                </div>
            @endif

            {{-- Beim Standort-Schritt stehen dieselben Eintraege schon oben in der
                 Auswahl "Vorhandenen Standort verwenden" - zweimal dieselbe Liste
                 untereinander sagt nichts Zusaetzliches. --}}
            @if ($entries->isNotEmpty() && $step['key'] !== 'site')
                {{-- Abgesetzte Flaeche mit Kacheln statt einer Zeilenliste: Was
                     schon erfasst ist, soll man ueberfliegen und nicht Zeile fuer
                     Zeile lesen. --}}
                <div class="mb-5 rounded-lg bg-gray-50 p-3 dark:bg-gray-700/40" wire:key="entries-{{ $step['key'] }}">
                    {{-- Kurzform ohne Leerzeichen: "@php (" liest Blade als
                         Blockanfang und schluckt alles bis zum naechsten
                         @endphp. --}}
                    {{-- Bearbeiten laeuft im Modal der Liste, eigene
                         /edit-Seiten gibt es nicht mehr. Der Eintrag fuehrt
                         deshalb in seine Liste - dort steht er samt Stift. --}}
                    @php($ziel = Route::has($step['key'].'.edit') ? $step['key'].'.edit' : ($step['key'].'.index'))
                    @php($bearbeitbar = Route::has($ziel))

                    <div class="mb-2 flex flex-wrap items-baseline gap-x-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Schon erfasst') }} ({{ $entries->count() }})

                        @if ($bearbeitbar)
                            {{-- Der Durchlauf soll nicht verloren gehen, wenn man
                                 etwas nachtraegt - deshalb ein neuer Tab. --}}
                            <span class="font-normal normal-case tracking-normal text-gray-400 dark:text-gray-500">
                                {{ __('zum Nachtragen anklicken, öffnet die Liste in einem neuen Tab') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
                        @foreach ($entries as $entry)
                            @if ($bearbeitbar)
                                <a href="{{ $ziel === $step['key'].'.edit' ? route($ziel, [$customer, $entry]) : route($ziel, $customer) }}" target="_blank" rel="noopener"
                                    class="rounded border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 transition-colors hover:border-cerulean-400 hover:text-cerulean-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-cerulean-500 dark:hover:text-cerulean-300">
                                    {{ $entry->{$step['label_field']} ?: '—' }}
                                </a>
                            @else
                                <span class="rounded border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                    {{ $entry->{$step['label_field']} ?: '—' }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if (($step['requires'] ?? null) === 'operatingsystems' && empty($selectOptions['operating_system_id'] ?? null) && \App\Models\OperatingSystem::count() === 0)
                <div class="mb-5 p-3 rounded-lg bg-amber-50 text-amber-700 text-sm dark:bg-amber-900/20 dark:text-amber-400">
                    {{ __('Es ist noch kein Betriebssystem hinterlegt. Bitte zuerst unter') }}
                    <a href="{{ route('admin.operatingsystem.create') }}" class="underline" target="_blank">{{ __('Admin → Betriebssysteme') }}</a>
                    eines anlegen.
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                @foreach ($step['fields'] as $field)
                    <div class="flex flex-col" wire:key="{{ $step['key'] }}-{{ $field['name'] }}">
                        <x-input.label :value="__($field['label'])" />

                        @if (($field['type'] ?? 'text') === 'select')
                            <x-input.select :name="$field['name']" wire:model="form.{{ $field['name'] }}" class="mt-1">
                                <option value="">— bitte wählen —</option>
                                @if (is_array($field['options'] ?? null))
                                    @foreach ($field['options'] as $value => $optionLabel)
                                        <option value="{{ $value }}">{{ __($optionLabel) }}</option>
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
                            {{-- 'sofort' in der Feldliste: Das Feld meldet sich
                                 waehrend der Eingabe an den Server, weil es ein
                                 anderes nachzieht (Subnetzmaske und CIDR). Alle
                                 uebrigen bleiben stumm bis zum Speichern.

                                 Zwei Zweige statt eines Ausdrucks im
                                 Attributnamen: "wire:model" mit einem
                                 Blade-Ausdruck dahinter zerlegt den
                                 Komponenten-Parser, und die Felder verlieren
                                 dabei alle uebrigen Attribute. --}}
                            @if ($field['sofort'] ?? false)
                                <x-input.field :name="$field['name']"
                                    wire:model.live.debounce.600ms="form.{{ $field['name'] }}"
                                    type="{{ $field['type'] ?? 'text' }}" class="mt-1"
                                    placeholder="{{ $field['placeholder'] ?? '' }}" />
                            @else
                                <x-input.field :name="$field['name']" wire:model="form.{{ $field['name'] }}"
                                    type="{{ $field['type'] ?? 'text' }}" class="mt-1"
                                    placeholder="{{ $field['placeholder'] ?? '' }}" />
                            @endif
                        @endif

                        @error('form.' . $field['name'])
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-input.button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" :label="__('Hinzufügen')" />

                <div class="flex items-center gap-4">
                    <button type="button" wire:click="previousStep" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        {{ __('Zurück') }}
                    </button>
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        {{ __('Überspringen') }}
                    </button>
                    <x-input.button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" :label="__('Weiter')" color="gray" />
                </div>
            </div>
        </div>
    @endif

</div>

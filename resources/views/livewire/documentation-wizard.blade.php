<div class="p-3 sm:p-5">

    <div class="mx-auto mb-6 max-w-2xl text-center">
        <div class="text-[11px] font-DINPro-bold uppercase tracking-[0.25em] text-cerulean-600 dark:text-cerulean-400">{{ __('Erstaufnahme') }}</div>
        <h1 class="mt-1.5 text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Dokumentations-Assistent') }}</h1>
        <a href="{{ route('customer.dashboard', $customer) }}" class="mt-1 inline-block text-sm text-gray-500 transition-colors hover:text-cerulean-600 dark:text-gray-400 dark:hover:text-cerulean-400">
            {{ $customer->name }} · {{ __('Zum Dashboard') }}
        </a>
    </div>

    @if ($finished)
        <x-panel polster="weit" class="mx-auto max-w-xl text-center">
            <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-2">{{ __('Durchlauf abgeschlossen') }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ count($run->completed_steps ?? []) }} {{ Str::plural('Bereich', count($run->completed_steps ?? [])) }} erfasst,
                {{ count($run->skipped_steps ?? []) }} übersprungen.
            </p>

            @if (! empty($zusammenfassung))
                {{-- Was dieser Durchlauf angelegt hat, je Bereich mit Absprung in
                     die Liste (neuer Tab) - siehe DocumentationRun::created_records. --}}
                <div class="mb-6 rounded-lg bg-gray-50 p-4 text-left dark:bg-gray-700/40">
                    <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('In diesem Durchlauf erfasst') }}</div>
                    <div class="space-y-3">
                        @foreach ($zusammenfassung as $bereich)
                            <div wire:key="zus-{{ $loop->index }}">
                                <div class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    @if ($bereich['route'])
                                        <a href="{{ route($bereich['route'], $customer) }}" target="_blank" rel="noopener" class="hover:text-cerulean-600 dark:hover:text-cerulean-400">{{ $bereich['label'] }}</a>
                                    @else
                                        {{ $bereich['label'] }}
                                    @endif
                                    <span class="text-gray-400 dark:text-gray-500">({{ count($bereich['namen']) }})</span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($bereich['namen'] as $name)
                                        <span class="rounded border border-gray-200 bg-white px-2 py-0.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">{{ $name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-center gap-3">
                <a href="{{ route('customer.dashboard', $customer) }}"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg font-DINPro-bold shadow-xs transition-colors focus:outline-hidden focus:ring-2 focus:ring-offset-2 px-4 py-2 text-sm bg-cerulean-600 text-white hover:bg-cerulean-700 focus:ring-cerulean-500">
                    {{ __('Zum Dashboard') }}
                </a>
                <x-input.button type="button" wire:click="restart" :label="__('Neuen Durchlauf starten')" color="gray" />
            </div>
        </x-panel>
    @elseif ($step)
        {{-- Fortschritt: schlanke segmentierte Leiste statt einzelner Pillen je Schritt
             (Muster aus der IPAM-Auslastungsleiste) - ein Segment je Schritt, Position in Worten. --}}
        @php
            $currentIndex = collect($steps)->search(fn ($s) => $s['key'] === $step['key']);
            // Bereiche in Reihenfolge, jeder mit seinen Schritten.
            $gruppen = collect($steps)->groupBy('group');
            $gruppenNamen = collect($steps)->pluck('group')->unique()->values();
            $done = $run->completed_steps ?? [];
            $skip = $run->skipped_steps ?? [];
        @endphp
        <div class="mb-6 mx-auto max-w-2xl">
            {{-- Bereiche als Kette statt achtzehn gleicher Striche: So sieht man,
                 wo im Ganzen man steht, nicht nur wie weit. Jeder Knoten führt zum
                 ersten Schritt seines Bereichs. --}}
            <ol class="flex items-start">
                @foreach ($gruppenNamen as $gi => $gname)
                    @php
                        $gschritte = $gruppen[$gname];
                        $gErledigt = $gschritte->every(fn ($s) => in_array($s['key'], $done, true) || in_array($s['key'], $skip, true));
                        $gAktiv = $gname === $step['group'];
                    @endphp
                    <li class="flex min-w-0 flex-1 flex-col items-center" wire:key="grp-{{ $gi }}">
                        <div class="flex w-full items-center">
                            <span class="h-0.5 flex-1 rounded {{ $loop->first ? 'opacity-0' : 'bg-gray-200 dark:bg-gray-700' }}"></span>
                            <button type="button" wire:click="gotoStep('{{ $gschritte->first()['key'] }}')" title="{{ __($gname) }}"
                                @class([
                                    'mx-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-DINPro-bold transition-colors',
                                    'bg-cerulean-600 text-white ring-4 ring-cerulean-100 dark:ring-cerulean-900/50' => $gAktiv,
                                    'bg-cerulean-100 text-cerulean-700 hover:bg-cerulean-200 dark:bg-cerulean-900/40 dark:text-cerulean-300' => $gErledigt && ! $gAktiv,
                                    'bg-gray-100 text-gray-400 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-500' => ! $gAktiv && ! $gErledigt,
                                ])>
                                @if ($gErledigt && ! $gAktiv)
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                @else
                                    {{ $gi + 1 }}
                                @endif
                            </button>
                            <span class="h-0.5 flex-1 rounded {{ $loop->last ? 'opacity-0' : 'bg-gray-200 dark:bg-gray-700' }}"></span>
                        </div>
                        <span @class([
                            'mt-1.5 max-w-full truncate px-1 text-center text-[11px] leading-tight transition-colors',
                            'font-DINPro-bold text-gray-900 dark:text-gray-100' => $gAktiv,
                            'text-gray-400 dark:text-gray-500' => ! $gAktiv,
                        ])>{{ __($gname) }}</span>
                    </li>
                @endforeach
            </ol>

            {{-- Ein Gesamtbalken über alle Schritte - dieselbe Aussage wie der Zähler
                 daneben. Vorher stand hier ein Strich je Schritt der aktuellen Gruppe;
                 über die volle Breite gezogen sah das neben der Bereichskette darüber
                 wie eine zweite, widersprüchliche Leiste aus (halb gefüllt, aber "2/18"). --}}
            <div class="mt-3 flex items-center gap-3">
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-cerulean-600 transition-all"
                        style="width: {{ round(($currentIndex + 1) / max(count($steps), 1) * 100) }}%"></div>
                </div>
                <span class="shrink-0 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ __('Schritt :n von :total', ['n' => $currentIndex + 1, 'total' => count($steps)]) }}</span>
            </div>
        </div>

        <x-panel class="sm:p-6 mx-auto max-w-2xl">
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
                {{-- Bearbeiten direkt hier im Modal, sofern der Nutzer aendern darf:
                     die meisten Schritte ueber die config-getriebene ObjektFormular-
                     Komponente ($imModal, Event objekt-bearbeiten), 'network' ueber
                     sein eigenes VLAN-Modal NetworkQuickCreate ($netzModal, Event
                     vlan-bearbeiten). Ohne Aenderungsrecht fuehrt der Eintrag weiter
                     in seine Liste (neuer Tab). --}}
                @php($imModal = array_key_exists($step['key'], config('forms')) && auth()->user()?->can($step['key'].'_update'))
                @php($netzModal = $step['key'] === 'network' && auth()->user()?->can('network_update'))
                @php($ziel = Route::has($step['key'].'.edit') ? $step['key'].'.edit' : ($step['key'].'.index'))
                @php($bearbeitbar = Route::has($ziel))

                {{-- Abgesetzte Flaeche mit Kacheln statt einer Zeilenliste: Was
                     schon erfasst ist, soll man ueberfliegen und nicht Zeile fuer
                     Zeile lesen. --}}
                <div class="mb-5 rounded-lg bg-gray-50 p-3 dark:bg-gray-700/40" wire:key="entries-{{ $step['key'] }}">
                    <div class="mb-2 flex flex-wrap items-baseline gap-x-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Schon erfasst') }} ({{ $entries->count() }})

                        @if ($imModal || $netzModal)
                            <span class="font-normal normal-case tracking-normal text-gray-400 dark:text-gray-500">
                                {{ __('zum Bearbeiten anklicken') }}
                            </span>
                        @elseif ($bearbeitbar)
                            {{-- Der Durchlauf soll nicht verloren gehen, wenn man
                                 etwas nachtraegt - deshalb ein neuer Tab. --}}
                            <span class="font-normal normal-case tracking-normal text-gray-400 dark:text-gray-500">
                                {{ __('zum Nachtragen anklicken, öffnet die Liste in einem neuen Tab') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
                        @foreach ($entries as $entry)
                            @if ($imModal)
                                <button type="button" wire:key="entry-{{ $step['key'] }}-{{ $entry->id }}"
                                    wire:click="$dispatch('objekt-bearbeiten', { typ: '{{ $step['key'] }}', id: {{ $entry->id }} })"
                                    class="rounded border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 transition-colors hover:border-cerulean-400 hover:text-cerulean-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-cerulean-500 dark:hover:text-cerulean-300">
                                    {{ $entry->{$step['label_field']} ?: '—' }}
                                </button>
                            @elseif ($netzModal)
                                <button type="button" wire:key="entry-{{ $step['key'] }}-{{ $entry->id }}"
                                    wire:click="$dispatch('vlan-bearbeiten', { id: {{ $entry->id }} })"
                                    class="rounded border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 transition-colors hover:border-cerulean-400 hover:text-cerulean-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-cerulean-500 dark:hover:text-cerulean-300">
                                    {{ $entry->{$step['label_field']} ?: '—' }}
                                </button>
                            @elseif ($bearbeitbar)
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

                @if ($imModal)
                    {{-- Nur das Bearbeiten-Modal, ohne eigenen "Neu"-Knopf (der Assistent
                         legt selbst an). Faengt das oben ausgeloeste objekt-bearbeiten und
                         meldet nach dem Speichern objekt-gespeichert; darauf rendert der
                         Assistent neu und die "Schon erfasst"-Liste ist aktuell. --}}
                    <livewire:objekt-formular :typ="$step['key']" :customer="$customer" :mitKnopf="false"
                        wire:key="wizard-objektformular-{{ $step['key'] }}" />
                @elseif ($netzModal)
                    {{-- Netzwerk hat sein eigenes VLAN-Modal. Faengt vlan-bearbeiten und
                         meldet nach dem Speichern vlan-angelegt (darauf rendert der
                         Assistent neu, siehe objektGespeichert()). --}}
                    <livewire:network-quick-create :customer="$customer" :mitKnopf="false"
                        wire:key="wizard-netzformular" />
                @endif
            @endif

            @if (($step['requires'] ?? null) === 'operatingsystems' && empty($selectOptions['operating_system_id'] ?? null) && \App\Models\OperatingSystem::count() === 0)
                <div class="mb-5 p-3 rounded-lg bg-amber-50 text-amber-700 text-sm dark:bg-amber-900/20 dark:text-amber-400">
                    {{ __('Es ist noch kein Betriebssystem hinterlegt. Bitte zuerst unter') }}
                    <a href="{{ route('admin.operatingsystem.create') }}" class="underline" target="_blank">{{ __('Admin → Betriebssysteme') }}</a>
                    eines anlegen.
                </div>
            @endif

            {{-- Nach "Hinzufuegen" hat der Server das Formular geleert, der
                 Browser aber nicht: Livewire schuetzt Eingabefelder beim
                 Morphen, der getippte Name blieb stehen und ein zweiter Klick
                 legte denselben Eintrag noch einmal an. Siehe resetForm(). --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6"
                x-data
                {{-- $nextTick ist Pflicht, nicht Kosmetik: Livewire feuert dieses
                     Event, morpht das DOM aber gleich danach und stellt die als
                     "dirty" geschuetzten (gerade getippten) Eingaben wieder her -
                     sofort geleert waeren sie danach wieder voll. Erst nach dem
                     Morph leeren. --}}
                @assistent-formular-geleert.window="$nextTick(() => $el.querySelectorAll('input, textarea, select').forEach(feld => feld.value = ''))"
                {{-- Enter in einem Textfeld fügt hinzu (kein Mausklick nötig); in
                     select/textarea bleibt Enter, was es ist. --}}
                x-on:keydown.enter.prevent="if ($event.target.matches('input')) $wire.save()"
                {{-- Beim Schrittwechsel ans erste Feld springen und nach oben
                     scrollen - siehe DocumentationWizard::dispatch('assistent-schritt'). --}}
                @assistent-schritt.window="$nextTick(() => { $el.querySelector('input, select')?.focus(); window.scrollTo({ top: 0, behavior: 'smooth' }) })">
                @foreach ($step['fields'] as $field)
                    <div class="flex flex-col" wire:key="{{ $step['key'] }}-{{ $field['name'] }}">
                        <x-input.label :value="__($field['label'])" />

                        @if (($field['type'] ?? 'text') === 'select')
                            <x-input.select :name="$field['name']" wire:model.live.debounce.400ms="form.{{ $field['name'] }}" class="mt-1">
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
                                <x-input.field :name="$field['name']" wire:model.live.debounce.400ms="form.{{ $field['name'] }}"
                                    type="{{ $field['type'] ?? 'text' }}" class="mt-1"
                                    placeholder="{{ $field['placeholder'] ?? '' }}" />
                            @endif
                        @endif

                        <x-input.fehler :feld="'form.' . $field['name']" />
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
                    {{-- Ganze Gruppe auf einmal weglassen, z. B. "keine Telefonie". --}}
                    <button type="button" wire:click="skipGroup" class="text-sm text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        title="{{ __('Alle offenen Schritte der Gruppe :gruppe überspringen', ['gruppe' => __($step['group'])]) }}">
                        {{ __('Gruppe überspringen') }}
                    </button>
                    <x-input.button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" :label="__('Weiter')" color="gray" />
                </div>
            </div>
        </x-panel>
    @endif

</div>
